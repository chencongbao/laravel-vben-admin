<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $roles = config('laravel-vben-admin.tables.roles', 'admin_roles');
        $rolePermissions = config('laravel-vben-admin.tables.role_permissions', 'admin_role_permissions');
        $parentId = DB::table($permissions)->where('code', 'system.user.view')->value('id');

        DB::table($permissions)->updateOrInsert(
            ['code' => 'system.user.delete'],
            [
                'parent_id' => $parentId,
                'name' => 'Delete administrators',
                'sort' => 0,
                'is_system' => true,
                'is_deprecated' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $managerId = DB::table($roles)->where('code', 'manager')->value('id');
        $permissionId = DB::table($permissions)->where('code', 'system.user.delete')->value('id');
        if ($managerId !== null && $permissionId !== null) {
            DB::table($rolePermissions)->insertOrIgnore([
                'role_id' => $managerId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        DB::table($permissions)->where('code', 'system.user.delete')->delete();
    }
};
