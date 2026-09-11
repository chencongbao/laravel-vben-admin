<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class AdminUserController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit, private readonly Authorizer $authorizer) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['keyword' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['active', 'disabled'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $users = AdminUser::query()->with('roles:id,code,name')->when($validated['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($nested) => $nested->where('username', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")))->when(isset($validated['status']), fn ($query) => $query->where('is_active', $validated['status'] === 'active'))->latest('id')->paginate($validated['per_page'] ?? 20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120', Rule::unique(config('laravel-vben-admin.tables.users', 'admin_users'), 'username')],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'id')->where('is_active', true)],
        ]);

        if (array_key_exists('role_ids', $data) && ! $this->authorizer->allows($request->user(), 'system.user.assign-roles')) {
            return response()->json(['message' => 'Role assignment is not allowed.', 'code' => 'ADMIN_PERMISSION_DENIED'], 403);
        }

        $user = DB::transaction(function () use ($data, $request): AdminUser {
            $user = AdminUser::query()->create(Arr::except($data, 'role_ids'));
            $user->roles()->sync($data['role_ids'] ?? []);
            $this->audit->record($request->user(), 'system.user.created', $user, ['after' => Arr::except($data, 'password')]);

            return $user;
        });

        return response()->json(['user' => $user->load('roles:id,code,name')], 201);
    }

    public function show(AdminUser $adminUser): JsonResponse
    {
        return response()->json(['user' => $adminUser->load('roles:id,code,name')]);
    }

    public function update(Request $request, AdminUser $adminUser): JsonResponse
    {
        $data = $request->validate([
            'username' => ['sometimes', 'string', 'max:120', Rule::unique(config('laravel-vben-admin.tables.users', 'admin_users'), 'username')->ignore($adminUser->getKey())],
            'name' => ['sometimes', 'string', 'max:120'],
            'password' => ['sometimes', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'id')->where('is_active', true)],
        ]);

        if ($adminUser->is($request->user()) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json(['message' => 'You cannot disable your own account.', 'code' => 'ADMIN_SELF_DISABLE_DENIED'], 422);
        }
        if (array_key_exists('role_ids', $data) && ! $this->authorizer->allows($request->user(), 'system.user.assign-roles')) {
            return response()->json(['message' => 'Role assignment is not allowed.', 'code' => 'ADMIN_PERMISSION_DENIED'], 403);
        }
        if ($adminUser->is($request->user()) && array_key_exists('role_ids', $data)) {
            $superRoleIds = AdminRole::query()->where('is_super_admin', true)->pluck('id')->all();
            if ($adminUser->roles()->where('is_super_admin', true)->exists() && ! array_intersect($superRoleIds, $data['role_ids'])) {
                return response()->json(['message' => 'You cannot remove your own super administrator role.', 'code' => 'ADMIN_SELF_DEMOTION_DENIED'], 422);
            }
        }

        DB::transaction(function () use ($data, $request, $adminUser): void {
            $before = $adminUser->only(['username', 'name', 'is_active']);
            $adminUser->fill(Arr::except($data, 'role_ids'))->save();
            if (array_key_exists('role_ids', $data)) {
                $adminUser->roles()->sync($data['role_ids']);
            }
            $this->audit->record($request->user(), 'system.user.updated', $adminUser, ['before' => $before, 'after' => $adminUser->only(['username', 'name', 'is_active']), 'roles_changed' => array_key_exists('role_ids', $data)]);
        });

        return response()->json(['user' => $adminUser->load('roles:id,code,name')]);
    }
}
