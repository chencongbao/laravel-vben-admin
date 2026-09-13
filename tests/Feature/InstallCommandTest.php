<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

final class InstallCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_install_creates_the_default_administrator_without_resetting_it(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();

        self::assertTrue(Hash::check('admin', $administrator->password));
        self::assertTrue($administrator->roles()->where('code', 'administrator')->exists());
        self::assertTrue(AdminRole::query()->where('code', 'administrator')->where('is_super_admin', true)->exists());
        self::assertTrue(AdminRole::query()->where('code', 'manager')->where('is_super_admin', false)->exists());
        self::assertTrue(Schema::hasColumns('personal_access_tokens', ['ip_address', 'user_agent']));
        self::assertDatabaseHas('admin_menus', [
            'code' => 'configuration',
            'parent_code' => null,
            'title' => 'page.configuration.title',
        ]);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'system.settings',
            'parent_code' => 'configuration',
        ]);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'system.logs',
            'parent_code' => null,
            'title' => 'page.systemLogs.title',
        ]);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'system.login-logs',
            'parent_code' => 'system.logs',
        ]);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'system.audit-logs',
            'parent_code' => 'system.logs',
        ]);

        $administrator->update(['password' => 'changed-password']);

        $this->artisan('vben-admin:install')->assertSuccessful();

        self::assertTrue(Hash::check('changed-password', $administrator->fresh()->password));
    }

    public function test_sync_preserves_settings_access_after_adding_the_configuration_parent(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $manager = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $settings = AdminMenu::query()->where('code', 'system.settings')->firstOrFail();
        $configuration = AdminMenu::query()->where('code', 'configuration')->firstOrFail();

        $manager->menus()->sync([$settings->getKey()]);

        $this->artisan('vben-admin:sync')->assertSuccessful();

        self::assertTrue($manager->menus()->whereKey($settings->getKey())->exists());
        self::assertTrue($manager->menus()->whereKey($configuration->getKey())->exists());
    }

    public function test_sync_preserves_log_access_after_adding_the_system_logs_parent(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $manager = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $loginLogs = AdminMenu::query()->where('code', 'system.login-logs')->firstOrFail();
        $systemLogs = AdminMenu::query()->where('code', 'system.logs')->firstOrFail();

        $manager->menus()->sync([$loginLogs->getKey()]);

        $this->artisan('vben-admin:sync')->assertSuccessful();

        self::assertTrue($manager->menus()->whereKey($loginLogs->getKey())->exists());
        self::assertTrue($manager->menus()->whereKey($systemLogs->getKey())->exists());
    }
}
