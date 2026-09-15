<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class ProfileUpdateTest extends TestCase
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

    public function test_profile_update_returns_name_validation_error(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $user = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        Sanctum::actingAs($user, ['admin']);

        $this->patchJson('/api/admin/auth/profile', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_authenticated_user_without_roles_can_update_own_profile(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $user = AdminUser::query()->create([
            'username' => 'profile-user',
            'password' => 'ValidPassword123',
            'name' => 'Profile User',
            'is_active' => true,
        ]);
        self::assertFalse($user->roles()->exists());
        Sanctum::actingAs($user, ['admin']);

        $this->patchJson('/api/admin/auth/profile', ['name' => 'Updated Profile'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Profile');

        self::assertSame('Updated Profile', $user->fresh()->name);
    }
}
