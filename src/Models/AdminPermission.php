<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminPermission extends Model
{
    protected $fillable = ['parent_id', 'code', 'name', 'http_methods', 'http_paths', 'sort', 'is_active', 'is_system', 'is_sensitive', 'is_deprecated'];

    protected function casts(): array
    {
        return [
            'http_methods' => 'array',
            'http_paths' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_deprecated' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.permissions', 'admin_permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, config('laravel-vben-admin.tables.role_permissions', 'admin_role_permissions'), 'permission_id', 'role_id')->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus'), 'permission_id', 'menu_id')->withTimestamps();
    }
}
