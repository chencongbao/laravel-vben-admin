<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminPermission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'is_sensitive' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.permissions', 'admin_permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, config('laravel-vben-admin.tables.role_permissions', 'admin_role_permissions'), 'permission_id', 'role_id')->withTimestamps();
    }
}
