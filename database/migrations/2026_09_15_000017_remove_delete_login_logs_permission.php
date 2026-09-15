<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        DB::table($table)->where('code', 'system.login-log.delete')->delete();
    }

    public function down(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $parentId = DB::table($table)->where('code', 'system.login-log.view')->value('id');

        DB::table($table)->updateOrInsert(
            ['code' => 'system.login-log.delete'],
            [
                'parent_id' => $parentId,
                'name' => 'Delete login logs',
                'is_active' => true,
                'is_system' => true,
                'is_sensitive' => true,
                'sort' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
};
