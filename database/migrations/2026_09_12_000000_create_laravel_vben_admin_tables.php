<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('password');
            $table->string('name', 120);
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.roles', 'admin_roles'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.permissions', 'admin_permissions'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 160)->unique();
            $table->string('name', 160);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_deprecated')->default(false)->index();
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.menus', 'admin_menus'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 160)->unique();
            $table->string('parent_code', 160)->nullable()->index();
            $table->string('title', 160);
            $table->string('type', 20)->default('page');
            $table->string('route_name', 160)->nullable()->unique();
            $table->string('route_path')->nullable();
            $table->string('view_key', 160)->nullable();
            $table->string('permission_code', 160)->nullable()->index();
            $table->string('icon', 160)->nullable();
            $table->integer('sort')->default(0)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $this->createPivot(
            config('laravel-vben-admin.tables.user_roles', 'admin_user_roles'),
            'user_id',
            config('laravel-vben-admin.tables.users', 'admin_users'),
            'role_id',
            config('laravel-vben-admin.tables.roles', 'admin_roles'),
        );
        $this->createPivot(
            config('laravel-vben-admin.tables.role_permissions', 'admin_role_permissions'),
            'role_id',
            config('laravel-vben-admin.tables.roles', 'admin_roles'),
            'permission_id',
            config('laravel-vben-admin.tables.permissions', 'admin_permissions'),
        );
        $this->createPivot(
            config('laravel-vben-admin.tables.role_menus', 'admin_role_menus'),
            'role_id',
            config('laravel-vben-admin.tables.roles', 'admin_roles'),
            'menu_id',
            config('laravel-vben-admin.tables.menus', 'admin_menus'),
        );

        Schema::create(config('laravel-vben-admin.tables.settings', 'admin_settings'), function (Blueprint $table): void {
            $table->id();
            $table->string('key', 160)->unique();
            $table->string('type', 40);
            $table->json('value')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.login_logs', 'admin_login_logs'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('username', 120);
            $table->boolean('succeeded')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create(config('laravel-vben-admin.tables.audit_logs', 'admin_audit_logs'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->index();
            $table->string('action', 160)->index();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 120)->nullable();
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'login_logs', 'settings', 'role_menus', 'role_permissions', 'user_roles', 'menus', 'permissions', 'roles', 'users'] as $key) {
            Schema::dropIfExists(config("laravel-vben-admin.tables.{$key}"));
        }
    }

    private function createPivot(string $tableName, string $left, string $leftTable, string $right, string $rightTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($left, $leftTable, $right, $rightTable): void {
            $table->unsignedBigInteger($left);
            $table->unsignedBigInteger($right);
            $table->timestamps();
            $table->unique([$left, $right]);
            $table->foreign($left)->references('id')->on($leftTable)->cascadeOnDelete();
            $table->foreign($right)->references('id')->on($rightTable)->cascadeOnDelete();
        });
    }
};
