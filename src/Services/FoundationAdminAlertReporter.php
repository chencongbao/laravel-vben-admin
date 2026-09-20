<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\Foundation\Services\Logging\FoundationLogger;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class FoundationAdminAlertReporter implements AdminAlertReporter
{
    public function __construct(
        private readonly FoundationLogger $logger,
        private readonly TelegramMessageDispatcher $telegram,
    ) {}

    public function reportSystemException(Throwable $exception): void
    {
        if (! config('laravel-vben-admin.alerts.system_exceptions', true)) {
            return;
        }

        $context = [];
        if (app()->bound('request') && app('request') instanceof Request) {
            $context['request'] = $this->requestContext(app('request'));
        } else {
            $context['source'] = PHP_SAPI;
        }

        $this->logger->exception('admin_system', $exception, $context);
    }

    public function reportLoginFailure(
        Request $request,
        string $username,
        ?AdminUser $user,
        ?string $failureCode,
    ): void {
        if (! config('laravel-vben-admin.alerts.login_failures', true)) {
            return;
        }

        $occurrenceCount = $this->loginFailureOccurrenceCount($request, $username, $failureCode);
        $thresholds = array_map('intval', (array) config('laravel-vben-admin.login_failure_alerts.thresholds', [1, 5, 10, 20]));
        if (! in_array($occurrenceCount, $thresholds, true) || ! $this->withinGlobalLoginAlertLimit()) {
            return;
        }

        $this->telegram->json([
            'event' => 'auth.login.failed',
            'username' => mb_substr(trim($username), 0, 120),
            'admin_user_id' => $user?->getKey(),
            'failure_code' => $failureCode,
            'occurrence_count' => $occurrenceCount,
            'time' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'request' => $this->requestContext($request),
        ], '['.config('app.name', 'Laravel').'] 登录异常');
    }

    private function loginFailureOccurrenceCount(Request $request, string $username, ?string $failureCode): int
    {
        $window = max(60, (int) config('laravel-vben-admin.login_failure_alerts.window_seconds', 600));
        $scope = implode('|', [
            (string) ($request->ip() ?? 'unknown'),
            mb_strtolower(trim($username)),
            (string) $failureCode,
        ]);
        $key = 'laravel-vben-admin:login-alert:'.hash('sha256', $scope);
        Cache::add($key, 0, $window);

        return (int) Cache::increment($key);
    }

    private function withinGlobalLoginAlertLimit(): bool
    {
        $limit = max(1, (int) config('laravel-vben-admin.login_failure_alerts.global_limit', 30));
        $key = 'laravel-vben-admin:login-alert-global:'.now()->format('YmdHi');
        Cache::add($key, 0, 120);

        return (int) Cache::increment($key) <= $limit;
    }

    private function requestContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'method' => strtoupper($request->method()),
            'path' => '/'.ltrim($request->path(), '/'),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'request_id' => mb_substr((string) $request->header('X-Request-ID', ''), 0, 120),
        ];
    }
}
