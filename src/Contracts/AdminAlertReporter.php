<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
use Throwable;

interface AdminAlertReporter
{
    public function reportSystemException(Throwable $exception): void;

    public function reportLoginFailure(
        Request $request,
        string $username,
        ?AdminUser $user,
        ?string $failureCode,
    ): void;
}
