<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        foreach ($this->parents() as $code => $parentCode) {
            $parentId = DB::table($table)->where('code', $parentCode)->value('id');
            if ($parentId !== null) {
                DB::table($table)->where('code', $code)->update(['parent_id' => $parentId]);
            }
        }
    }

    public function down(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        foreach (array_keys($this->parents()) as $code) {
            DB::table($table)->where('code', $code)->update(['parent_id' => null]);
        }
    }

    /** @return array<string, string> */
    private function parents(): array
    {
        return [
            'system.user.view' => 'system.access',
            'system.user.create' => 'system.user.view',
            'system.user.update' => 'system.user.view',
            'system.user.assign-roles' => 'system.user.view',
            'system.role.view' => 'system.access',
            'system.role.create' => 'system.role.view',
            'system.role.update' => 'system.role.view',
            'system.role.delete' => 'system.role.view',
            'system.role.assign-access' => 'system.role.view',
            'system.permission.view' => 'system.access',
            'system.permission.create' => 'system.permission.view',
            'system.permission.update' => 'system.permission.view',
            'system.permission.delete' => 'system.permission.view',
            'system.menu.view' => 'system.access',
            'system.menu.create' => 'system.menu.view',
            'system.menu.update' => 'system.menu.view',
            'system.menu.delete' => 'system.menu.view',
            'system.audit.view' => 'system.access',
            'system.login-log.view' => 'system.access',
            'system.login-log.delete' => 'system.login-log.view',
            'system.setting.view' => 'system.access',
            'system.setting.update' => 'system.setting.view',
        ];
    }
};
