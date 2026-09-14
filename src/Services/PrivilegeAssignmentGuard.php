<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

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
        return $this->isSuperAdmin($actor)
            || ! AdminRole::query()->whereKey($roleIds)->where('is_super_admin', true)->exists();
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

        return collect($permissionIds)->diff($actorPermissionIds)->isEmpty()
            && ! AdminPermission::query()->whereKey($permissionIds)->where('is_sensitive', true)->exists();
    }
}
