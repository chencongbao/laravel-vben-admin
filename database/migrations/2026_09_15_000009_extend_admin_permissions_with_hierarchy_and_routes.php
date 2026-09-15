<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissionMenus = config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus');

        Schema::table($permissions, function (Blueprint $table) use ($permissions): void {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained($permissions)->nullOnDelete();
            $table->string('description', 500)->nullable()->after('name');
            $table->json('http_methods')->nullable()->after('description');
            $table->json('http_paths')->nullable()->after('http_methods');
            $table->integer('sort')->default(0)->after('http_paths')->index();
        });

        Schema::create($permissionMenus, function (Blueprint $table) use ($permissions, $menus): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('menu_id');
            $table->timestamps();
            $table->unique(['permission_id', 'menu_id']);
            $table->foreign('permission_id')->references('id')->on($permissions)->cascadeOnDelete();
            $table->foreign('menu_id')->references('id')->on($menus)->cascadeOnDelete();
        });

        $now = now();
        DB::table($menus)->whereNotNull('permission_code')->orderBy('id')->each(function (object $menu) use ($now, $permissionMenus, $permissions): void {
            $permissionId = DB::table($permissions)->where('code', $menu->permission_code)->value('id');
            if ($permissionId !== null) {
                DB::table($permissionMenus)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'menu_id' => $menu->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        $permissions = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        Schema::dropIfExists(config('laravel-vben-admin.tables.permission_menus', 'admin_permission_menus'));
        Schema::table($permissions, function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'description', 'http_methods', 'http_paths', 'sort']);
        });
    }
};
