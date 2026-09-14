<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;

interface LoginRecorder
{
    public function record(
        Request $request,
        string $username,
        ?AdminUser $user,
        bool $succeeded,
        ?string $failureCode = null,
    ): void;
}
