<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginIpBlock;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminSecurityEvent;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Models\Activity;

final class AdminSecurityTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, ActivitylogServiceProvider::class, LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }

    public function test_repeated_failures_create_a_risk_event_and_temporary_lock(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/admin/auth/login', ['username' => 'admin', 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/admin/auth/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'LOGIN_TEMPORARILY_LOCKED');

        self::assertTrue(AdminSecurityEvent::query()->where('code', 'auth.login.risk_threshold')->exists());
    }

    public function test_default_manager_can_use_security_center_and_writes_audit_logs(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->getJson('/api/admin/system/security/events')->assertUnauthorized();
        $manager = AdminUser::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($manager, ['admin']);
        $this->getJson('/api/admin/system/security/events')->assertOk();
        $blockId = $this->postJson('/api/admin/system/security/ip-blocks', [
            'ip_address' => '203.0.113.10',
            'reason_code' => 'MANUAL_SECURITY_BLOCK',
            'duration_hours' => 24,
        ])->assertCreated()->json('block.id');

        $this->getJson('/api/admin/system/security/ip-blocks')->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/api/admin/system/security/ip-blocks/'.$blockId.'/release', [])->assertOk();
        self::assertNotNull(AdminLoginIpBlock::query()->findOrFail($blockId)->released_at);
        self::assertTrue(Activity::query()->where('event', 'system.security.ip-block.created')->exists());
        self::assertTrue(Activity::query()->where('event', 'system.security.ip-block.released')->exists());
    }

    public function test_duplicate_active_ip_block_is_rejected(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        Sanctum::actingAs(AdminUser::query()->where('username', 'cmsadmin')->firstOrFail(), ['admin']);
        $payload = ['ip_address' => '203.0.113.20', 'reason_code' => 'MANUAL_SECURITY_BLOCK'];
        $this->postJson('/api/admin/system/security/ip-blocks', $payload)->assertCreated();
        $this->postJson('/api/admin/system/security/ip-blocks', $payload)
            ->assertUnprocessable()->assertJsonPath('code', 'SECURITY_IP_ALREADY_BLOCKED');
        self::assertSame(1, AdminLoginIpBlock::query()->where('ip_address', '203.0.113.20')->count());
    }

    public function test_invalid_ip_block_input_is_rejected_without_writing(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        Sanctum::actingAs(AdminUser::query()->where('username', 'cmsadmin')->firstOrFail(), ['admin']);

        $this->postJson('/api/admin/system/security/ip-blocks', [
            'ip_address' => '203.0.113.0/24',
            'reason_code' => 'MANUAL_SECURITY_BLOCK',
        ])->assertUnprocessable();

        self::assertSame(0, AdminLoginIpBlock::query()->count());
    }

    public function test_security_permissions_and_menu_are_granted_to_manager_by_default(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        self::assertTrue(AdminPermission::query()->where('code', 'system.security.view')->exists());
        $manager = AdminUser::query()->where('username', 'admin')->firstOrFail()->roles()->firstOrFail();
        self::assertTrue($manager->permissions()->where('code', 'system.security.view')->exists());
        self::assertTrue($manager->permissions()->where('code', 'system.security.update')->exists());
        self::assertTrue($manager->menus()->where('code', 'system.security')->exists());
    }
}
