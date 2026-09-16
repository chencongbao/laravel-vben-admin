<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminLoginIpBlock extends Model
{
    protected $fillable = ['ip_address', 'reason_code', 'is_automatic', 'expires_at', 'released_at', 'created_by', 'released_by'];

    protected function casts(): array
    {
        return ['is_automatic' => 'boolean', 'expires_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.login_ip_blocks', 'admin_login_ip_blocks');
    }
}
