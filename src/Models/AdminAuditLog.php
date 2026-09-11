<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array', 'context' => 'array', 'created_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.audit_logs', 'admin_audit_logs');
    }
}
