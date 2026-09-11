<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Models\AdminAuditLog;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AdminLogController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $logs = AdminAuditLog::query()
            ->when($request->string('action')->toString(), fn ($query, $action) => $query->where('action', $action))
            ->when($request->integer('actor_id'), fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->latest('id')
            ->paginate($perPage);

        return response()->json($logs);
    }

    public function login(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $logs = AdminLoginLog::query()
            ->when($request->string('username')->toString(), fn ($query, $username) => $query->where('username', 'like', "%{$username}%"))
            ->when($request->has('succeeded'), fn ($query) => $query->where('succeeded', $request->boolean('succeeded')))
            ->latest('id')
            ->paginate($perPage);

        return response()->json($logs);
    }
}
