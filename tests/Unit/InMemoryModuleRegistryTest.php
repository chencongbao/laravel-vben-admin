<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminModule;
use Chencongbao\LaravelVbenAdmin\Services\InMemoryModuleRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

final class InMemoryModuleRegistryTest extends TestCase
{
    public function test_it_rejects_duplicate_module_keys(): void
    {
        $module = new class implements AdminModule
        {
            public function key(): string { return 'matches'; }
            public function permissions(): array { return []; }
            public function menus(): array { return []; }
        };

        $registry = new InMemoryModuleRegistry();
        $registry->register($module);

        $this->expectException(LogicException::class);
        $registry->register($module);
    }
}
