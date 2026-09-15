<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class MenuManagementTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, ActivitylogServiceProvider::class, LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('vben-admin:install')->assertSuccessful();
        Sanctum::actingAs(AdminUser::query()->where('username', 'cmsadmin')->firstOrFail(), ['admin']);
    }

    public function test_workspace_is_available_but_excluded_from_menu_management(): void
    {
        $workspace = AdminMenu::query()->where('code', 'dashboard.workspace')->firstOrFail();
        AdminMenu::query()->where('code', 'system')->update(['sort' => -100000]);

        $this->getJson('/api/admin/access/menus')
            ->assertOk()
            ->assertJsonPath('menus.0.code', 'dashboard.workspace')
            ->assertJsonPath('menus.0.meta.order', -100001)
            ->assertJsonPath('menus.0.meta.affixTab', true)
            ->assertJsonPath('menus.0.meta.tabClosable', false);

        $this->getJson('/api/admin/system/menus')
            ->assertOk()
            ->assertJsonMissing(['code' => 'dashboard.workspace']);

        $this->getJson('/api/admin/system/menus/'.$workspace->getKey())->assertNotFound();
        $this->patchJson('/api/admin/system/menus/'.$workspace->getKey(), ['title' => 'Changed'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DEFAULT_MENU_PROTECTED');
    }

    public function test_reorder_requires_all_manageable_menus_but_not_workspace(): void
    {
        $items = AdminMenu::query()
            ->where('code', '!=', 'dashboard.workspace')
            ->get()
            ->map(fn (AdminMenu $menu) => [
                'id' => $menu->getKey(),
                'parent_id' => $menu->parent_id,
                'sort' => $menu->sort,
            ])
            ->all();

        $this->putJson('/api/admin/system/menus/reorder', ['items' => $items])->assertOk();
    }

    public function test_workspace_cannot_be_used_as_a_parent(): void
    {
        $workspace = AdminMenu::query()->where('code', 'dashboard.workspace')->firstOrFail();

        $this->postJson('/api/admin/system/menus', [
            'code' => 'content.invalid-parent',
            'parent_id' => $workspace->getKey(),
            'title' => 'Invalid parent',
            'type' => 'page',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_menu_permissions_are_saved_and_returned(): void
    {
        $view = AdminPermission::query()->where('code', 'system.menu.view')->firstOrFail();
        $update = AdminPermission::query()->where('code', 'system.menu.update')->firstOrFail();

        $response = $this->postJson('/api/admin/system/menus', [
            'code' => 'content.articles',
            'title' => 'Articles',
            'type' => 'page',
            'route_name' => 'ContentArticles',
            'route_path' => '/content/articles',
            'view_key' => 'content.articles',
            'permission_ids' => [$view->getKey(), $update->getKey()],
        ])->assertCreated()
            ->assertJsonPath('menu.icon', 'lucide:list')
            ->assertJsonCount(2, 'menu.permissions');

        $menuId = $response->json('menu.id');
        $menu = AdminMenu::query()->findOrFail($menuId);
        self::assertSame('lucide:list', $menu->icon);
        self::assertEqualsCanonicalizing(
            [$view->getKey(), $update->getKey()],
            $menu->permissions()->pluck('admin_permissions.id')->all(),
        );

        $this->getJson('/api/admin/system/menus')
            ->assertOk()
            ->assertJsonFragment(['code' => 'content.articles'])
            ->assertJsonFragment(['code' => 'system.menu.update']);
    }

    public function test_menu_table_has_no_visibility_columns(): void
    {
        $menus = config('laravel-vben-admin.tables.menus', 'admin_menus');

        self::assertFalse(Schema::hasColumn($menus, 'is_active'));
        self::assertFalse(Schema::hasColumn($menus, 'is_hidden'));
    }

    public function test_menu_table_uses_only_the_permission_pivot(): void
    {
        self::assertFalse(Schema::hasColumn(config('laravel-vben-admin.tables.menus', 'admin_menus'), 'permission_code'));
    }

    public function test_updating_custom_menu_code_does_not_break_child_hierarchy(): void
    {
        $parent = AdminMenu::query()->create([
            'code' => 'content.old', 'title' => 'Content', 'type' => 'directory', 'route_path' => '/content/old',
        ]);
        $child = AdminMenu::query()->create([
            'code' => 'content.child', 'parent_id' => $parent->getKey(), 'title' => 'Child', 'type' => 'page',
        ]);

        $this->patchJson('/api/admin/system/menus/'.$parent->getKey(), [
            'code' => 'content.new',
            'route_path' => '/content/new',
        ])->assertOk()
            ->assertJsonPath('menu.code', 'content.new');

        self::assertSame($parent->getKey(), $child->fresh()->parent_id);
        self::assertSame('content.new', $parent->fresh()->code);
    }
}
