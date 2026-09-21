<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationPublisher;
use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class AdminNotificationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
    }

    public function test_install_creates_notification_tables_and_authenticated_users_can_read_notifications(): void
    {
        self::assertTrue(Schema::hasTable('admin_notifications'));
        self::assertTrue(Schema::hasTable('admin_notification_states'));

        $this->app->make(AdminNotificationPublisher::class)->publish([
            'code' => 'system.release.ready',
            'title_key' => 'project.release.ready',
            'title' => 'Release ready',
            'message' => 'Version 1.2.0 is ready.',
            'parameters' => ['version' => '1.2.0'],
            'link' => '/releases/1',
        ]);

        $this->getJson('/api/admin/notifications')->assertUnauthorized();

        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($user, ['admin']);

        $this->getJson('/api/admin/notifications/latest')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'system.release.ready')
            ->assertJsonPath('data.0.is_read', false);
        $this->getJson('/api/admin/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_read_and_hidden_state_is_isolated_per_user(): void
    {
        $notification = $this->app->make(AdminNotificationPublisher::class)->publish([
            'code' => 'system.maintenance',
            'title' => 'Maintenance',
        ]);
        $manager = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();

        Sanctum::actingAs($manager, ['admin']);
        $this->postJson("/api/admin/notifications/{$notification->getKey()}/read")->assertOk();
        $this->getJson('/api/admin/notifications/latest')->assertJsonPath('data.0.is_read', true);

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->getJson('/api/admin/notifications/latest')->assertJsonPath('data.0.is_read', false);

        Sanctum::actingAs($manager, ['admin']);
        $this->deleteJson("/api/admin/notifications/{$notification->getKey()}")->assertOk();
        $this->getJson('/api/admin/notifications/latest')->assertJsonCount(0, 'data');

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->getJson('/api/admin/notifications/latest')->assertJsonCount(1, 'data');
    }

    public function test_expired_notifications_are_hidden_and_unsafe_payloads_are_rejected(): void
    {
        $publisher = $this->app->make(AdminNotificationPublisher::class);
        $publisher->publish([
            'code' => 'system.expired',
            'title' => 'Expired',
            'published_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        Sanctum::actingAs(AdminUser::query()->where('username', 'admin')->firstOrFail(), ['admin']);
        $this->getJson('/api/admin/notifications/latest')->assertJsonCount(0, 'data');

        try {
            $publisher->publish([
                'code' => 'system.unsafe',
                'title' => 'Unsafe',
                'link' => 'javascript:alert(1)',
                'metadata' => ['access_token' => 'secret'],
            ]);
            self::fail('Unsafe notification data should be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('link', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $publisher->publish([
            'code' => 'system.secret',
            'title' => 'Secret',
            'metadata' => ['nested' => ['password' => 'secret']],
        ]);
    }
}
