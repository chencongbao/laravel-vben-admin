<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminMenu extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_hidden' => 'boolean', 'is_system' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.menus', 'admin_menus');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, config('laravel-vben-admin.tables.role_menus', 'admin_role_menus'), 'menu_id', 'role_id')->withTimestamps();
    }
}
