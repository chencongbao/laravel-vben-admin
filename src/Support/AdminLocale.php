<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

final class AdminLocale
{
    public const array SUPPORTED = ['zh-CN', 'en-US'];

    public static function fromLaravel(?string $locale = null): string
    {
        $normalized = strtolower(str_replace('_', '-', $locale ?? (string) config('app.locale', 'zh_CN')));

        return match (true) {
            $normalized === 'en', str_starts_with($normalized, 'en-') => 'en-US',
            $normalized === 'zh', str_starts_with($normalized, 'zh-') => 'zh-CN',
            default => 'zh-CN',
        };
    }
}
