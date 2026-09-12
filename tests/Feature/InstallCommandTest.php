<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Orchestra\Testbench\TestCase;

final class InstallCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_install_creates_the_default_administrator_without_resetting_it(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $administrator = AdminUser::query()->where('username', 'admin')->firstOrFail();

        self::assertTrue(Hash::check('admin', $administrator->password));
        self::assertTrue($administrator->roles()->where('code', 'administrator')->exists());
        self::assertTrue(AdminRole::query()->where('code', 'administrator')->where('is_super_admin', true)->exists());
        self::assertTrue(AdminRole::query()->where('code', 'manager')->where('is_super_admin', false)->exists());

        $administrator->update(['password' => 'changed-password']);

        $this->artisan('vben-admin:install')->assertSuccessful();

        self::assertTrue(Hash::check('changed-password', $administrator->fresh()->password));
    }
}
