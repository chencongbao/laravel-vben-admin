<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Contracts\LoginRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
use Spatie\Activitylog\Contracts\Activity;

final class ActivityLoginRecorder implements LoginRecorder
{
    public function __construct(
        private readonly LoginClientClassifier $clientClassifier,
        private readonly AdminAlertReporter $alertReporter,
    ) {}

    public function record(
        Request $request,
        string $username,
        ?AdminUser $user,
        bool $succeeded,
        ?string $failureCode = null,
    ): void {
        $event = $succeeded ? 'auth.login.succeeded' : 'auth.login.failed';
        $logger = activity(config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->event($event)
            ->withProperties([
                'username' => $username,
                'succeeded' => $succeeded,
                'failure_code' => $failureCode,
                'client_type' => $this->clientClassifier->classify($request->userAgent()),
            ])
            ->tap(function (Activity $activity) use ($request): void {
                $activity->log_type = 'login';
                $activity->ip_address = $request->ip();
                $activity->method = strtoupper($request->method());
                $activity->path = '/'.ltrim($request->path(), '/');
                $activity->user_agent = mb_substr((string) $request->userAgent(), 0, 2000);
            });

        if ($user !== null) {
            $logger->causedBy($user);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->log($succeeded ? '登录成功' : '登录失败');

        if (! $succeeded) {
            $this->alertReporter->reportLoginFailure($request, $username, $user, $failureCode);
        }
    }
}
