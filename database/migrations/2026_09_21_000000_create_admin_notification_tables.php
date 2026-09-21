<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-vben-admin.tables.notifications', 'admin_notifications'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 160)->index();
            $table->string('source', 80)->default('system')->index();
            $table->string('type', 40)->default('system')->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('title_key')->nullable();
            $table->string('message_key')->nullable();
            $table->json('parameters')->nullable();
            $table->string('icon', 120)->nullable();
            $table->text('link')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->nullOnDelete();
            $table->timestamp('published_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.notification_states', 'admin_notification_states'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained(config('laravel-vben-admin.tables.notifications', 'admin_notifications'))->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained(config('laravel-vben-admin.tables.users', 'admin_users'))->cascadeOnDelete();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('hidden_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['notification_id', 'admin_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-vben-admin.tables.notification_states', 'admin_notification_states'));
        Schema::dropIfExists(config('laravel-vben-admin.tables.notifications', 'admin_notifications'));
    }
};
