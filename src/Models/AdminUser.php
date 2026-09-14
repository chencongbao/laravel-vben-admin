<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['username', 'password', 'name', 'avatar', 'is_active', 'last_login_at', 'last_login_ip', 'remember_token', 'login_ip_whitelist', 'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'login_ip_whitelist' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
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
