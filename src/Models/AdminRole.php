<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'is_super_admin' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.roles', 'admin_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, config('laravel-vben-admin.tables.role_permissions', 'admin_role_permissions'), 'role_id', 'permission_id')->withTimestamps();
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, config('laravel-vben-admin.tables.role_menus', 'admin_role_menus'), 'role_id', 'menu_id')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, config('laravel-vben-admin.tables.user_roles', 'admin_user_roles'), 'role_id', 'user_id')->withTimestamps();
    }
}
