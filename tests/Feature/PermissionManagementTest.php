<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class PermissionManagementTest extends TestCase
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

    public function test_permission_metadata_and_menu_bindings_are_saved_atomically(): void
    {
        $parent = AdminPermission::query()->where('code', 'system.access')->firstOrFail();
        $menu = AdminMenu::query()->where('code', 'system.permissions')->firstOrFail();

        $this->postJson('/api/admin/system/permissions', [
            'parent_id' => $parent->getKey(),
            'code' => 'match.publish',
            'name' => 'Publish matches',
            'description' => 'Publish a match after validation.',
            'http_methods' => ['POST'],
            'http_paths' => ['/api/admin/matches/{match}/publish'],
            'sort' => 30,
            'is_active' => true,
            'is_sensitive' => true,
            'menu_ids' => [$menu->getKey()],
            'unexpected_admin_flag' => true,
        ])->assertCreated()
            ->assertJsonPath('permission.code', 'match.publish')
            ->assertJsonPath('permission.http_methods.0', 'POST')
            ->assertJsonPath('permission.menus.0.id', $menu->getKey());

        $permission = AdminPermission::query()->where('code', 'match.publish')->firstOrFail();
        self::assertSame(['POST'], $permission->http_methods);
        self::assertSame(['/api/admin/matches/{match}/publish'], $permission->http_paths);
        self::assertArrayNotHasKey('unexpected_admin_flag', $permission->getAttributes());
        self::assertTrue($permission->menus()->whereKey($menu->getKey())->exists());
    }

    public function test_invalid_methods_and_hierarchy_cycles_are_rejected_without_partial_writes(): void
    {
        $this->postJson('/api/admin/system/permissions', [
            'code' => 'match.invalid',
            'name' => 'Invalid method',
            'http_methods' => ['TRACE'],
        ])->assertUnprocessable()->assertJsonValidationErrors('http_methods.0');
        self::assertFalse(AdminPermission::query()->where('code', 'match.invalid')->exists());

        $parent = AdminPermission::query()->create(['code' => 'match.group', 'name' => 'Match group']);
        $child = AdminPermission::query()->create(['parent_id' => $parent->getKey(), 'code' => 'match.group.view', 'name' => 'View match group']);

        $this->patchJson('/api/admin/system/permissions/'.$parent->getKey(), ['parent_id' => $child->getKey()])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PERMISSION_CYCLE');
        self::assertNull($parent->fresh()->parent_id);
    }
}
