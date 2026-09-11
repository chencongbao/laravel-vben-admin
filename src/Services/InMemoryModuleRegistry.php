<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminModule;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use LogicException;

final class InMemoryModuleRegistry implements ModuleRegistry
{
    /** @var array<string, AdminModule> */
    private array $modules = [];

    public function register(AdminModule $module): void
    {
        if (isset($this->modules[$module->key()])) {
            throw new LogicException("Admin module [{$module->key()}] is already registered.");
        }

        $this->modules[$module->key()] = $module;
    }

    public function all(): array
    {
        return $this->modules;
    }
}
