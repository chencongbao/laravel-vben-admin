<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        Schema::table($permissions, function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        Schema::table($permissions, function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->after('name');
        });
    }
};
