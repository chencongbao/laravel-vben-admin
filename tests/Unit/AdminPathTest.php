<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Support\AdminPath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminPathTest extends TestCase
{
    public function test_it_normalizes_a_valid_path(): void
    {
        self::assertSame('control-center', AdminPath::from('/control-center/'));
        self::assertSame('internal/admin', AdminPath::from('internal/admin'));
    }

    #[DataProvider('invalidPaths')]
    public function test_it_rejects_an_unsafe_or_reserved_path(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdminPath::from($path);
    }

    public static function invalidPaths(): array
    {
        return [[''], ['/'], ['../admin'], ['https://example.com/admin'], ['api'], ['api/admin'], ['admin path']];
    }
}
