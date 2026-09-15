<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Support\SystemSettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class InstallCommandTest extends TestCase
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
    }

    public function test_install_creates_default_accounts_without_resetting_them(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();

        self::assertTrue(Hash::check('admin', $superAdministrator->password));
        self::assertTrue(Hash::check('admin', $administrator->password));
        self::assertTrue($superAdministrator->roles()->where('code', 'administrator')->exists());
        self::assertTrue($administrator->roles()->where('code', 'manager')->exists());
        self::assertFalse($administrator->roles()->where('is_super_admin', true)->exists());
        self::assertTrue(AdminRole::query()->where('code', 'administrator')->where('is_super_admin', true)->exists());
        self::assertTrue(AdminRole::query()->where('code', 'manager')->where('is_super_admin', false)->exists());
        self::assertTrue(Schema::hasColumns('personal_access_tokens', ['ip_address', 'user_agent']));
        self::assertTrue(Schema::hasColumns('activity_log', ['log_name', 'log_type', 'event', 'attribute_changes', 'properties', 'legacy_source', 'legacy_id']));
        self::assertFalse(Schema::hasTable('admin_login_logs'));
        self::assertFalse(Schema::hasTable('admin_audit_logs'));
        self::assertSame('default', SystemSettings::value('system.login_theme'));
        self::assertSame('panel-right', SystemSettings::value('system.login_layout'));
        self::assertTrue(SystemSettings::value('system.login_remember_me'));
        self::assertSame('安全、高效、易扩展的后台管理平台', SystemSettings::value('system.login_description'));
        self::assertSame('default', SystemSettings::value('system.admin_theme'));
        self::assertSame('light', SystemSettings::value('system.admin_theme_mode'));
        self::assertSame('sidebar-nav', SystemSettings::value('system.admin_layout'));
        self::assertTrue(SystemSettings::value('system.tabbar_enable'));
        self::assertTrue(SystemSettings::value('system.tabbar_persist'));
        self::assertSame(0, SystemSettings::value('system.tabbar_max_count'));
        self::assertSame('chrome', SystemSettings::value('system.tabbar_style_type'));
        $advancedPreferences = SystemSettings::value('system.advanced_preferences');
        self::assertIsArray($advancedPreferences);
        self::assertTrue($advancedPreferences['widget']['languageToggle']);
        self::assertTrue($advancedPreferences['breadcrumb']['showHome']);
        self::assertArrayNotHasKey('watermark', $advancedPreferences['app']);
        self::assertArrayNotHasKey('footer', $advancedPreferences);
        self::assertArrayNotHasKey('copyright', $advancedPreferences);
        self::assertArrayNotHasKey('custom', $advancedPreferences);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'configuration',
            'parent_id' => null,
            'title' => 'configuration.title',
        ]);
        self::assertDatabaseMissing('admin_permissions', ['code' => 'system.login-log.delete']);
        $systemLogsPermission = AdminPermission::query()->where('code', 'system.logs.access')->firstOrFail();
        $configurationPermission = AdminPermission::query()->where('code', 'system.configuration.access')->firstOrFail();
        self::assertDatabaseHas('admin_permissions', ['code' => 'system.login-log.view', 'parent_id' => $systemLogsPermission->getKey()]);
        self::assertDatabaseHas('admin_permissions', ['code' => 'system.audit.view', 'parent_id' => $systemLogsPermission->getKey()]);
        self::assertDatabaseHas('admin_permissions', ['code' => 'system.setting.view', 'parent_id' => $configurationPermission->getKey()]);
        self::assertDatabaseHas('admin_permissions', ['code' => 'system.theme-setting.view', 'parent_id' => $configurationPermission->getKey()]);
        self::assertDatabaseMissing('admin_permissions', ['code' => 'system.setting.update']);
        self::assertDatabaseMissing('admin_permissions', ['code' => 'system.theme-setting.update']);
        $configuration = AdminMenu::query()->where('code', 'configuration')->firstOrFail();
        self::assertDatabaseHas('admin_menus', ['code' => 'system.settings', 'parent_id' => $configuration->getKey()]);
        self::assertDatabaseHas('admin_menus', [
            'code' => 'system.logs',
            'parent_id' => null,
            'title' => 'system.logsTitle',
        ]);
        $logs = AdminMenu::query()->where('code', 'system.logs')->firstOrFail();
        self::assertDatabaseHas('admin_menus', ['code' => 'system.login-logs', 'parent_id' => $logs->getKey()]);
        self::assertDatabaseHas('admin_menus', ['code' => 'system.audit-logs', 'parent_id' => $logs->getKey()]);

        $superAdministrator->update(['password' => 'changed-super-password']);
        $administrator->update(['password' => 'changed-manager-password']);

        $this->artisan('vben-admin:install')->assertSuccessful();

        self::assertTrue(Hash::check('changed-super-password', $superAdministrator->fresh()->password));
        self::assertTrue(Hash::check('changed-manager-password', $administrator->fresh()->password));
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
