<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminMenu extends Model
{
    protected $fillable = ['code', 'parent_id', 'title', 'type', 'route_name', 'route_path', 'view_key', 'permission_code', 'icon', 'sort', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.menus', 'admin_menus');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, config('laravel-vben-admin.tables.role_menus', 'admin_role_menus'), 'menu_id', 'role_id')->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus'), 'menu_id', 'permission_id')->withTimestamps();
    }
}
