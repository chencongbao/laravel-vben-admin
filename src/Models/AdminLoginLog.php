<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['succeeded' => 'boolean', 'created_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.login_logs', 'admin_login_logs');
    }
}
