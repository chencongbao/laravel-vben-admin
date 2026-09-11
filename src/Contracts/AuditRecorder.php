<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

interface AuditRecorder
{
    public function record(AdminUser $actor, string $action, ?object $subject = null, array $changes = [], array $context = []): void;
}
