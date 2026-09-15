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
            $legacyAdministrator = AdminRole::query()->where('code', 'super-admin')->first();
            if ($legacyAdministrator && ! AdminRole::query()->where('code', 'administrator')->exists()) {
                $legacyAdministrator->update(['code' => 'administrator']);
            }

            AdminRole::query()->updateOrCreate(
                ['code' => 'administrator'],
                ['name' => '超级管理员', 'is_active' => true, 'is_system' => true, 'is_super_admin' => true],
            );
            AdminRole::query()->updateOrCreate(
                ['code' => 'manager'],
                ['name' => '管理员', 'is_active' => true, 'is_system' => true, 'is_super_admin' => false],
            );

            foreach ($permissions as $permission) {
                AdminPermission::query()->updateOrCreate(['code' => $permission['code']], $permission);
            }

            foreach ($this->permissionParents() as $code => $parentCode) {
                $parentId = AdminPermission::query()->where('code', $parentCode)->value('id');
                AdminPermission::query()->where('code', $code)->update(['parent_id' => $parentId]);
            }

            foreach ($menus as $menu) {
                AdminMenu::query()->updateOrCreate(
                    ['code' => $menu['code']],
                    collect($menu)->except(['parent_menu_code', 'permission_code'])->all(),
                );
            }

            foreach ($menus as $menu) {
                $parentId = $menu['parent_menu_code'] === null
                    ? null
                    : AdminMenu::query()->where('code', $menu['parent_menu_code'])->value('id');
                AdminMenu::query()->where('code', $menu['code'])->update(['parent_id' => $parentId]);
            }

            foreach ($menus as $menuData) {
                if ($menuData['permission_code'] !== null) {
                    $menu = AdminMenu::query()->where('code', $menuData['code'])->firstOrFail();
                    $permission = AdminPermission::query()->where('code', $menuData['permission_code'])->first();
                    if ($permission !== null) {
                        $menu->permissions()->syncWithoutDetaching([$permission->getKey()]);
                    }
                }
            }

            foreach ([
                'configuration' => ['system.settings', 'system.theme-settings'],
                'system.logs' => ['system.login-logs', 'system.audit-logs'],
            ] as $parentCode => $childCodes) {
                $parentMenu = AdminMenu::query()->where('code', $parentCode)->firstOrFail();
                AdminRole::query()
                    ->whereHas('menus', fn ($query) => $query->whereIn('code', $childCodes))
                    ->each(fn (AdminRole $role) => $role->menus()->syncWithoutDetaching([$parentMenu->getKey()]));
            }
        });

        $this->components->info('System data synchronized; custom data and role assignments were preserved.');

        return self::SUCCESS;
    }

    private function permissions(ModuleRegistry $registry): array
    {
        $items = [
            ['code' => 'system.access', 'name' => 'Access administration', 'is_system' => true],
            ['code' => 'system.logs.access', 'name' => 'System logs', 'is_system' => true],
            ['code' => 'system.configuration.access', 'name' => 'Configuration administration', 'is_system' => true],
        ];

        foreach ([
            'system.user.view' => 'View administrators',
            'system.user.create' => 'Create administrators',
            'system.user.update' => 'Update administrators',
            'system.role.view' => 'View roles',
            'system.role.create' => 'Create roles',
            'system.role.update' => 'Update roles',
            'system.role.delete' => 'Delete roles',
            'system.permission.view' => 'View permissions',
            'system.permission.create' => 'Create permissions',
            'system.permission.update' => 'Update permissions',
            'system.permission.delete' => 'Delete permissions',
            'system.menu.view' => 'View menus',
            'system.menu.create' => 'Create menus',
            'system.menu.update' => 'Update menus',
            'system.menu.delete' => 'Delete menus',
            'system.audit.view' => 'View audit logs',
            'system.login-log.view' => 'View login logs',
            'system.setting.view' => 'View system settings',
            'system.theme-setting.view' => 'View theme settings',
        ] as $code => $name) {
            $items[] = ['code' => $code, 'name' => $name, 'is_system' => true];
        }

        foreach ($registry->all() as $module) {
            foreach ($module->permissions() as $permission) {
                $items[] = ['code' => $permission->code, 'name' => $permission->name, 'is_system' => false];
            }
        }

        return $items;
    }

    /** @return array<string, string> */
    private function permissionParents(): array
    {
        return [
            'system.user.view' => 'system.access',
            'system.user.create' => 'system.user.view',
            'system.user.update' => 'system.user.view',
            'system.role.view' => 'system.access',
            'system.role.create' => 'system.role.view',
            'system.role.update' => 'system.role.view',
            'system.role.delete' => 'system.role.view',
            'system.permission.view' => 'system.access',
            'system.permission.create' => 'system.permission.view',
            'system.permission.update' => 'system.permission.view',
            'system.permission.delete' => 'system.permission.view',
            'system.menu.view' => 'system.access',
            'system.menu.create' => 'system.menu.view',
            'system.menu.update' => 'system.menu.view',
            'system.menu.delete' => 'system.menu.view',
            'system.audit.view' => 'system.logs.access',
            'system.login-log.view' => 'system.logs.access',
            'system.setting.view' => 'system.configuration.access',
            'system.theme-setting.view' => 'system.configuration.access',
        ];
    }

    private function menus(ModuleRegistry $registry): array
    {
        $items = [
            [
                'code' => 'dashboard.workspace', 'parent_menu_code' => null, 'title' => 'dashboard.workspace', 'type' => 'page',
                'route_name' => 'Workspace', 'route_path' => '/workspace', 'view_key' => 'dashboard.workspace',
                'permission_code' => null, 'icon' => 'carbon:workspace', 'sort' => -1000,
                'is_system' => true,
            ],
            [
                'code' => 'system', 'parent_menu_code' => null, 'title' => 'system.title', 'type' => 'directory', 'route_name' => 'System', 'route_path' => '/system', 'view_key' => null,
                'permission_code' => 'system.access', 'icon' => 'lucide:settings', 'sort' => 1000, 'is_system' => true,
            ],
            [
                'code' => 'configuration', 'parent_menu_code' => null, 'title' => 'configuration.title', 'type' => 'directory', 'route_name' => 'Configuration', 'route_path' => '/configuration', 'view_key' => null,
                'permission_code' => 'system.configuration.access', 'icon' => 'lucide:sliders-horizontal', 'sort' => 3000, 'is_system' => true,
            ],
            [
                'code' => 'system.logs', 'parent_menu_code' => null, 'title' => 'system.logsTitle', 'type' => 'directory', 'route_name' => 'SystemLogs', 'route_path' => '/system-logs', 'view_key' => null,
                'permission_code' => 'system.logs.access', 'icon' => 'lucide:notebook-tabs', 'sort' => 2000, 'is_system' => true,
            ],
        ];

        foreach ([
            ['system.users', 'system.administrators', 'SystemUsers', '/system/users', 'system.users', 'system.user.view', 'lucide:users', 10],
            ['system.roles', 'system.roles', 'SystemRoles', '/system/roles', 'system.roles', 'system.role.view', 'lucide:user-cog', 20],
            ['system.permissions', 'system.permissions', 'SystemPermissions', '/system/permissions', 'system.permissions', 'system.permission.view', 'lucide:shield-check', 30],
            ['system.menus', 'system.menus', 'SystemMenus', '/system/menus', 'system.menus', 'system.menu.view', 'lucide:list-tree', 40],
        ] as [$code, $title, $routeName, $routePath, $viewKey, $permissionCode, $icon, $sort]) {
            $items[] = [
                'code' => $code, 'parent_menu_code' => 'system', 'title' => $title, 'type' => 'page', 'route_name' => $routeName,
                'route_path' => $routePath, 'view_key' => $viewKey, 'permission_code' => $permissionCode, 'icon' => $icon,
                'sort' => $sort, 'is_system' => true,
            ];
        }

        $items[] = [
            'code' => 'system.settings', 'parent_menu_code' => 'configuration', 'title' => 'system.settings', 'type' => 'page',
            'route_name' => 'SystemSettings', 'route_path' => '/system/settings', 'view_key' => 'system.settings',
            'permission_code' => 'system.setting.view', 'icon' => 'lucide:settings-2', 'sort' => 10,
            'is_system' => true,
        ];

        $items[] = [
            'code' => 'system.theme-settings', 'parent_menu_code' => 'configuration', 'title' => 'configuration.themeSettings', 'type' => 'page',
            'route_name' => 'ThemeSettings', 'route_path' => '/configuration/theme', 'view_key' => 'system.theme-settings',
            'permission_code' => 'system.theme-setting.view', 'icon' => 'lucide:palette', 'sort' => 20,
            'is_system' => true,
        ];

        foreach ([
            ['system.login-logs', 'system.loginLogs', 'SystemLoginLogs', '/system/login-logs', 'system.login-logs', 'system.login-log.view', 'lucide:log-in', 10],
            ['system.audit-logs', 'system.auditLogs', 'SystemAuditLogs', '/system/audit-logs', 'system.audit-logs', 'system.audit.view', 'lucide:clipboard-list', 20],
        ] as [$code, $title, $routeName, $routePath, $viewKey, $permissionCode, $icon, $sort]) {
            $items[] = [
                'code' => $code, 'parent_menu_code' => 'system.logs', 'title' => $title, 'type' => 'page', 'route_name' => $routeName,
                'route_path' => $routePath, 'view_key' => $viewKey, 'permission_code' => $permissionCode, 'icon' => $icon,
                'sort' => $sort, 'is_system' => true,
            ];
        }

        foreach ($registry->all() as $module) {
            foreach ($module->menus() as $menu) {
                $items[] = [
                    'code' => $menu->code, 'parent_menu_code' => $menu->parentCode, 'title' => $menu->title, 'type' => $menu->type,
                    'route_name' => $menu->routeName, 'route_path' => $menu->routePath, 'view_key' => $menu->viewKey,
                    'permission_code' => $menu->permissionCode, 'icon' => null, 'sort' => $menu->sort,
                    'is_system' => false,
                ];
            }
        }

        return $items;
    }
}
