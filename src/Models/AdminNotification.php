<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminNotification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.notifications', 'admin_notifications');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
