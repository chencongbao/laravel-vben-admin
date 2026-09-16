<?php

namespace Chencongbao\LaravelVbenAdmin\Events;

use Chencongbao\LaravelVbenAdmin\Models\AdminSecurityEvent;
use Illuminate\Foundation\Events\Dispatchable;

final class AdminSecurityRiskDetected
{
    use Dispatchable;

    public function __construct(public readonly AdminSecurityEvent $securityEvent) {}
}
