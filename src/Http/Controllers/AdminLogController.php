<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

final class AdminLogController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate(['id' => ['nullable', 'integer', 'min:1'], 'action' => ['nullable', 'string', 'max:160'], 'actor_id' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $perPage = AdminPagination::perPage($filters['per_page'] ?? null);
        $id = $filters['id'] ?? null;
        $logs = Activity::query()
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'operation')
            ->when($id, fn ($query) => $query->whereKey($id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('event', $action))
            ->when($filters['actor_id'] ?? null, fn ($query, $actorId) => $query->where('causer_id', $actorId))
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Activity $activity) => [
                'id' => $activity->getKey(),
                'action' => $activity->event,
                'description' => $activity->description,
                'actor_id' => $activity->causer_id,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'changes' => $activity->attribute_changes?->toArray() ?? [],
                'context' => $activity->getProperty('context', []),
                'ip_address' => $activity->ip_address,
                'method' => $activity->method,
                'path' => $activity->path,
                'user_agent' => $activity->user_agent,
                'created_at' => $activity->created_at,
            ]);

        return response()->json($logs);
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

    public function destroyLoginBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $ids = array_values(array_map('intval', $validated['ids']));
        $query = Activity::query()
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'login')
            ->whereKey($ids);

        if ((clone $query)->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => ['The selected login logs are invalid.']]);
        }

        DB::transaction(function () use ($ids, $query, $request): void {
            $query->delete();
            $this->audit->record($request->user(), 'system.login-log.batch-deleted', context: [
                'deleted_count' => count($ids),
                'deleted_ids' => $ids,
            ]);
        });

        return response()->json(['deleted_count' => count($ids)]);
    }
}
