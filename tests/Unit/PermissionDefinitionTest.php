<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Definitions\PermissionDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PermissionDefinitionTest extends TestCase
{
    public function test_it_accepts_a_stable_permission_code(): void
    {
        $permission = new PermissionDefinition('system.user.view', 'View users');

        self::assertSame('system.user.view', $permission->code);
    }

    public function test_it_rejects_an_http_path_as_a_permission_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionDefinition('GET:/api/admin/users', 'View users');
    }
}
