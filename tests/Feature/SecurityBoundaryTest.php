<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\PrivilegeAssignmentGuard;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class SecurityBoundaryTest extends TestCase
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
    }

    public function test_admin_models_discard_unknown_mass_assignment_fields(): void
    {
        $user = new AdminUser(['username' => 'safe-user', 'name' => 'Safe User', 'unexpected_admin_flag' => true]);

        self::assertSame('safe-user', $user->username);
        self::assertArrayNotHasKey('unexpected_admin_flag', $user->getAttributes());
    }

    public function test_non_super_admin_cannot_assign_super_role_or_sensitive_permissions(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $superRole = AdminRole::query()->where('is_super_admin', true)->firstOrFail();
        $sensitivePermission = AdminPermission::query()->where('is_sensitive', true)->firstOrFail();
        $guard = $this->app->make(PrivilegeAssignmentGuard::class);

        self::assertFalse($guard->canAssignRoles($actor, [$superRole->getKey()]));
        self::assertFalse($guard->canAssignPermissions($actor, [$sensitivePermission->getKey()]));
    }

    public function test_non_super_admin_cannot_modify_a_super_admin_even_with_update_permission(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $target = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $role = $actor->roles()->firstOrFail();
        $role->permissions()->syncWithoutDetaching(AdminPermission::query()->where('code', 'system.user.update')->valueOrFail('id'));
        Sanctum::actingAs($actor, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$target->getKey(), ['name' => 'Tampered'])
            ->assertForbidden()
            ->assertJsonPath('code', 'ADMIN_PRIVILEGE_ESCALATION_DENIED');

        self::assertNotSame('Tampered', $target->fresh()->name);
    }
}
