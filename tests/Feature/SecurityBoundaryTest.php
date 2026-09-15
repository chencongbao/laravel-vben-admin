<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
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

    public function test_non_super_admin_cannot_assign_super_role_or_permissions_they_do_not_hold(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $superRole = AdminRole::query()->where('is_super_admin', true)->firstOrFail();
        $unheldPermission = AdminPermission::query()->create(['code' => 'external.unheld', 'name' => 'Unheld permission']);
        $guard = $this->app->make(PrivilegeAssignmentGuard::class);

        self::assertFalse($guard->canAssignRoles($actor, [$superRole->getKey()]));
        self::assertFalse($guard->canAssignPermissions($actor, [$unheldPermission->getKey()]));
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

    public function test_only_super_administrators_can_view_super_administrator_users(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $userViewPermission = AdminPermission::query()->where('code', 'system.user.view')->firstOrFail();
        $administrator->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$userViewPermission->getKey()]);

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->getJson('/api/admin/system/users?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['username' => 'cmsadmin'])
            ->assertJsonFragment(['username' => 'admin']);
        $this->getJson('/api/admin/system/users/'.$superAdministrator->getKey())
            ->assertOk()
            ->assertJsonPath('user.username', 'cmsadmin');

        Sanctum::actingAs($administrator, ['admin']);
        $this->getJson('/api/admin/system/users?per_page=100')
            ->assertOk()
            ->assertJsonMissing(['username' => 'cmsadmin'])
            ->assertJsonFragment(['username' => 'admin']);
        $this->getJson('/api/admin/system/users/'.$superAdministrator->getKey())->assertNotFound();
        $this->getJson('/api/admin/system/users/'.$administrator->getKey())
            ->assertOk()
            ->assertJsonPath('user.username', 'admin');
    }

    public function test_administrator_create_accepts_at_most_one_role(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $roleIds = AdminRole::query()->limit(2)->pluck('id')->all();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->postJson('/api/admin/system/users', [
            'name' => 'Multiple roles user',
            'password' => 'SecurePassword123',
            'role_ids' => $roleIds,
            'username' => 'multiple-roles-user',
        ])->assertUnprocessable()->assertJsonValidationErrors('role_ids');

        self::assertFalse(AdminUser::query()->where('username', 'multiple-roles-user')->exists());
    }

    public function test_default_administrator_accounts_keep_their_fixed_roles_and_usernames(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $superRole = AdminRole::query()->where('code', 'administrator')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$superAdministrator->getKey(), ['role_ids' => [$managerRole->getKey()]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'BUILTIN_ADMIN_ROLE_PROTECTED');
        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), ['role_ids' => [$superRole->getKey()]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'BUILTIN_ADMIN_ROLE_PROTECTED');
        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), ['username' => 'renamed-admin'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'BUILTIN_ADMIN_IDENTITY_PROTECTED');

        self::assertTrue($superAdministrator->roles()->whereKey($superRole->getKey())->exists());
        self::assertTrue($administrator->roles()->whereKey($managerRole->getKey())->exists());
        self::assertSame('admin', $administrator->fresh()->username);
    }

    public function test_builtin_administrator_roles_cannot_be_edited(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $superRole = AdminRole::query()->where('code', 'administrator')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $permission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $menu = AdminMenu::query()->where('code', 'system.roles')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        foreach ([$superRole, $managerRole] as $role) {
            $this->patchJson('/api/admin/system/roles/'.$role->getKey(), [
                'name' => 'Changed built-in role',
                'permission_ids' => [$permission->getKey()],
                'menu_ids' => [$menu->getKey()],
            ])->assertUnprocessable()->assertJsonPath('code', 'SYSTEM_ROLE_PROTECTED');

            $this->putJson('/api/admin/system/roles/'.$role->getKey().'/access', [
                'permission_ids' => [$permission->getKey()],
                'menu_ids' => [$menu->getKey()],
            ])->assertUnprocessable()->assertJsonPath('code', 'SYSTEM_ROLE_PROTECTED');
        }

        self::assertNotSame('Changed built-in role', $superRole->fresh()->name);
        self::assertNotSame('Changed built-in role', $managerRole->fresh()->name);
    }

    public function test_administrator_username_is_immutable_after_creation(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->create([
            'username' => 'content-operator',
            'name' => 'Content operator',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), [
            'username' => 'renamed-content-operator',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'ADMIN_USERNAME_IMMUTABLE');

        self::assertSame('content-operator', $administrator->fresh()->username);
    }

    public function test_non_super_administrator_only_sees_assigned_permissions_and_menus(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $actor = AdminUser::query()->create([
            'username' => 'limited-manager',
            'name' => 'Limited manager',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $role = AdminRole::query()->create([
            'code' => 'limited-manager',
            'name' => 'Limited manager',
            'is_active' => true,
        ]);
        $permissionView = AdminPermission::query()->where('code', 'system.permission.view')->firstOrFail();
        $menuView = AdminPermission::query()->where('code', 'system.menu.view')->firstOrFail();
        $hiddenPermission = AdminPermission::query()->where('code', 'system.user.view')->firstOrFail();
        $visibleMenu = AdminMenu::query()->where('code', 'system.menus')->firstOrFail();
        $hiddenMenu = AdminMenu::query()->where('code', 'system.users')->firstOrFail();
        $role->permissions()->sync([$permissionView->getKey(), $menuView->getKey()]);
        $role->menus()->sync([$visibleMenu->getKey()]);
        $actor->roles()->sync([$role->getKey()]);
        Sanctum::actingAs($actor, ['admin']);

        $this->getJson('/api/admin/system/permissions?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['code' => 'system.permission.view'])
            ->assertJsonFragment(['code' => 'system.menu.view'])
            ->assertJsonMissing(['code' => 'system.user.view']);
        $this->getJson('/api/admin/system/permissions/'.$hiddenPermission->getKey())->assertNotFound();

        $this->getJson('/api/admin/system/menus')
            ->assertOk()
            ->assertJsonFragment(['code' => 'system.menus'])
            ->assertJsonMissing(['code' => 'system.users']);
        $this->getJson('/api/admin/system/menus/'.$hiddenMenu->getKey())->assertNotFound();
    }

    public function test_role_menu_assignment_includes_all_ancestors(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $permission = AdminPermission::query()->where('code', 'system.user.view')->firstOrFail();
        $parentMenu = AdminMenu::query()->where('code', 'system')->firstOrFail();
        $childMenu = AdminMenu::query()->where('code', 'system.users')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $response = $this->postJson('/api/admin/system/roles', [
            'code' => 'user-viewer',
            'name' => 'User viewer',
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$childMenu->getKey()],
        ])->assertCreated();

        $role = AdminRole::query()->findOrFail($response->json('role.id'));
        self::assertEqualsCanonicalizing(
            [$parentMenu->getKey(), $childMenu->getKey()],
            $role->menus()->pluck('admin_menus.id')->all(),
        );
    }

    public function test_role_permission_assignment_includes_all_ancestors(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $parentPermission = AdminPermission::query()->where('code', 'system.configuration.access')->firstOrFail();
        $childPermission = AdminPermission::query()->where('code', 'system.setting.view')->firstOrFail();
        $menu = AdminMenu::query()->where('code', 'system.settings')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $response = $this->postJson('/api/admin/system/roles', [
            'code' => 'settings-viewer',
            'name' => 'Settings viewer',
            'permission_ids' => [$childPermission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertCreated();

        $role = AdminRole::query()->findOrFail($response->json('role.id'));
        self::assertEqualsCanonicalizing(
            [$parentPermission->getKey(), $childPermission->getKey()],
            $role->permissions()->pluck('admin_permissions.id')->all(),
        );
    }

    public function test_only_super_administrators_can_view_built_in_administrator_roles(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $roleViewPermission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $administrator->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$roleViewPermission->getKey()]);
        $customRole = AdminRole::query()->create(['code' => 'content-editor', 'name' => 'Content editor', 'is_active' => true]);
        $superRole = AdminRole::query()->where('code', 'administrator')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->getJson('/api/admin/system/roles?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['code' => 'administrator'])
            ->assertJsonFragment(['code' => 'manager'])
            ->assertJsonFragment(['code' => 'content-editor']);

        Sanctum::actingAs($administrator, ['admin']);
        $this->getJson('/api/admin/system/roles?per_page=100')
            ->assertOk()
            ->assertJsonMissing(['code' => 'administrator'])
            ->assertJsonMissing(['code' => 'manager'])
            ->assertJsonFragment(['code' => 'content-editor']);
        $this->getJson('/api/admin/system/roles/'.$superRole->getKey())->assertNotFound();
        $this->getJson('/api/admin/system/roles/'.$managerRole->getKey())->assertNotFound();
        $this->getJson('/api/admin/system/roles/'.$customRole->getKey())
            ->assertOk()
            ->assertJsonPath('role.code', 'content-editor');
    }
}
