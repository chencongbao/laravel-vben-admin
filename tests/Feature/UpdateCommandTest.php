<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class UpdateCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, ActivitylogServiceProvider::class, LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    public function test_update_is_repeatable_and_preserves_passwords_and_custom_assignments(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();

        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $administrator->update(['password' => 'changed-manager-password']);
        $manager = AdminRole::query()->where('code', 'manager')->firstOrFail();
        $securityPermission = AdminPermission::query()->where('code', 'system.security.view')->firstOrFail();
        $manager->permissions()->syncWithoutDetaching([$securityPermission->getKey()]);

        $this->artisan('vben-admin:update', ['--skip-frontend' => true])->assertSuccessful();
        $this->artisan('vben-admin:update', ['--skip-frontend' => true])->assertSuccessful();

        self::assertTrue(Hash::check('changed-manager-password', $administrator->fresh()->password));
        self::assertTrue($manager->permissions()->whereKey($securityPermission->getKey())->exists());
    }

    public function test_update_dry_run_does_not_write_system_data(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $permission = AdminPermission::query()->where('code', 'system.user.view')->firstOrFail();
        $permission->update(['name' => 'Temporary custom label']);

        $this->artisan('vben-admin:update', ['--dry-run' => true])
            ->expectsOutputToContain('no files or database records will be written')
            ->assertSuccessful();

        self::assertSame('Temporary custom label', $permission->fresh()->name);
    }
}
