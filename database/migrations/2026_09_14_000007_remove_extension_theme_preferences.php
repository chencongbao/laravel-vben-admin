<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updatePreferences(function (array $preferences): array {
            unset(
                $preferences['app']['watermark'],
                $preferences['app']['watermarkContent'],
                $preferences['footer'],
                $preferences['copyright'],
                $preferences['custom'],
            );

            return $preferences;
        });
    }

    public function down(): void
    {
        $this->updatePreferences(function (array $preferences): array {
            $preferences['app'] = array_merge($preferences['app'] ?? [], [
                'watermark' => false,
                'watermarkContent' => '',
            ]);
            $preferences['footer'] = ['enable' => false, 'fixed' => false];
            $preferences['copyright'] = [
                'enable' => true,
                'companyName' => '',
                'companySiteLink' => '',
                'date' => '2026',
                'icp' => '',
                'icpLink' => '',
            ];
            $preferences['custom'] = [
                'enableFormFullscreen' => true,
                'tenantMode' => 'single',
                'defaultTableSize' => 20,
                'reportTitle' => '',
            ];

            return $preferences;
        });
    }

    private function updatePreferences(callable $callback): void
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

        DB::table($table)->where('key', 'system.advanced_preferences')->update([
            'value' => json_encode($callback($preferences), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
