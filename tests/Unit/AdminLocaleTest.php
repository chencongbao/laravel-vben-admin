<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Support\AdminLocale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminLocaleTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function test_it_maps_laravel_locales_to_vben_locales(string $laravelLocale, string $expected): void
    {
        self::assertSame($expected, AdminLocale::fromLaravel($laravelLocale));
    }

    public static function localeProvider(): array
    {
        return [
            ['zh', 'zh-CN'],
            ['zh_CN', 'zh-CN'],
            ['zh-CN', 'zh-CN'],
            ['en', 'en-US'],
            ['en_US', 'en-US'],
            ['en-US', 'en-US'],
            ['fr_FR', 'zh-CN'],
        ];
    }
}
