<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminSecurityEvent extends Model
{
    protected $fillable = ['code', 'severity', 'admin_user_id', 'username', 'ip_address', 'user_agent', 'context', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['context' => 'array', 'resolved_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.security_events', 'admin_security_events');
    }
}
