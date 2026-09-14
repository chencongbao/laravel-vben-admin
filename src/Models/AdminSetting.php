<?php

namespace Chencongbao\LaravelVbenAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = ['key', 'type', 'value', 'is_system'];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_system' => 'boolean'];
    }

    public function getTable(): string
    {
        return config('laravel-vben-admin.tables.settings', 'admin_settings');
    }
}
