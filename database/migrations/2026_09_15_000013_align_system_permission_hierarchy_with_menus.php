<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        foreach ($this->groups() as $code => $name) {
            DB::table($table)->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                    'is_system' => true,
                    'is_sensitive' => false,
                    'sort' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->moveChildren($table, [
            'system.audit.view' => 'system.logs.access',
            'system.login-log.view' => 'system.logs.access',
            'system.setting.view' => 'system.configuration.access',
        ]);
    }

    public function down(): void
    {
        $table = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        $this->moveChildren($table, [
            'system.audit.view' => 'system.access',
            'system.login-log.view' => 'system.access',
            'system.setting.view' => 'system.access',
        ]);

        DB::table($table)->whereIn('code', array_keys($this->groups()))->delete();
    }

    /** @param array<string, string> $parents */
    private function moveChildren(string $table, array $parents): void
    {
        foreach ($parents as $code => $parentCode) {
            $parentId = DB::table($table)->where('code', $parentCode)->value('id');
            DB::table($table)->where('code', $code)->update(['parent_id' => $parentId]);
        }
    }

    /** @return array<string, string> */
    private function groups(): array
    {
        return [
            'system.logs.access' => 'System logs',
            'system.configuration.access' => 'Configuration administration',
        ];
    }
};
