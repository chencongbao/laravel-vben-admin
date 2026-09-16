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
use Spatie\Activitylog\Models\Activity;

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

    public function test_administrator_can_update_own_profile_but_cannot_change_own_status_or_role(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($administrator, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), [
            'name' => 'Updated administrator',
        ])->assertOk()->assertJsonPath('user.name', 'Updated administrator');

        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), [
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonPath('code', 'ADMIN_SELF_STATUS_CHANGE_DENIED');

        self::assertTrue($administrator->fresh()->is_active);

        $selfManagedRole = AdminRole::query()->create([
            'code' => 'self-managed',
            'name' => 'Self managed',
            'is_active' => true,
        ]);
        $alternativeRole = AdminRole::query()->create([
            'code' => 'alternative',
            'name' => 'Alternative',
            'is_active' => true,
        ]);
        $selfManagedRole->permissions()->sync([
            AdminPermission::query()->where('code', 'system.user.update')->valueOrFail('id'),
        ]);
        $selfManagedUser = AdminUser::query()->create([
            'username' => 'self-managed-user',
            'name' => 'Self managed user',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $selfManagedUser->roles()->sync([$selfManagedRole->getKey()]);
        Sanctum::actingAs($selfManagedUser, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$selfManagedUser->getKey(), [
            'role_ids' => [$alternativeRole->getKey()],
        ])->assertUnprocessable()->assertJsonPath('code', 'ADMIN_SELF_ROLE_CHANGE_DENIED');

        self::assertTrue($selfManagedUser->roles()->whereKey($selfManagedRole->getKey())->exists());
    }

    public function test_super_administrator_can_change_the_builtin_manager_status_without_changing_its_fixed_role(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->patchJson('/api/admin/system/users/'.$administrator->getKey(), [
            'is_active' => false,
            'name' => 'Disabled administrator',
        ])->assertOk()
            ->assertJsonPath('user.is_active', false)
            ->assertJsonPath('user.name', 'Disabled administrator');

        self::assertFalse($administrator->fresh()->is_active);
        self::assertEqualsCanonicalizing(
            [$managerRole->getKey()],
            $administrator->roles()->pluck('admin_roles.id')->all(),
        );
    }

    public function test_user_role_options_include_every_active_non_super_role_and_creation_rejects_super_role(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $superRole = AdminRole::query()->where('code', 'administrator')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $customRole = AdminRole::query()->create([
            'code' => 'user-role-option',
            'name' => 'User role option',
            'is_active' => true,
        ]);
        $disabledRole = AdminRole::query()->create([
            'code' => 'disabled-user-role-option',
            'name' => 'Disabled user role option',
            'is_active' => false,
        ]);

        Sanctum::actingAs($administrator, ['admin']);
        $response = $this->getJson('/api/admin/system/users/role-options')->assertOk();
        $roleIds = collect($response->json('roles'))->pluck('id')->all();
        self::assertContains($managerRole->getKey(), $roleIds);
        self::assertContains($customRole->getKey(), $roleIds);
        self::assertNotContains($superRole->getKey(), $roleIds);
        self::assertNotContains($disabledRole->getKey(), $roleIds);

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->postJson('/api/admin/system/users', [
            'name' => 'Rejected super administrator',
            'password' => 'SecurePassword123',
            'role_ids' => [$superRole->getKey()],
            'username' => 'rejected-super-administrator',
        ])->assertUnprocessable()->assertJsonPath('code', 'ADMIN_SUPER_ROLE_ASSIGNMENT_DENIED');

        self::assertFalse(AdminUser::query()->where('username', 'rejected-super-administrator')->exists());
    }

    public function test_only_fixed_accounts_are_delete_protected(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $ordinaryRole = AdminRole::query()->create([
            'code' => 'deletable-user-role',
            'name' => 'Deletable user role',
            'is_active' => true,
        ]);
        $managerTarget = AdminUser::query()->create([
            'username' => 'additional-manager',
            'name' => 'Additional manager',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $managerTarget->roles()->sync([AdminRole::query()->where('code', 'manager')->valueOrFail('id')]);
        $administratorTarget = AdminUser::query()->create([
            'username' => 'additional-super-administrator',
            'name' => 'Additional super administrator',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $administratorTarget->roles()->sync([AdminRole::query()->where('code', 'administrator')->valueOrFail('id')]);
        $ordinaryUserForManager = AdminUser::query()->create([
            'username' => 'ordinary-user-for-manager',
            'name' => 'Ordinary user for manager',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $ordinaryUserForManager->roles()->sync([$ordinaryRole->getKey()]);
        $ordinaryUserForSuper = AdminUser::query()->create([
            'username' => 'ordinary-user-for-super',
            'name' => 'Ordinary user for super',
            'password' => 'SecurePassword123',
            'is_active' => true,
        ]);
        $ordinaryUserForSuper->roles()->sync([$ordinaryRole->getKey()]);

        Sanctum::actingAs($administrator, ['admin']);
        $this->deleteJson('/api/admin/system/users/'.$ordinaryUserForManager->getKey())->assertNoContent();
        $this->deleteJson('/api/admin/system/users/'.$managerTarget->getKey())->assertNoContent();
        $this->deleteJson('/api/admin/system/users/'.$administratorTarget->getKey())
            ->assertForbidden()
            ->assertJsonPath('code', 'ADMIN_PRIVILEGE_ESCALATION_DENIED');

        Sanctum::actingAs($superAdministrator, ['admin']);
        $this->deleteJson('/api/admin/system/users/'.$ordinaryUserForSuper->getKey())->assertNoContent();
        $this->deleteJson('/api/admin/system/users/'.$administratorTarget->getKey())->assertNoContent();
        $this->deleteJson('/api/admin/system/users/'.$administrator->getKey())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PROTECTED_ADMIN_USER_DELETE_DENIED');
        $this->deleteJson('/api/admin/system/users/'.$superAdministrator->getKey())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PROTECTED_ADMIN_USER_DELETE_DENIED');

        self::assertFalse(AdminUser::query()->whereKey($ordinaryUserForManager->getKey())->exists());
        self::assertFalse(AdminUser::query()->whereKey($ordinaryUserForSuper->getKey())->exists());
        self::assertFalse(AdminUser::query()->whereKey($managerTarget->getKey())->exists());
        self::assertFalse(AdminUser::query()->whereKey($administratorTarget->getKey())->exists());
        self::assertSame(4, Activity::query()->where('event', 'system.user.deleted')->count());
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

    public function test_super_administrator_role_is_immutable_and_manager_access_is_editable(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $superRole = AdminRole::query()->where('code', 'administrator')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $permission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $menu = AdminMenu::query()->where('code', 'system.roles')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->patchJson('/api/admin/system/roles/'.$superRole->getKey(), [
            'name' => 'Changed built-in role',
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertUnprocessable()->assertJsonPath('code', 'SYSTEM_ROLE_PROTECTED');

        $this->putJson('/api/admin/system/roles/'.$superRole->getKey().'/access', [
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertUnprocessable()->assertJsonPath('code', 'SYSTEM_ROLE_PROTECTED');

        $this->patchJson('/api/admin/system/roles/'.$managerRole->getKey(), [
            'code' => $managerRole->code,
            'name' => $managerRole->name,
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertOk();

        self::assertNotSame('Changed built-in role', $superRole->fresh()->name);
        self::assertTrue($managerRole->permissions()->whereKey($permission->getKey())->exists());
        self::assertTrue($managerRole->menus()->whereKey($menu->getKey())->exists());
    }

    public function test_super_administrator_can_assign_menu_and_permission_management_to_manager(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $superAdministrator = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $protectedPermission = AdminPermission::query()->where('code', 'system.menu.view')->firstOrFail();
        $protectedMenu = AdminMenu::query()->where('code', 'system.menus')->firstOrFail();
        Sanctum::actingAs($superAdministrator, ['admin']);

        $this->putJson('/api/admin/system/roles/'.$managerRole->getKey().'/access', [
            'permission_ids' => [$protectedPermission->getKey()],
            'menu_ids' => [$protectedMenu->getKey()],
        ])->assertOk();

        self::assertTrue($managerRole->permissions()->whereKey($protectedPermission->getKey())->exists());
        self::assertTrue($managerRole->menus()->whereKey($protectedMenu->getKey())->exists());
    }

    public function test_only_super_administrator_can_modify_manager_role(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $managerRole = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $permission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $menu = AdminMenu::query()->where('code', 'system.roles')->firstOrFail();
        Sanctum::actingAs($administrator, ['admin']);

        $this->patchJson('/api/admin/system/roles/'.$managerRole->getKey(), [
            'code' => $managerRole->code,
            'name' => $managerRole->name,
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertForbidden()->assertJsonPath('code', 'ADMIN_SUPER_ADMIN_REQUIRED');

        $this->putJson('/api/admin/system/roles/'.$managerRole->getKey().'/access', [
            'permission_ids' => [$permission->getKey()],
            'menu_ids' => [$menu->getKey()],
        ])->assertForbidden()->assertJsonPath('code', 'ADMIN_SUPER_ADMIN_REQUIRED');
    }

    public function test_manager_can_create_and_update_custom_roles_within_their_own_access_scope(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $rolePermission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $roleMenu = AdminMenu::query()->where('code', 'system.roles')->firstOrFail();
        Sanctum::actingAs($administrator, ['admin']);

        $response = $this->postJson('/api/admin/system/roles', [
            'code' => 'content-editor',
            'name' => 'Content editor',
            'permission_ids' => [$rolePermission->getKey()],
            'menu_ids' => [$roleMenu->getKey()],
        ])->assertCreated()
            ->assertJsonPath('role.code', 'content-editor');

        $role = AdminRole::query()->findOrFail($response->json('role.id'));
        self::assertFalse($role->is_system);
        self::assertFalse($role->is_super_admin);

        $this->patchJson('/api/admin/system/roles/'.$role->getKey(), [
            'code' => 'content-editor',
            'name' => 'Senior content editor',
            'permission_ids' => [$rolePermission->getKey()],
            'menu_ids' => [$roleMenu->getKey()],
        ])->assertOk()
            ->assertJsonPath('role.name', 'Senior content editor');

        self::assertSame('Senior content editor', $role->fresh()->name);
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

    public function test_manager_can_load_role_access_options_without_menu_or_permission_management_access(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();
        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $managerRole = $administrator->roles()->firstOrFail();
        $roleViewPermission = AdminPermission::query()->where('code', 'system.role.view')->firstOrFail();
        $menuManagementPermission = AdminPermission::query()->where('code', 'system.menu.view')->firstOrFail();
        $permissionManagementPermission = AdminPermission::query()->where('code', 'system.permission.view')->firstOrFail();
        $managerRole->permissions()->syncWithoutDetaching([$roleViewPermission->getKey()]);
        $managerRole->permissions()->detach([$menuManagementPermission->getKey(), $permissionManagementPermission->getKey()]);
        $expectedPermissionIds = $managerRole->permissions()->pluck('admin_permissions.id')->sort()->values()->all();
        $expectedMenuIds = $managerRole->menus()
            ->where('code', '!=', 'dashboard.workspace')
            ->pluck('admin_menus.id')
            ->sort()
            ->values()
            ->all();
        Sanctum::actingAs($administrator, ['admin']);

        $this->getJson('/api/admin/system/permissions?per_page=100')
            ->assertForbidden()
            ->assertJsonPath('code', 'ADMIN_PERMISSION_DENIED');
        $this->getJson('/api/admin/system/menus')
            ->assertForbidden()
            ->assertJsonPath('code', 'ADMIN_PERMISSION_DENIED');

        $response = $this->getJson('/api/admin/system/roles/access-options')->assertOk();

        self::assertSame(
            $expectedPermissionIds,
            collect($response->json('permissions'))->pluck('id')->sort()->values()->all(),
        );
        self::assertSame(
            $expectedMenuIds,
            collect($response->json('menus'))->pluck('id')->sort()->values()->all(),
        );
    }
}
