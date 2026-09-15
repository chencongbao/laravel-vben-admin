<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $configurationId = DB::table($permissions)->where('code', 'system.configuration.access')->value('id');

        DB::table($permissions)->updateOrInsert(
            ['code' => 'system.theme-setting.view'],
            [
                'parent_id' => $configurationId,
                'name' => 'View theme settings',
                'is_active' => true,
                'is_system' => true,
                'is_sensitive' => false,
                'sort' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $viewId = DB::table($permissions)->where('code', 'system.theme-setting.view')->value('id');
        DB::table($permissions)->updateOrInsert(
            ['code' => 'system.theme-setting.update'],
            [
                'parent_id' => $viewId,
                'name' => 'Update theme settings',
                'is_active' => true,
                'is_system' => true,
                'is_sensitive' => true,
                'sort' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table($menus)->where('code', 'configuration')->update(['permission_code' => null]);
        DB::table($menus)->where('code', 'system.theme-settings')->update(['permission_code' => 'system.theme-setting.view']);
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        DB::table($menus)->where('code', 'configuration')->update(['permission_code' => 'system.setting.view']);
        DB::table($menus)->where('code', 'system.theme-settings')->update(['permission_code' => null]);
        DB::table($permissions)->whereIn('code', ['system.theme-setting.update', 'system.theme-setting.view'])->delete();
    }
};
