<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-vben-admin.tables.security_events', 'admin_security_events'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->index();
            $table->string('severity', 20)->index();
            $table->foreignId('admin_user_id')->nullable()->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->nullOnDelete();
            $table->string('username', 120)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->nullOnDelete();
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.login_ip_blocks', 'admin_login_ip_blocks'), function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('reason_code', 120);
            $table->boolean('is_automatic')->default(false)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-vben-admin.tables.login_ip_blocks', 'admin_login_ip_blocks'));
        Schema::dropIfExists(config('laravel-vben-admin.tables.security_events', 'admin_security_events'));
    }
};
