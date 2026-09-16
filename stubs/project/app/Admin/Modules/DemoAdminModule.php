<?php

namespace App\Admin\Modules;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminModule;
use Chencongbao\LaravelVbenAdmin\Definitions\MenuDefinition;
use Chencongbao\LaravelVbenAdmin\Definitions\PermissionDefinition;

final class DemoAdminModule implements AdminModule
{
    public function key(): string
    {
        return 'demo';
    }

    public function permissions(): array
    {
        return [
            new PermissionDefinition('demo.view', 'View demo'),
        ];
    }

    public function menus(): array
    {
        return [
            new MenuDefinition(
                code: 'demo',
                title: 'demo.title',
                type: 'page',
                routeName: 'Demo',
                routePath: '/demo',
                viewKey: 'demo.index',
                permissionCode: 'demo.view',
                sort: 100,
            ),
        ];
    }
}
