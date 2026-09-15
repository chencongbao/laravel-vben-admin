<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        if (Schema::hasColumn($permissions, 'is_active')) {
            Schema::table($permissions, function (Blueprint $table): void {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn($permissions, 'is_sensitive')) {
            Schema::table($permissions, fn (Blueprint $table) => $table->dropColumn('is_sensitive'));
        }
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        if (! Schema::hasColumn($permissions, 'is_active')) {
            Schema::table($permissions, fn (Blueprint $table) => $table->boolean('is_active')->default(true)->index());
        }

        if (! Schema::hasColumn($permissions, 'is_sensitive')) {
            Schema::table($permissions, fn (Blueprint $table) => $table->boolean('is_sensitive')->default(false));
        }
    }
};
