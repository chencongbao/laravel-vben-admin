<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

use InvalidArgumentException;

final class AdminPath
{
    public static function value(): string
    {
        return self::from((string) config('laravel-vben-admin.path', 'admin'));
    }

    public static function from(string $configuredPath): string
    {
        $path = trim($configuredPath, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, '://')) {
            throw new InvalidArgumentException('VBEN_ADMIN_PATH must be a safe relative path.');
        }

        if (! preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $path)) {
            throw new InvalidArgumentException('VBEN_ADMIN_PATH may contain only letters, numbers, dashes, underscores and slashes.');
        }

        if ($path === 'api' || str_starts_with($path, 'api/')) {
            throw new InvalidArgumentException('VBEN_ADMIN_PATH cannot use the reserved API path.');
        }

        return $path;
    }

    public static function publicDirectory(): string
    {
        return public_path(self::value());
    }
}
