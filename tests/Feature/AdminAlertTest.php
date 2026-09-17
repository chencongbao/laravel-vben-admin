<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\Foundation\Jobs\SendTelegramNotification;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Jobs\SendTelegramMessage;
use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\TelegramMessageDispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Throwable;

final class AdminAlertTest extends TestCase
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
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }

    public function test_failed_login_is_logged_before_alert_is_requested(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');

        $alerts = new class implements AdminAlertReporter
        {
            public array $loginFailures = [];

            public function reportSystemException(Throwable $exception): void {}

            public function reportLoginFailure(Request $request, string $username, ?AdminUser $user, ?string $failureCode): void
            {
                $this->loginFailures[] = compact('username', 'user', 'failureCode');
                if (! Activity::query()->where('event', 'auth.login.failed')->exists()) {
                    throw new RuntimeException('Login failure was not persisted before the alert.');
                }
            }
        };
        $this->app->instance(AdminAlertReporter::class, $alerts);

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        self::assertCount(1, $alerts->loginFailures);
        self::assertSame('admin', $alerts->loginFailures[0]['username']);
        self::assertSame('INVALID_CREDENTIALS', $alerts->loginFailures[0]['failureCode']);
        self::assertInstanceOf(AdminUser::class, $alerts->loginFailures[0]['user']);
    }

    public function test_reportable_system_exception_is_forwarded_to_alert_reporter(): void
    {
        $exception = new RuntimeException('system failed');
        $alerts = $this->createMock(AdminAlertReporter::class);
        $alerts->expects(self::once())
            ->method('reportSystemException')
            ->with($exception);
        $this->app->instance(AdminAlertReporter::class, $alerts);

        $this->app->make(ExceptionHandler::class)->report($exception);
    }

    public function test_failed_login_is_stored_in_database_and_dispatches_admin_job_without_credentials(): void
    {
        $this->artisan('vben-admin:install', ['--skip-frontend' => true])->assertSuccessful();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->app['config']->set('foundation_log.telegram.enabled', true);
        $this->app['config']->set('foundation_log.telegram.bot_token', 'test-bot-token');
        $this->app['config']->set('foundation_log.telegram.chat_ids', ['123456']);
        $this->app['config']->set('foundation_log.telegram.queue.enabled', true);
        Queue::fake();

        $this->postJson('/api/admin/auth/login', [
            'username' => 'admin',
            'password' => 'never-serialize-this-password',
        ])->assertUnprocessable();

        self::assertTrue(Activity::query()
            ->where('log_type', 'login')
            ->where('event', 'auth.login.failed')
            ->where('properties->failure_code', 'INVALID_CREDENTIALS')
            ->exists());
        Queue::assertNotPushed(SendTelegramNotification::class);
        Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job): bool {
            $payload = serialize($job);

            return ! str_contains($payload, 'test-bot-token')
                && ! str_contains($payload, 'never-serialize-this-password');
        });
    }

    public function test_projects_can_dispatch_json_messages_through_the_shared_job(): void
    {
        Queue::fake();
        $this->app['config']->set('foundation_log.telegram.enabled', true);
        $this->app['config']->set('foundation_log.telegram.bot_token', 'test-bot-token');
        $this->app['config']->set('foundation_log.telegram.chat_ids', ['123456']);
        $this->app['config']->set('foundation_log.telegram.queue.name', 'notice');

        $this->app->make(TelegramMessageDispatcher::class)->json([
            'event' => 'example.completed',
            'object_id' => 1001,
        ], 'Business Alert');

        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->queue === 'notice');
    }
}
