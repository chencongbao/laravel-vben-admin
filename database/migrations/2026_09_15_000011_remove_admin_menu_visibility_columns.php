<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        if (Schema::hasColumn($menus, 'is_active')) {
            Schema::table($menus, function (Blueprint $table): void {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn($menus, 'is_hidden')) {
            Schema::table($menus, function (Blueprint $table): void {
                $table->dropColumn('is_hidden');
            });
        }
    }

    public function down(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        Schema::table($menus, function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_hidden')->default(false);
        });
    }
};
