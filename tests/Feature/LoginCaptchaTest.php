<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Orchestra\Testbench\TestCase;

final class LoginCaptchaTest extends TestCase
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
        $app['config']->set('cache.default', 'array');
    }

    public function test_first_invalid_login_requires_captcha_for_the_next_attempt(): void
    {
        $this->artisan('vben-admin:install')->assertSuccessful();

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'captcha_required' => true,
        ]);

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertStatus(422)->assertJson([
            'code' => 'CAPTCHA_INVALID',
            'captcha_required' => true,
        ]);

        $this->getJson('/api/admin/auth/captcha?username=admin')
            ->assertOk()
            ->assertJsonStructure(['captcha_key', 'captcha_image', 'captcha_hint', 'expires_in'])
            ->assertJsonPath('expires_in', 600);
    }
}
