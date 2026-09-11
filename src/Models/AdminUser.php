<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.users', 'admin_users');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, config('laravel-vben-admin.tables.user_roles', 'admin_user_roles'), 'user_id', 'role_id')->withTimestamps();
    }
}
