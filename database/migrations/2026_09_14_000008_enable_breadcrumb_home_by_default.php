<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setShowHome(true);
    }

    public function down(): void
    {
        $this->setShowHome(false);
    }

    private function setShowHome(bool $enabled): void
    {
        $table = config('laravel-vben-admin.tables.settings', 'admin_settings');
        if (! Schema::hasTable($table)) {
            return;
        }

        $setting = DB::table($table)->where('key', 'system.advanced_preferences')->first();
        if ($setting === null) {
            return;
        }

        $preferences = is_string($setting->value)
            ? json_decode($setting->value, true)
            : $setting->value;
        if (! is_array($preferences)) {
            return;
        }

        $preferences['breadcrumb'] = array_merge(
            $preferences['breadcrumb'] ?? [],
            ['showHome' => $enabled],
        );

        DB::table($table)->where('key', 'system.advanced_preferences')->update([
            'value' => json_encode($preferences, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
