<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(false)->after('avatar')->index();
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->dropIndex(['two_factor_enabled']);
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
