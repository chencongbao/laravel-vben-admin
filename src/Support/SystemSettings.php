<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Throwable;

final class SystemSettings
{
    public static function definitions(): array
    {
        return [
            'system.name' => [
                'type' => 'string',
                'default' => (string) config('app.name', 'Laravel Vben Admin'),
            ],
            'system.page_size' => [
                'type' => 'integer',
                'default' => 20,
                'min' => 10,
                'max' => 100,
            ],
        ];
    }

    public static function value(string $key): mixed
    {
        $definition = self::definitions()[$key] ?? null;

        if ($definition === null) {
            return null;
        }

        try {
            $setting = AdminSetting::query()->where('key', $key)->first();
        } catch (Throwable) {
            return $definition['default'];
        }

        return $setting?->value ?? $definition['default'];
    }
}
