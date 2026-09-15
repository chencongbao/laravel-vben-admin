<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        Schema::table($menus, function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
        });

        DB::table($menus)->whereNotNull('parent_code')->orderBy('id')->each(function (object $menu) use ($menus): void {
            $parentId = DB::table($menus)->where('code', $menu->parent_code)->value('id');
            DB::table($menus)->where('id', $menu->id)->update(['parent_id' => $parentId]);
        });

        Schema::table($menus, function (Blueprint $table) use ($menus): void {
            $table->foreign('parent_id')->references('id')->on($menus)->nullOnDelete();
            $table->dropIndex(['parent_code']);
        });

        Schema::table($menus, function (Blueprint $table): void {
            $table->dropColumn('parent_code');
        });
    }

    public function down(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        Schema::table($menus, function (Blueprint $table): void {
            $table->string('parent_code', 160)->nullable()->index();
        });

        DB::table($menus)->whereNotNull('parent_id')->orderBy('id')->each(function (object $menu) use ($menus): void {
            $parentCode = DB::table($menus)->where('id', $menu->parent_id)->value('code');
            DB::table($menus)->where('id', $menu->id)->update(['parent_code' => $parentCode]);
        });

        Schema::table($menus, function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
