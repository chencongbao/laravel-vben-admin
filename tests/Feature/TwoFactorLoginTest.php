<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\TwoFactorAuthentication;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class TwoFactorLoginTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelVbenAdminServiceProvider::class,
        ];
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
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }

    public function test_local_environment_skips_two_factor_challenge_when_enabled(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');

        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $this->app->make(TwoFactorAuthentication::class)->enable($user);

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertOk()
            ->assertJsonMissing(['two_factor_required' => true])
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_non_local_environment_still_requires_two_factor_challenge_when_enabled(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();

        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $user->forceFill([
            'login_ip_whitelist' => ['127.0.0.1'],
        ])->save();
        $this->app->make(TwoFactorAuthentication::class)->enable($user);

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertStatus(202)
            ->assertJson([
                'two_factor_required' => true,
                'setup_required' => true,
            ])
            ->assertJsonStructure(['challenge_token', 'expires_in', 'secret', 'qr_code']);
    }

    public function test_non_local_environment_rejects_login_before_two_factor_when_ip_is_not_whitelisted(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();

        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $this->app->make(TwoFactorAuthentication::class)->enable($user);

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertForbidden()->assertJson([
            'code' => 'LOGIN_IP_NOT_ALLOWED',
        ]);
    }

    public function test_super_administrator_is_forced_to_configure_two_factor_outside_local(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $user = AdminUser::query()->where('username', 'cmsadmin')->firstOrFail();
        $user->forceFill(['login_ip_whitelist' => ['127.0.0.1']])->save();

        $this->postJson('/api/admin/auth/login', ['username' => 'cmsadmin', 'password' => 'admin'])
            ->assertStatus(202)->assertJsonPath('two_factor_required', true)->assertJsonPath('setup_required', true);

        self::assertTrue($user->fresh()->two_factor_enabled);
    }

    public function test_two_factor_challenge_is_bound_to_ip_and_user_agent(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();
        $user->forceFill(['login_ip_whitelist' => ['127.0.0.1']])->save();
        $this->app->make(TwoFactorAuthentication::class)->enable($user);
        $challenge = $this->withHeader('User-Agent', 'First Agent')->postJson('/api/admin/auth/login', [
            'username' => 'admin', 'password' => 'admin',
        ])->assertStatus(202)->json();
        $user->refresh();
        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $this->withHeader('User-Agent', 'Different Agent')->postJson('/api/admin/auth/two-factor/challenge', [
            'challenge_token' => $challenge['challenge_token'], 'code' => $code,
        ])->assertUnprocessable()->assertJsonPath('code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_created_access_token_has_an_expiration_time(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');
        $user = AdminUser::query()->where('username', 'admin')->firstOrFail();

        $this->postJson('/api/admin/auth/login', ['username' => 'admin', 'password' => 'admin'])->assertOk();

        self::assertNotNull($user->tokens()->latest('id')->firstOrFail()->expires_at);
    }
}
