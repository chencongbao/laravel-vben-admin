<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Definitions\MenuDefinition;
use Chencongbao\LaravelVbenAdmin\Definitions\PermissionDefinition;

interface AdminModule
{
    public function key(): string;

    /** @return list<PermissionDefinition> */
    public function permissions(): array;

    /** @return list<MenuDefinition> */
    public function menus(): array;
}
