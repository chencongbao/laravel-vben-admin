<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

final class AdminLogController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:160'],
            'actor' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:160'],
            'ended_at' => ['nullable', 'date'],
            'ip_address' => ['nullable', 'ip'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'string', 'max:255'],
        ]);
        $perPage = AdminPagination::perPage($filters['per_page'] ?? null);
        $id = $filters['id'] ?? null;
        $logs = Activity::query()
            ->with('causer')
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'operation')
            ->when($id, fn ($query) => $query->whereKey($id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('event', $action))
            ->when($filters['actor'] ?? null, function ($query, $actor): void {
                $query->whereHasMorph('causer', [AdminUser::class], function ($actorQuery) use ($actor): void {
                    $actorQuery->where(function ($nested) use ($actor): void {
                        $nested->where('name', 'like', "%{$actor}%")
                            ->orWhere('username', 'like', "%{$actor}%");
                        if (ctype_digit($actor)) {
                            $nested->orWhereKey((int) $actor);
                        }
                    });
                });
            })
            ->when($filters['actor_id'] ?? null, fn ($query, $actorId) => $query->where('causer_id', $actorId))
            ->when($filters['description'] ?? null, fn ($query, $description) => $query->where('description', 'like', "%{$description}%"))
            ->when($filters['ip_address'] ?? null, fn ($query, $ipAddress) => $query->where('ip_address', $ipAddress))
            ->when($filters['started_at'] ?? null, fn ($query, $startedAt) => $query->where('created_at', '>=', $startedAt))
            ->when($filters['ended_at'] ?? null, fn ($query, $endedAt) => $query->where('created_at', '<=', $endedAt))
            ->when($filters['subject_id'] ?? null, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', 'like', "%{$subjectType}%"))
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Activity $activity) => $this->auditPayload($activity, false));

        return response()->json($logs);
    }

    public function auditDetail(int $activity): JsonResponse
    {
        $record = Activity::query()
            ->with('causer')
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'operation')
            ->findOrFail($activity);

        return response()->json($this->auditPayload($record, true));
    }

    private function auditPayload(Activity $activity, bool $withDetails): array
    {
        $causer = $activity->causer;
        $payload = [
            'id' => $activity->getKey(),
            'action' => $activity->event,
            'action_type' => $this->resolveActionType($activity),
            'description' => $activity->description,
            'actor_id' => $activity->causer_id,
            'actor' => $causer === null ? null : [
                'id' => $causer->getKey(),
                'name' => $causer->getAttribute('name'),
                'username' => $causer->getAttribute('username'),
            ],
            'subject_type' => $activity->subject_type,
            'subject_type_name' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'changed_count' => $this->changedCount($activity->attribute_changes?->toArray() ?? []),
            'ip_address' => $activity->ip_address,
            'method' => $activity->method,
            'path' => $activity->path,
            'created_at' => $activity->created_at,
        ];

        if ($withDetails) {
            $payload['changes'] = $activity->attribute_changes?->toArray() ?? [];
            $payload['context'] = $activity->getProperty('context', []);
            $payload['request_input'] = $activity->getProperty('request_input', []);
            $payload['request_id'] = $activity->getProperty('request_id');
            $payload['route_name'] = $activity->getProperty('route_name');
            $payload['user_agent'] = $activity->user_agent;
        }

        return $payload;
    }

    private function changedCount(array $changes): int
    {
        if (array_key_exists('old', $changes) || array_key_exists('attributes', $changes)) {
            $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];
            $attributes = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];

            return max(1, count(array_unique(array_merge(array_keys($old), array_keys($attributes)))));
        }

        if (array_key_exists('before', $changes) || array_key_exists('after', $changes)) {
            $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
            $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];

            return ($before !== [] || $after !== [])
                ? count(array_unique(array_merge(array_keys($before), array_keys($after))))
                : 1;
        }

        $count = 0;
        foreach ($changes as $value) {
            if (is_array($value)) {
                $count += $this->changedCount($value);
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function resolveActionType(Activity $activity): string
    {
        $action = strtolower((string) $activity->event);
        $suffix = str_contains($action, '.') ? substr($action, (int) strrpos($action, '.') + 1) : $action;

        return match (true) {
            in_array($suffix, ['create', 'created', 'store'], true), strtoupper((string) $activity->method) === 'POST' => 'created',
            in_array($suffix, ['edit', 'update', 'updated'], true), in_array(strtoupper((string) $activity->method), ['PATCH', 'PUT'], true) => 'updated',
            in_array($suffix, ['delete', 'deleted', 'destroy'], true), strtoupper((string) $activity->method) === 'DELETE' => 'deleted',
            default => 'other',
        };
    }

    public function login(Request $request): JsonResponse
    {
        $filters = $request->validate(['id' => ['nullable', 'integer', 'min:1'], 'username' => ['nullable', 'string', 'max:120'], 'succeeded' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        if (array_key_exists('succeeded', $filters)) {
            $filters['succeeded'] = $request->boolean('succeeded');
        }
        $perPage = AdminPagination::perPage($filters['per_page'] ?? null);
        $id = $filters['id'] ?? null;
        $logs = Activity::query()
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'login')
            ->when($id, fn ($query) => $query->whereKey($id))
            ->when($filters['username'] ?? null, fn ($query, $username) => $query->where('properties->username', 'like', "%{$username}%"))
            ->when(array_key_exists('succeeded', $filters), fn ($query) => $query->where('properties->succeeded', $filters['succeeded']))
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Activity $activity) => [
                'id' => $activity->getKey(),
                'user_id' => $activity->causer_id,
                'username' => $activity->getProperty('username', ''),
                'succeeded' => (bool) $activity->getProperty('succeeded', false),
                'failure_code' => $activity->getProperty('failure_code'),
                'client_type' => $activity->getProperty('client_type', 'unknown'),
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'created_at' => $activity->created_at,
            ]);

        return response()->json($logs);
    }
}
