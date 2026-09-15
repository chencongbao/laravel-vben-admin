<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        foreach (['http_methods', 'http_paths'] as $column) {
            if (Schema::hasColumn($permissions, $column)) {
                Schema::table($permissions, function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        if (! Schema::hasColumn($permissions, 'http_methods')) {
            Schema::table($permissions, function (Blueprint $table): void {
                $table->json('http_methods')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn($permissions, 'http_paths')) {
            Schema::table($permissions, function (Blueprint $table): void {
                $table->json('http_paths')->nullable()->after('http_methods');
            });
        }
    }
};
