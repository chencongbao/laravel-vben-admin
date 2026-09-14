<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class AdminPermissionController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $id = $request->validate(['id' => ['nullable', 'integer', 'min:1']])['id'] ?? null;

        return response()->json(AdminPermission::query()->when($id, fn ($query) => $query->whereKey($id))->orderBy('code')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $permission = AdminPermission::query()->create($data + ['is_system' => false, 'is_deprecated' => false]);
        $this->audit->record($request->user(), 'system.permission.created', $permission, ['after' => $data]);

        return response()->json(['permission' => $permission], 201);
    }

    public function show(AdminPermission $adminPermission): JsonResponse
    {
        return response()->json(['permission' => $adminPermission]);
    }

    public function update(Request $request, AdminPermission $adminPermission): JsonResponse
    {
        if ($adminPermission->is_system && $request->hasAny(['code', 'is_active'])) {
            return response()->json(['message' => 'System permission identity cannot be modified.', 'code' => 'SYSTEM_PERMISSION_PROTECTED'], 422);
        }

        $data = $this->validated($request, $adminPermission);
        $before = $adminPermission->only(['code', 'name', 'is_active', 'is_sensitive']);
        $adminPermission->update($data);
        $this->audit->record($request->user(), 'system.permission.updated', $adminPermission, ['before' => $before, 'after' => $adminPermission->only(['code', 'name', 'is_active', 'is_sensitive'])]);

        return response()->json(['permission' => $adminPermission]);
    }

    public function destroy(Request $request, AdminPermission $adminPermission): JsonResponse
    {
        if ($adminPermission->is_system) {
            return response()->json(['message' => 'System permissions cannot be deleted.', 'code' => 'SYSTEM_PERMISSION_PROTECTED'], 422);
        }
        if ($adminPermission->roles()->exists()) {
            return response()->json(['message' => 'The permission is assigned to roles.', 'code' => 'PERMISSION_IN_USE'], 422);
        }

        $this->audit->record($request->user(), 'system.permission.deleted', $adminPermission, ['before' => $adminPermission->toArray()]);
        $adminPermission->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?AdminPermission $permission = null): array
    {
        return $request->validate([
            'code' => [$permission ? 'sometimes' : 'required', 'string', 'max:160', 'regex:/^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/', Rule::unique(config('laravel-vben-admin.tables.permissions', 'admin_permissions'), 'code')->ignore($permission?->getKey())],
            'name' => [$permission ? 'sometimes' : 'required', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ]);
    }
}
