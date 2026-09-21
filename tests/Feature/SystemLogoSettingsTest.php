<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Models\Activity;

final class SystemLogoSettingsTest extends TestCase
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

    public function test_logo_upload_is_applied_and_reset_to_the_default(): void
    {
        Storage::fake('public');
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $administrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        Sanctum::actingAs($administrator, ['admin']);

        $this->getJson('/api/admin/application')->assertOk()->assertJsonPath('logo', null);

        $response = $this->postJson('/api/admin/system/settings/logo', [
            'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
        ])->assertOk();

        $path = AdminSetting::query()->where('key', 'system.logo')->valueOrFail('value');
        self::assertIsString($path);
        self::assertStringStartsWith('laravel-vben-admin/system/logo/', $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('logo', Storage::disk('public')->url($path));
        $this->getJson('/api/admin/application')
            ->assertOk()
            ->assertJsonPath('logo', Storage::disk('public')->url($path));

        $activity = Activity::query()->where('event', 'system.settings.updated')->latest('id')->firstOrFail();
        self::assertSame($path, $activity->attribute_changes->get('settings')['system.logo']['after']);
        self::assertSame(['system.logo'], $activity->getProperty('context.keys'));

        $this->deleteJson('/api/admin/system/settings/logo')->assertOk()->assertJsonPath('logo', null);
        Storage::disk('public')->assertMissing($path);
        self::assertSame('', AdminSetting::query()->where('key', 'system.logo')->valueOrFail('value'));
        $this->getJson('/api/admin/application')->assertOk()->assertJsonPath('logo', null);
    }

    public function test_logo_requires_a_safe_local_image_and_cannot_use_the_generic_settings_endpoint(): void
    {
        Storage::fake('public');
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        Sanctum::actingAs(AdminUser::query()->where('username', 'cmsadmin')->firstOrFail(), ['admin']);

        $this->postJson('/api/admin/system/settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
        ])->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->putJson('/api/admin/system/settings', [
            'settings' => [['key' => 'system.logo', 'value' => 'https://example.com/logo.png']],
        ])->assertUnprocessable();

        self::assertFalse(AdminSetting::query()->where('key', 'system.logo')->exists());
    }

    public function test_logo_write_endpoints_require_only_the_system_setting_view_permission(): void
    {
        Storage::fake('public');
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $user = AdminUser::query()->create([
            'username' => 'logo-reader',
            'password' => Hash::make('StrongPassword123'),
            'name' => 'Logo Reader',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user, ['admin']);

        $this->postJson('/api/admin/system/settings/logo', [
            'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
        ])->assertForbidden();
        $this->deleteJson('/api/admin/system/settings/logo')->assertForbidden();

        self::assertFalse(AdminSetting::query()->where('key', 'system.logo')->exists());

        $role = AdminRole::query()->create([
            'code' => 'settings-reader',
            'name' => 'Settings Reader',
            'is_active' => true,
            'is_system' => false,
            'is_super_admin' => false,
        ]);
        $role->permissions()->attach(AdminPermission::query()->where('code', 'system.setting.view')->valueOrFail('id'));
        $user->roles()->attach($role);

        $this->postJson('/api/admin/system/settings/logo', [
            'logo' => UploadedFile::fake()->image('brand.png', 128, 128),
        ])->assertOk();
        $this->deleteJson('/api/admin/system/settings/logo')->assertOk();
    }
}
