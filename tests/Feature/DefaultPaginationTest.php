<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;

final class DefaultPaginationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
    }

    public function test_saved_page_size_is_used_by_backend_and_public_bootstrap(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        AdminSetting::query()->create([
            'is_system' => true,
            'key' => 'system.page_size',
            'type' => 'integer',
            'value' => 40,
        ]);

        self::assertSame(40, AdminPagination::defaultPageSize());
        self::assertSame(40, AdminPagination::perPage());
        self::assertSame(60, AdminPagination::perPage(60));

        $this->getJson('/api/admin/application')
            ->assertOk()
            ->assertJsonPath('page_size', 40);
    }
}
