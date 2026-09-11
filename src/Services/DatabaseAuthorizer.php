<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

final class DatabaseAuthorizer implements Authorizer
{
    public function allows(AdminUser $user, string $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->roles()
            ->where('is_active', true)
            ->where(function ($query) use ($permission): void {
                $query->where('is_super_admin', true)
                    ->orWhereHas('permissions', fn ($permissions) => $permissions
                        ->where('code', $permission)
                        ->where('is_active', true));
            })
            ->exists();
    }
}
