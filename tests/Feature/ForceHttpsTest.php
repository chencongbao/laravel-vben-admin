<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Orchestra\Testbench\TestCase;

final class ForceHttpsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('laravel-vben-admin.route.force_https', true);
    }

    public function test_http_admin_api_requests_are_redirected_to_https_with_a_method_preserving_status(): void
    {
        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertRedirect('https://localhost/api/admin/auth/login')
            ->assertStatus(308);
    }

    public function test_https_admin_api_requests_are_not_redirected(): void
    {
        $this->getJson('https://localhost/api/admin/application')
            ->assertOk();
    }
}
