<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Services\PrivilegeAssignmentGuard;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminRoleController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit, private readonly PrivilegeAssignmentGuard $privilegeGuard) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $perPage = AdminPagination::perPage($validated['per_page'] ?? null);
        $id = $validated['id'] ?? null;

        return response()->json(AdminRole::query()->withCount('permissions', 'menus')->when($id, fn ($query) => $query->whereKey($id))->orderBy('id')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRole($request);
        $access = $this->validateAccess($request);
        if (! $this->privilegeGuard->canAssignPermissions($request->user(), $access['permission_ids'])) {
            return response()->json(['message' => 'Permission assignment exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }

        $role = DB::transaction(function () use ($access, $data, $request): AdminRole {
            $role = AdminRole::query()->create($data + ['is_system' => false, 'is_super_admin' => false]);
            $role->permissions()->sync($access['permission_ids']);
            $role->menus()->sync($access['menu_ids']);
            $this->audit->record($request->user(), 'system.role.created', $role, ['after' => $data + $access]);

            return $role;
        });

        return response()->json(['role' => $role->load('permissions:id,code,name', 'menus:id,code,title')], 201);
    }

    public function show(AdminRole $adminRole): JsonResponse
    {
        return response()->json(['role' => $adminRole->load('permissions:id,code,name', 'menus:id,code,title')]);
    }

    public function update(Request $request, AdminRole $adminRole): JsonResponse
    {
        if ($adminRole->is_super_admin) {
            return response()->json(['message' => 'System roles cannot be modified.', 'code' => 'SYSTEM_ROLE_PROTECTED'], 422);
        }

        $data = $this->validateRole($request, $adminRole);
        $access = $this->validateAccess($request);
        if (! $this->privilegeGuard->canAssignPermissions($request->user(), $access['permission_ids'])) {
            return response()->json(['message' => 'Permission assignment exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }
        $before = $adminRole->only(['code', 'name', 'is_active']);
        if ($adminRole->is_system) {
            unset($data['code'], $data['name'], $data['is_active']);
        }

        DB::transaction(function () use ($access, $adminRole, $before, $data, $request): void {
            $adminRole->update($data);
            $adminRole->permissions()->sync($access['permission_ids']);
            $adminRole->menus()->sync($access['menu_ids']);
            $this->audit->record($request->user(), 'system.role.updated', $adminRole, [
                'before' => $before,
                'after' => $adminRole->only(['code', 'name', 'is_active']) + $access,
            ]);
        });

        return response()->json(['role' => $adminRole->load('permissions:id,code,name', 'menus:id,code,title')]);
    }

    public function destroy(Request $request, AdminRole $adminRole): JsonResponse
    {
        if ($adminRole->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.', 'code' => 'SYSTEM_ROLE_PROTECTED'], 422);
        }
        if ($adminRole->users()->exists()) {
            return response()->json(['message' => 'The role is assigned to administrators.', 'code' => 'ROLE_IN_USE'], 422);
        }

        DB::transaction(function () use ($request, $adminRole): void {
            $this->audit->record($request->user(), 'system.role.deleted', $adminRole, ['before' => $adminRole->toArray()]);
            $adminRole->delete();
        });

        return response()->json(status: 204);
    }

    public function access(Request $request, AdminRole $adminRole): JsonResponse
    {
        if ($adminRole->is_super_admin) {
            return response()->json(['message' => 'System role access cannot be modified.', 'code' => 'SYSTEM_ROLE_PROTECTED'], 422);
        }

        $data = $request->validate([
            'permission_ids' => ['required', 'array', 'max:500'], 'permission_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.permissions', 'admin_permissions'), 'id')->where('is_active', true)],
            'menu_ids' => ['required', 'array', 'max:500'], 'menu_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.menus', 'admin_menus'), 'id')],
        ]);
        if (! $this->privilegeGuard->canAssignPermissions($request->user(), $data['permission_ids'])) {
            return response()->json(['message' => 'Permission assignment exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }

        DB::transaction(function () use ($data, $request, $adminRole): void {
            $adminRole->permissions()->sync($data['permission_ids']);
            $adminRole->menus()->sync($data['menu_ids']);
            $this->audit->record($request->user(), 'system.role.access-updated', $adminRole, ['permission_ids' => $data['permission_ids'], 'menu_ids' => $data['menu_ids']]);
        });

        return response()->json(['role' => $adminRole->load('permissions:id,code,name', 'menus:id,code,title')]);
    }

    private function validateRole(Request $request, ?AdminRole $role = null): array
    {
        return $request->validate([
            'code' => [$role ? 'sometimes' : 'required', 'string', 'max:120', Rule::unique(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'code')->ignore($role?->getKey())],
            'name' => [$role ? 'sometimes' : 'required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateAccess(Request $request): array
    {
        return $request->validate([
            'permission_ids' => ['required', 'array', 'min:1', 'max:500'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.permissions', 'admin_permissions'), 'id')->where('is_active', true)],
            'menu_ids' => ['required', 'array', 'min:1', 'max:500'],
            'menu_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.menus', 'admin_menus'), 'id')],
        ]);
    }
}
