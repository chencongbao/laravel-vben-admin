<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $permissionMenus = config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus');
        $now = now();

        DB::table($menus)->whereNotNull('permission_code')->orderBy('id')->each(function (object $menu) use ($now, $permissions, $permissionMenus): void {
            $permissionId = DB::table($permissions)->where('code', $menu->permission_code)->value('id');
            if ($permissionId !== null) {
                DB::table($permissionMenus)->insertOrIgnore(['menu_id' => $menu->id, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
            }
        });

        Schema::table($menus, function (Blueprint $table): void {
            $table->dropIndex(['permission_code']);
            $table->dropColumn('permission_code');
        });
    }

    public function down(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $permissionMenus = config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus');

        Schema::table($menus, fn (Blueprint $table) => $table->string('permission_code', 160)->nullable()->index());
        DB::table($menus)->orderBy('id')->each(function (object $menu) use ($menus, $permissions, $permissionMenus): void {
            $code = DB::table($permissionMenus)->join($permissions, $permissions.'.id', '=', $permissionMenus.'.permission_id')->where($permissionMenus.'.menu_id', $menu->id)->orderByRaw("CASE WHEN {$permissions}.code LIKE '%.view' THEN 0 ELSE 1 END")->value($permissions.'.code');
            DB::table($menus)->where('id', $menu->id)->update(['permission_code' => $code]);
        });
    }
};
