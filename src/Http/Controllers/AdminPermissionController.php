<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminPermissionController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $perPage = AdminPagination::perPage($validated['per_page'] ?? null);
        $id = $validated['id'] ?? null;

        return response()->json(AdminPermission::query()
            ->with('menus:id,code,title')
            ->when($id, fn ($query) => $query->whereKey($id))
            ->orderBy('sort')->orderBy('id')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $menuIds = $data['menu_ids'] ?? [];
        unset($data['menu_ids']);
        $permission = DB::transaction(function () use ($data, $menuIds, $request): AdminPermission {
            $permission = AdminPermission::query()->create($data + ['is_system' => false, 'is_deprecated' => false]);
            $permission->menus()->sync($menuIds);
            $this->audit->record($request->user(), 'system.permission.created', $permission, [
                'after' => $data + ['menu_ids' => $menuIds],
            ]);

            return $permission;
        });

        return response()->json(['permission' => $permission->load('menus:id,code,title')], 201);
    }

    public function show(AdminPermission $adminPermission): JsonResponse
    {
        return response()->json(['permission' => $adminPermission->load('menus:id,code,title')]);
    }

    public function update(Request $request, AdminPermission $adminPermission): JsonResponse
    {
        $changesSystemIdentity = $adminPermission->is_system && (
            ($request->has('code') && $request->string('code')->toString() !== $adminPermission->code)
            || ($request->has('is_active') && $request->boolean('is_active') !== $adminPermission->is_active)
        );
        if ($changesSystemIdentity) {
            return response()->json(['message' => 'System permission identity cannot be modified.', 'code' => 'SYSTEM_PERMISSION_PROTECTED'], 422);
        }

        $data = $this->validated($request, $adminPermission);
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $adminPermission->parent_id;
        if ($this->createsCycle($adminPermission, $parentId)) {
            return response()->json(['message' => 'The permission hierarchy contains a cycle.', 'code' => 'PERMISSION_CYCLE'], 422);
        }

        $menuIds = $data['menu_ids'] ?? null;
        unset($data['menu_ids']);
        $before = $adminPermission->load('menus:id')->toArray();
        DB::transaction(function () use ($adminPermission, $before, $data, $menuIds, $request): void {
            $adminPermission->update($data);
            if ($menuIds !== null) {
                $adminPermission->menus()->sync($menuIds);
            }
            $after = $adminPermission->fresh()->load('menus:id')->toArray();
            $this->audit->record($request->user(), 'system.permission.updated', $adminPermission, ['before' => $before, 'after' => $after]);
        });

        return response()->json(['permission' => $adminPermission->fresh()->load('menus:id,code,title')]);
    }

    public function destroy(Request $request, AdminPermission $adminPermission): JsonResponse
    {
        if ($adminPermission->is_system) {
            return response()->json(['message' => 'System permissions cannot be deleted.', 'code' => 'SYSTEM_PERMISSION_PROTECTED'], 422);
        }
        if ($adminPermission->roles()->exists()) {
            return response()->json(['message' => 'The permission is assigned to roles.', 'code' => 'PERMISSION_IN_USE'], 422);
        }
        if ($adminPermission->children()->exists()) {
            return response()->json(['message' => 'Delete or move child permissions first.', 'code' => 'PERMISSION_HAS_CHILDREN'], 422);
        }

        $this->audit->record($request->user(), 'system.permission.deleted', $adminPermission, ['before' => $adminPermission->toArray()]);
        $adminPermission->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?AdminPermission $permission = null): array
    {
        $permissionTable = config('laravel-vben-admin.tables.permissions', 'admin_permissions');
        $menuTable = config('laravel-vben-admin.tables.menus', 'admin_menus');

        return $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists($permissionTable, 'id')],
            'code' => [$permission ? 'sometimes' : 'required', 'string', 'max:160', 'regex:/^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/', Rule::unique($permissionTable, 'code')->ignore($permission?->getKey())],
            'name' => [$permission ? 'sometimes' : 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'http_methods' => ['nullable', 'array', 'max:10'],
            'http_methods.*' => ['required', 'string', 'distinct', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])],
            'http_paths' => ['nullable', 'array', 'max:50'],
            'http_paths.*' => ['required', 'string', 'distinct', 'max:255', 'regex:/^\/[A-Za-z0-9._~!$&\'()*+,;=:@%{}\/-]*$/'],
            'sort' => ['sometimes', 'integer', 'min:-100000', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'menu_ids' => ['sometimes', 'array', 'max:500'],
            'menu_ids.*' => ['required', 'integer', 'distinct', Rule::exists($menuTable, 'id')],
        ]);
    }

    private function createsCycle(AdminPermission $permission, ?int $parentId): bool
    {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === $permission->getKey() || isset($visited[$parentId])) {
                return true;
            }
            $visited[$parentId] = true;
            $parentId = AdminPermission::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
