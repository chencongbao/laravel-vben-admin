<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->boolean('login_ip_whitelist_enabled')->default(false)->after('two_factor_confirmed_at')->index();
            $table->json('login_ip_whitelist')->nullable()->after('login_ip_whitelist_enabled');
        });
    }

    public function down(): void
    {
        Schema::table(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->dropIndex(['login_ip_whitelist_enabled']);
            $table->dropColumn(['login_ip_whitelist_enabled', 'login_ip_whitelist']);
        });
    }
};
