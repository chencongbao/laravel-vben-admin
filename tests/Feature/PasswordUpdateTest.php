<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class PasswordUpdateTest extends TestCase
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

    public function test_password_update_returns_field_errors_for_invalid_input(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $user = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        Sanctum::actingAs($user, ['admin']);

        $this->putJson('/api/admin/auth/password', [
            'current_password' => '',
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_password_update_returns_stable_code_for_incorrect_current_password(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $user = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        Sanctum::actingAs($user, ['admin']);

        $this->putJson('/api/admin/auth/password', [
            'current_password' => 'incorrect-password',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'ValidPassword123',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'CURRENT_PASSWORD_INCORRECT')
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_weak_password_policy_accepts_six_characters_and_is_exposed_to_authenticated_users(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        AdminSetting::query()->create([
            'key' => 'system.password_strength',
            'type' => 'enum',
            'value' => 'weak',
            'is_system' => true,
        ]);
        $user = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $user->forceFill(['password' => Hash::make('CurrentPassword123')])->save();
        Sanctum::actingAs($user, ['admin']);

        $this->getJson('/api/admin/auth/password-policy')
            ->assertOk()
            ->assertJsonPath('password_policy.type', 'weak')
            ->assertJsonPath('password_policy.min_length', 6)
            ->assertJsonPath('password_policy.requires_mixed_case', false)
            ->assertJsonPath('password_policy.requires_numbers', false);

        $this->putJson('/api/admin/auth/password', [
            'current_password' => 'CurrentPassword123',
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertOk();

        self::assertTrue(Hash::check('123456', $user->fresh()->password));
    }
}
