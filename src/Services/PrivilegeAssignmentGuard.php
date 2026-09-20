<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Collection;

final class PrivilegeAssignmentGuard
{
    public function isSuperAdmin(AdminUser $user): bool
    {
        return $user->roles()->where('is_active', true)->where('is_super_admin', true)->exists();
    }

    public function canManageUser(AdminUser $actor, AdminUser $target): bool
    {
        return $this->isSuperAdmin($actor) || ! $target->roles()->where('is_super_admin', true)->exists();
    }

    public function canAssignRoles(AdminUser $actor, array $roleIds): bool
    {
        if (AdminRole::query()->whereKey($roleIds)->where('is_super_admin', true)->exists()) {
            return false;
        }

        return collect($roleIds)->diff($this->assignableRoleIds($actor))->isEmpty();
    }

    public function assignableRoleIds(AdminUser $actor): Collection
    {
        $roles = AdminRole::query()
            ->where('is_active', true)
            ->where('is_super_admin', false)
            ->with(['permissions:id', 'menus:id'])
            ->get();

        if ($this->isSuperAdmin($actor)) {
            return $roles->pluck('id');
        }

        $permissionIds = $this->accessiblePermissionIds($actor) ?? collect();
        $menuIds = $this->accessibleMenuIds($actor) ?? collect();

        return $roles
            ->filter(fn (AdminRole $role) => $role->permissions->pluck('id')->diff($permissionIds)->isEmpty()
                && $role->menus->pluck('id')->diff($menuIds)->isEmpty())
            ->pluck('id')
            ->values();
    }

    public function canAssignPermissions(AdminUser $actor, array $permissionIds): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        $actorPermissionIds = $actor->roles()
            ->where('is_active', true)
            ->with('permissions:id')
            ->get()
            ->flatMap(fn (AdminRole $role) => $role->permissions->pluck('id'))
            ->unique();

        return collect($permissionIds)->diff($actorPermissionIds)->isEmpty();
    }

    public function canAssignMenus(AdminUser $actor, array $menuIds): bool
    {
        $actorMenuIds = $this->accessibleMenuIds($actor);

        return $actorMenuIds === null || collect($menuIds)->diff($actorMenuIds)->isEmpty();
    }

    public function accessiblePermissionIds(AdminUser $actor): ?Collection
    {
        if ($this->isSuperAdmin($actor)) {
            return null;
        }

        return $actor->roles()
            ->where('is_active', true)
            ->with('permissions:id')
            ->get()
            ->flatMap(fn (AdminRole $role) => $role->permissions->pluck('id'))
            ->unique()
            ->values();
    }

    public function accessibleMenuIds(AdminUser $actor): ?Collection
    {
        if ($this->isSuperAdmin($actor)) {
            return null;
        }

        return $actor->roles()
            ->where('is_active', true)
            ->with('menus:id')
            ->get()
            ->flatMap(fn (AdminRole $role) => $role->menus->pluck('id'))
            ->unique()
            ->values();
    }

    public function canAccessPermission(AdminUser $actor, AdminPermission $permission): bool
    {
        $ids = $this->accessiblePermissionIds($actor);

        return $ids === null || $ids->contains($permission->getKey());
    }

    public function canAccessMenu(AdminUser $actor, AdminMenu $menu): bool
    {
        $ids = $this->accessibleMenuIds($actor);

        return $ids === null || $ids->contains($menu->getKey());
    }
}
