<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemPermissionLocaleTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function test_builtin_system_permissions_have_localized_names(string $locale): void
    {
        $path = dirname(__DIR__, 2)."/frontend/apps/web-antd/src/locales/langs/{$locale}/system.json";
        $messages = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'system.setting.update',
            'system.theme-setting.update',
        ] as $permission) {
            $value = $messages['permissionNames'];

            foreach (explode('.', $permission) as $segment) {
                self::assertIsArray($value, "Missing locale key: system.permissionNames.{$permission}");
                self::assertArrayHasKey($segment, $value, "Missing locale key: system.permissionNames.{$permission}");
                $value = $value[$segment];
            }

            self::assertIsString($value);
            self::assertNotSame('', trim($value));
        }
    }

    public static function localeProvider(): array
    {
        return [
            ['en-US'],
            ['zh-CN'],
        ];
    }
}
