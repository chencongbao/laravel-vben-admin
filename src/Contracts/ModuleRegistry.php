<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

interface ModuleRegistry
{
    public function register(AdminModule $module): void;

    /** @return array<string, AdminModule> */
    public function all(): array;
}
