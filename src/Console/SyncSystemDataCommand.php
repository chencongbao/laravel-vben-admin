<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SyncSystemDataCommand extends Command
{
    protected $signature = 'vben-admin:sync {--dry-run : Preview changes without writing them}';

    protected $description = 'Idempotently synchronize package-owned roles, permissions and menus';

    public function handle(ModuleRegistry $registry): int
    {
        $permissions = $this->permissions($registry);
        $menus = $this->menus($registry);
        $this->table(['Type', 'Code', 'Name'], collect($permissions)->map(fn ($item) => ['permission', $item['code'], $item['name']])->concat(
            collect($menus)->map(fn ($item) => ['menu', $item['code'], $item['title']])
        )->all());

        if ($this->option('dry-run')) {
            $this->components->info('Dry run complete; no data was written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($permissions, $menus): void {
            AdminRole::query()->updateOrCreate(['code' => 'super-admin'], ['name' => 'Super Administrator', 'is_active' => true, 'is_system' => true, 'is_super_admin' => true]);

            foreach ($permissions as $permission) {
                AdminPermission::query()->updateOrCreate(['code' => $permission['code']], $permission);
            }

            foreach ($menus as $menu) {
                AdminMenu::query()->updateOrCreate(['code' => $menu['code']], $menu);
            }
        });

        $this->components->info('System data synchronized; custom data and role assignments were preserved.');

        return self::SUCCESS;
    }

    private function permissions(ModuleRegistry $registry): array
    {
        $items = [
            ['code' => 'system.access', 'name' => 'Access administration', 'is_active' => true, 'is_system' => true, 'is_sensitive' => false],
        ];

        foreach ([
            'system.user.view' => ['View administrators', false],
            'system.user.create' => ['Create administrators', true],
            'system.user.update' => ['Update administrators', true],
            'system.user.assign-roles' => ['Assign administrator roles', true],
            'system.role.view' => ['View roles', false],
            'system.role.create' => ['Create roles', true],
            'system.role.update' => ['Update roles', true],
            'system.role.delete' => ['Delete roles', true],
            'system.role.assign-access' => ['Assign role access', true],
            'system.permission.view' => ['View permissions', false],
            'system.permission.create' => ['Create permissions', true],
            'system.permission.update' => ['Update permissions', true],
            'system.permission.delete' => ['Delete permissions', true],
            'system.menu.view' => ['View menus', false],
            'system.menu.create' => ['Create menus', true],
            'system.menu.update' => ['Update menus', true],
            'system.menu.delete' => ['Delete menus', true],
            'system.audit.view' => ['View audit logs', true],
            'system.login-log.view' => ['View login logs', true],
            'system.setting.view' => ['View system settings', false],
            'system.setting.update' => ['Update system settings', true],
        ] as $code => [$name, $sensitive]) {
            $items[] = ['code' => $code, 'name' => $name, 'is_active' => true, 'is_system' => true, 'is_sensitive' => $sensitive];
        }

        foreach ($registry->all() as $module) {
            foreach ($module->permissions() as $permission) {
                $items[] = ['code' => $permission->code, 'name' => $permission->name, 'is_active' => true, 'is_system' => false, 'is_sensitive' => $permission->sensitive];
            }
        }

        return $items;
    }

    private function menus(ModuleRegistry $registry): array
    {
        $items = [
            [
                'code' => 'dashboard.workspace', 'parent_code' => null, 'title' => 'page.dashboard.workspace', 'type' => 'page',
                'route_name' => 'Workspace', 'route_path' => '/workspace', 'view_key' => 'dashboard.workspace',
                'permission_code' => null, 'icon' => 'carbon:workspace', 'sort' => -1000,
                'is_active' => true, 'is_hidden' => false, 'is_system' => true,
            ],
            [
            'code' => 'system', 'parent_code' => null, 'title' => 'System', 'type' => 'directory', 'route_name' => 'System', 'route_path' => '/system', 'view_key' => null,
            'permission_code' => 'system.access', 'icon' => 'lucide:settings', 'sort' => 1000, 'is_active' => true, 'is_hidden' => false, 'is_system' => true,
            ],
        ];

        foreach ([
            ['system.users', 'Administrators', 'SystemUsers', '/system/users', 'system.users', 'system.user.view', 10],
            ['system.roles', 'Roles', 'SystemRoles', '/system/roles', 'system.roles', 'system.role.view', 20],
            ['system.permissions', 'Permissions', 'SystemPermissions', '/system/permissions', 'system.permissions', 'system.permission.view', 30],
            ['system.menus', 'Menus', 'SystemMenus', '/system/menus', 'system.menus', 'system.menu.view', 40],
            ['system.login-logs', 'Login Logs', 'SystemLoginLogs', '/system/login-logs', 'system.login-logs', 'system.login-log.view', 80],
            ['system.audit-logs', 'Audit Logs', 'SystemAuditLogs', '/system/audit-logs', 'system.audit-logs', 'system.audit.view', 90],
            ['system.settings', 'Settings', 'SystemSettings', '/system/settings', 'system.settings', 'system.setting.view', 100],
        ] as [$code, $title, $routeName, $routePath, $viewKey, $permissionCode, $sort]) {
            $items[] = [
                'code' => $code, 'parent_code' => 'system', 'title' => $title, 'type' => 'page', 'route_name' => $routeName,
                'route_path' => $routePath, 'view_key' => $viewKey, 'permission_code' => $permissionCode, 'icon' => null,
                'sort' => $sort, 'is_active' => true, 'is_hidden' => false, 'is_system' => true,
            ];
        }

        foreach ($registry->all() as $module) {
            foreach ($module->menus() as $menu) {
                $items[] = [
                    'code' => $menu->code, 'parent_code' => $menu->parentCode, 'title' => $menu->title, 'type' => $menu->type,
                    'route_name' => $menu->routeName, 'route_path' => $menu->routePath, 'view_key' => $menu->viewKey,
                    'permission_code' => $menu->permissionCode, 'icon' => null, 'sort' => $menu->sort,
                    'is_active' => true, 'is_hidden' => false, 'is_system' => false,
                ];
            }
        }

        return $items;
    }
}
