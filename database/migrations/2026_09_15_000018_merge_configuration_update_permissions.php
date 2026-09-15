<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        DB::table($table)->whereIn('code', [
            'system.setting.update',
            'system.theme-setting.update',
        ])->delete();
    }

    public function down(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        foreach ([
            'system.setting.update' => ['system.setting.view', 'Update system settings'],
            'system.theme-setting.update' => ['system.theme-setting.view', 'Update theme settings'],
        ] as $code => [$parentCode, $name]) {
            DB::table($table)->updateOrInsert(
                ['code' => $code],
                [
                    'parent_id' => DB::table($table)->where('code', $parentCode)->value('id'),
                    'name' => $name,
                    'is_active' => true,
                    'is_system' => true,
                    'is_sensitive' => true,
                    'sort' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
};
