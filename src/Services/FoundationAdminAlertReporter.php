<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\Foundation\Services\Logging\FoundationLogger;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
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

        $this->telegram->json([
            'event' => 'auth.login.failed',
            'username' => mb_substr(trim($username), 0, 120),
            'admin_user_id' => $user?->getKey(),
            'failure_code' => $failureCode,
            'time' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'request' => $this->requestContext($request),
        ], '['.config('app.name', 'Laravel').'] 登录异常');
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
