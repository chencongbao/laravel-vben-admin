<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            if (! Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                Schema::table('personal_access_tokens', function (Blueprint $table): void {
                    $table->string('ip_address', 45)->nullable();
                });
            }
            if (! Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                Schema::table('personal_access_tokens', function (Blueprint $table): void {
                    $table->text('user_agent')->nullable();
                });
            }
        }

        Schema::create(config('laravel-vben-admin.tables.users', 'admin_users'), function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('password');
            $table->string('name', 120);
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->boolean('two_factor_enabled')->default(false)->index();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('login_ip_whitelist')->nullable();
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
            $table->foreignId('parent_id')->nullable()->constrained(config('laravel-vben-admin.tables.permissions', 'admin_permissions'))->nullOnDelete();
            $table->string('code', 160)->unique();
            $table->string('name', 160);
            $table->integer('sort')->default(0)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_deprecated')->default(false)->index();
            $table->timestamps();
        });

        Schema::create(config('laravel-vben-admin.tables.menus', 'admin_menus'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained(config('laravel-vben-admin.tables.menus', 'admin_menus'))->nullOnDelete();
            $table->string('code', 160)->unique();
            $table->string('title', 160);
            $table->string('type', 20)->default('page');
            $table->string('route_name', 160)->nullable()->unique();
            $table->string('route_path')->nullable();
            $table->string('view_key', 160)->nullable();
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
        $this->createPivot(
            config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus'),
            'permission_id',
            config('laravel-vben-admin.tables.permissions', 'admin_permissions'),
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

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->string('log_type', 20)->default('operation');
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 12)->nullable();
            $table->text('path')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['log_name', 'log_type', 'created_at'], 'activity_log_name_type_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        foreach (['settings', 'permission_menus', 'role_menus', 'role_permissions', 'user_roles', 'menus', 'permissions', 'roles', 'users'] as $key) {
            Schema::dropIfExists(config("laravel-vben-admin.tables.{$key}"));
        }

        if (Schema::hasTable('personal_access_tokens')) {
            foreach (['ip_address', 'user_agent'] as $column) {
                if (Schema::hasColumn('personal_access_tokens', $column)) {
                    Schema::table('personal_access_tokens', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
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
