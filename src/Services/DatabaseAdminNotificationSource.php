<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationSource;
use Chencongbao\LaravelVbenAdmin\Models\AdminNotification;
use Chencongbao\LaravelVbenAdmin\Models\AdminNotificationState;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DatabaseAdminNotificationSource implements AdminNotificationSource
{
    public function paginate(AdminUser $user, int $page, int $perPage): array
    {
        $paginator = $this->visibleQuery($user)
            ->orderByDesc($this->notificationTable().'.published_at')
            ->orderByDesc($this->notificationTable().'.id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (AdminNotification $item) => $this->serialize($item))->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function latest(AdminUser $user, int $limit): array
    {
        return $this->visibleQuery($user)
            ->orderByDesc($this->notificationTable().'.published_at')
            ->orderByDesc($this->notificationTable().'.id')
            ->limit($limit)
            ->get()
            ->map(fn (AdminNotification $item) => $this->serialize($item))
            ->values()
            ->all();
    }

    public function unreadCount(AdminUser $user): int
    {
        return $this->visibleQuery($user)->whereNull('notification_state.read_at')->count();
    }

    public function markAsRead(AdminUser $user, string $id): void
    {
        $notification = $this->findVisible($user, $id);
        $this->state($user, $notification->getKey())->forceFill(['read_at' => now()])->save();
    }

    public function markAllAsRead(AdminUser $user): void
    {
        $this->setStateForAllVisible($user, 'read_at');
    }

    public function hide(AdminUser $user, string $id): void
    {
        $notification = $this->findVisible($user, $id);
        $this->state($user, $notification->getKey())->forceFill(['hidden_at' => now()])->save();
    }

    public function clear(AdminUser $user): void
    {
        $this->setStateForAllVisible($user, 'hidden_at');
    }

    private function visibleQuery(AdminUser $user): Builder
    {
        $notifications = config('laravel-vben-admin.tables.notifications', 'admin_notifications');
        $states = config('laravel-vben-admin.tables.notification_states', 'admin_notification_states');
        $stateQuery = DB::table($states)
            ->select(['notification_id', 'read_at', 'hidden_at'])
            ->where('admin_user_id', $user->getKey());

        return AdminNotification::query()
            ->from($notifications)
            ->leftJoinSub($stateQuery, 'notification_state', fn ($join) => $join->on($notifications.'.id', '=', 'notification_state.notification_id'))
            ->select($notifications.'.*')
            ->selectRaw('notification_state.read_at as notification_read_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereNull('notification_state.hidden_at');
    }

    private function findVisible(AdminUser $user, string $id): AdminNotification
    {
        abort_unless(ctype_digit($id), 404);

        return $this->visibleQuery($user)->where($this->notificationTable().'.id', (int) $id)->firstOrFail();
    }

    private function state(AdminUser $user, int $notificationId): AdminNotificationState
    {
        return AdminNotificationState::query()->firstOrNew([
            'notification_id' => $notificationId,
            'admin_user_id' => $user->getKey(),
        ]);
    }

    private function setStateForAllVisible(AdminUser $user, string $column): void
    {
        $ids = $this->visibleQuery($user)->pluck($this->notificationTable().'.id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($column, $ids, $user): void {
            $now = now();
            AdminNotificationState::query()->upsert(
                $ids->map(fn ($id) => [
                    'notification_id' => $id,
                    'admin_user_id' => $user->getKey(),
                    $column => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['notification_id', 'admin_user_id'],
                [$column, 'updated_at'],
            );
        });
    }

    private function serialize(AdminNotification $notification): array
    {
        return [
            'id' => (string) $notification->getKey(),
            'code' => $notification->code,
            'source' => $notification->source,
            'type' => $notification->type,
            'severity' => $notification->severity,
            'title' => $notification->title,
            'message' => $notification->message,
            'title_key' => $notification->title_key,
            'message_key' => $notification->message_key,
            'parameters' => $notification->parameters ?? [],
            'icon' => $notification->icon,
            'link' => $notification->link,
            'metadata' => $notification->metadata ?? [],
            'is_read' => $notification->getAttribute('notification_read_at') !== null,
            'published_at' => $notification->published_at?->toISOString(),
            'expires_at' => $notification->expires_at?->toISOString(),
        ];
    }

    private function notificationTable(): string
    {
        return config('laravel-vben-admin.tables.notifications', 'admin_notifications');
    }
}
