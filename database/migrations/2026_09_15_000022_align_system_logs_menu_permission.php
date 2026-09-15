<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $permissionMenus = config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus');

        $menuId = DB::table($menus)->where('code', 'system.logs')->value('id');
        $permissionId = DB::table($permissions)->where('code', 'system.logs.access')->value('id');

        if ($menuId === null || $permissionId === null) {
            return;
        }

        $childPermissionIds = DB::table($permissions)
            ->whereIn('code', ['system.login-log.view', 'system.audit.view'])
            ->pluck('id');

        DB::table($permissionMenus)
            ->where('menu_id', $menuId)
            ->whereIn('permission_id', $childPermissionIds)
            ->delete();

        DB::table($permissionMenus)->insertOrIgnore([
            'menu_id' => $menuId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $permissionMenus = config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus');

        $menuId = DB::table($menus)->where('code', 'system.logs')->value('id');
        $permissionId = DB::table($permissions)->where('code', 'system.logs.access')->value('id');

        if ($menuId !== null && $permissionId !== null) {
            DB::table($permissionMenus)->where([
                'menu_id' => $menuId,
                'permission_id' => $permissionId,
            ])->delete();
        }
    }
};
