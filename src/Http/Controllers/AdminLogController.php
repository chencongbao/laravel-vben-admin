<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Activitylog\Models\Activity;

final class AdminLogController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $logs = Activity::query()
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'operation')
            ->when($request->string('action')->toString(), fn ($query, $action) => $query->where('event', $action))
            ->when($request->integer('actor_id'), fn ($query, $actorId) => $query->where('causer_id', $actorId))
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
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $logs = Activity::query()
            ->where('log_name', config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->where('log_type', 'login')
            ->when($request->string('username')->toString(), fn ($query, $username) => $query->where('properties->username', 'like', "%{$username}%"))
            ->when($request->has('succeeded'), fn ($query) => $query->where('properties->succeeded', $request->boolean('succeeded')))
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
