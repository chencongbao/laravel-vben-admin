<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_system' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.settings', 'admin_settings');
    }
}
