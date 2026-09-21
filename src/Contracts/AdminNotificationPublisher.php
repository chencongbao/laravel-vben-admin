<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminNotification;

interface AdminNotificationPublisher
{
    public function publish(array $notification): AdminNotification;
}
