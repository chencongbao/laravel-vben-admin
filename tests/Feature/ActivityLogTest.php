<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelVbenAdminServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
    }

    public function test_install_creates_the_unified_activity_log_table(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        self::assertTrue(Schema::hasColumns('activity_log', [
            'log_name',
            'log_type',
            'description',
            'event',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'attribute_changes',
            'properties',
            'ip_address',
            'method',
            'path',
            'user_agent',
            'legacy_source',
            'legacy_id',
        ]));
        self::assertFalse(Schema::hasTable('admin_login_logs'));
        self::assertFalse(Schema::hasTable('admin_audit_logs'));
    }

    public function test_failed_and_successful_logins_are_written_to_the_activity_log(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)')
            ->postJson('/api/admin/auth/login', [
                'username' => 'admin',
                'password' => 'wrong-password',
            ])->assertStatus(422);

        $failed = Activity::query()->where('log_type', 'login')->latest('id')->firstOrFail();
        self::assertSame('auth.login.failed', $failed->event);
        self::assertSame('admin', $failed->log_name);
        self::assertSame('admin', $failed->getProperty('username'));
        self::assertFalse($failed->getProperty('succeeded'));
        self::assertSame('INVALID_CREDENTIALS', $failed->getProperty('failure_code'));
        self::assertSame('desktop', $failed->getProperty('client_type'));

        $this->postJson('/api/admin/auth/login', [
            'username' => 'cmsadmin',
            'password' => 'admin',
        ])->assertOk();

        $succeeded = Activity::query()->where('event', 'auth.login.succeeded')->firstOrFail();
        self::assertTrue($succeeded->getProperty('succeeded'));
        self::assertNotNull($succeeded->causer_id);

        Sanctum::actingAs($succeeded->causer, ['admin']);
        $this->getJson('/api/admin/system/login-logs?id='.$failed->getKey().'&username=admin&succeeded=0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $failed->getKey())
            ->assertJsonPath('data.0.failure_code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('data.0.client_type', 'desktop');

        $this->deleteJson('/api/admin/system/login-logs/batch', ['ids' => [$failed->getKey()]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1);
        self::assertFalse(Activity::query()->whereKey($failed->getKey())->exists());
        $deletionAudit = Activity::query()->where('event', 'system.login-log.batch-deleted')->firstOrFail();
        self::assertSame(1, $deletionAudit->getProperty('context.deleted_count'));
        self::assertSame([$failed->getKey()], $deletionAudit->getProperty('context.deleted_ids'));
    }

    public function test_operation_audit_uses_spatie_and_redacts_sensitive_values(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $request = Request::create('/api/admin/system/settings', 'PUT', [
            'name' => 'RG LIVE',
            'password' => 'plain-text-password',
            'nested' => ['two_factor_secret' => 'plain-text-secret'],
        ], server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'ActivityLogTest/1.0',
        ]);
        $this->app->instance('request', $request);

        $this->app->make(AuditRecorder::class)->record(
            $actor,
            'system.setting.updated',
            $actor,
            ['before' => ['password' => 'old'], 'after' => ['password' => 'new']],
            ['access_token' => 'token-value'],
        );

        $activity = Activity::query()->where('log_type', 'operation')->firstOrFail();
        self::assertSame('system.setting.updated', $activity->event);
        self::assertSame($actor->getKey(), $activity->causer_id);
        self::assertSame($actor->getKey(), $activity->subject_id);
        self::assertSame('[REDACTED]', $activity->attribute_changes->get('before')['password']);
        self::assertSame('[REDACTED]', $activity->getProperty('context.access_token'));
        self::assertSame('[REDACTED]', $activity->getProperty('request_input.password'));
        self::assertSame('[REDACTED]', $activity->getProperty('request_input.nested.two_factor_secret'));
        self::assertSame('203.0.113.10', $activity->ip_address);
        self::assertSame('PUT', $activity->method);
        self::assertSame('/api/admin/system/settings', $activity->path);

        Sanctum::actingAs($actor, ['admin']);
        $this->getJson('/api/admin/system/audit-logs?id='.$activity->getKey().'&action=system.setting.updated')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $activity->getKey())
            ->assertJsonPath('data.0.method', 'PUT')
            ->assertJsonPath('data.0.path', '/api/admin/system/settings');

        $this->deleteJson('/api/admin/system/login-logs/batch', ['ids' => [$activity->getKey()]])
            ->assertUnprocessable();
        self::assertTrue(Activity::query()->whereKey($activity->getKey())->exists());
    }

    public function test_setting_batch_creates_one_audit_record_and_unchanged_values_create_none(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        Sanctum::actingAs($actor, ['admin']);
        $payload = [
            'settings' => [
                ['key' => 'system.name', 'value' => 'Audit Test'],
                ['key' => 'system.page_size', 'value' => 30],
            ],
        ];

        $this->putJson('/api/admin/system/settings', $payload)->assertOk();

        $activity = Activity::query()->where('event', 'system.settings.updated')->firstOrFail();
        self::assertSame(['system.name', 'system.page_size'], $activity->getProperty('context.keys'));
        self::assertSame('Audit Test', $activity->attribute_changes->get('settings')['system.name']['after']);
        self::assertSame(30, $activity->attribute_changes->get('settings')['system.page_size']['after']);

        $this->putJson('/api/admin/system/settings', $payload)->assertOk();

        self::assertSame(1, Activity::query()->where('event', 'system.settings.updated')->count());
    }
}
