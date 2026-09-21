<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

interface AdminNotificationSource
{
    public function paginate(AdminUser $user, int $page, int $perPage): array;

    public function latest(AdminUser $user, int $limit): array;

    public function unreadCount(AdminUser $user): int;

    public function markAsRead(AdminUser $user, string $id): void;

    public function markAllAsRead(AdminUser $user): void;

    public function hide(AdminUser $user, string $id): void;

    public function clear(AdminUser $user): void;
}
