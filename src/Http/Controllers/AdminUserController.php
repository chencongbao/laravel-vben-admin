<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\LoginIpWhitelist;
use Chencongbao\LaravelVbenAdmin\Services\PrivilegeAssignmentGuard;
use Chencongbao\LaravelVbenAdmin\Services\TwoFactorAuthentication;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Chencongbao\LaravelVbenAdmin\Support\AdminPasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminUserController extends Controller
{
    private const FIXED_ACCOUNT_ROLES = [
        'admin' => 'manager',
        'cmsadmin' => 'administrator',
    ];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PrivilegeAssignmentGuard $privilegeGuard,
        private readonly TwoFactorAuthentication $twoFactor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['id' => ['nullable', 'integer', 'min:1'], 'keyword' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['active', 'disabled'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $isSuperAdmin = $this->privilegeGuard->isSuperAdmin($request->user());
        $users = AdminUser::query()
            ->with('roles:id,code,name')
            ->when(! $isSuperAdmin, fn ($query) => $query->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->where('is_super_admin', true)))
            ->when($validated['id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($validated['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($nested) => $nested->where('username', 'like', "%{$keyword}%")->orWhere('name', 'like', "%{$keyword}%")))
            ->when(isset($validated['status']), fn ($query) => $query->where('is_active', $validated['status'] === 'active'))
            ->latest('id')
            ->paginate(AdminPagination::perPage($validated['per_page'] ?? null));

        return response()->json($users);
    }

    public function roleOptions(): JsonResponse
    {
        return response()->json([
            'roles' => AdminRole::query()
                ->where('is_active', true)
                ->where('is_super_admin', false)
                ->latest('id')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120', Rule::unique(config('laravel-vben-admin.tables.users', 'admin_users'), 'username')],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', AdminPasswordPolicy::rule()],
            'is_active' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'login_ip_whitelist' => ['sometimes', 'array', 'max:100'],
            'login_ip_whitelist.*' => ['string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! LoginIpWhitelist::isValidRule((string) $value)) {
                    $fail('The login IP whitelist contains an invalid IP address or CIDR range.');
                }
            }],
            'role_ids' => ['sometimes', 'array', 'max:1'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'id')->where('is_active', true)],
        ]);

        if (array_key_exists('role_ids', $data)
            && AdminRole::query()->whereKey($data['role_ids'])->where('is_super_admin', true)->exists()) {
            return response()->json(['message' => 'The super administrator role cannot be assigned to a new user.', 'code' => 'ADMIN_SUPER_ROLE_ASSIGNMENT_DENIED'], 422);
        }

        if (array_key_exists('role_ids', $data) && ! $this->privilegeGuard->canAssignRoles($request->user(), $data['role_ids'])) {
            return response()->json(['message' => 'Role assignment exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }

        $user = DB::transaction(function () use ($data, $request): AdminUser {
            $data['login_ip_whitelist'] = LoginIpWhitelist::normalize($data['login_ip_whitelist'] ?? []);
            $user = AdminUser::query()->create(Arr::except($data, ['role_ids', 'two_factor_enabled']));
            if ($data['two_factor_enabled'] ?? false) {
                $this->twoFactor->enable($user);
            }
            $user->roles()->sync($data['role_ids'] ?? []);
            $this->audit->record($request->user(), 'system.user.created', $user, ['after' => Arr::except($data, 'password')]);

            return $user;
        });

        return response()->json(['user' => $user->load('roles:id,code,name')], 201);
    }

    public function show(Request $request, AdminUser $adminUser): JsonResponse
    {
        if (! $this->privilegeGuard->canManageUser($request->user(), $adminUser)) {
            abort(404);
        }

        return response()->json(['user' => $adminUser->load('roles:id,code,name')]);
    }

    public function update(Request $request, AdminUser $adminUser): JsonResponse
    {
        $data = $request->validate([
            'username' => ['sometimes', 'string', 'max:120', Rule::unique(config('laravel-vben-admin.tables.users', 'admin_users'), 'username')->ignore($adminUser->getKey())],
            'name' => ['sometimes', 'string', 'max:120'],
            'password' => ['sometimes', 'string', AdminPasswordPolicy::rule()],
            'is_active' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'login_ip_whitelist' => ['sometimes', 'array', 'max:100'],
            'login_ip_whitelist.*' => ['string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! LoginIpWhitelist::isValidRule((string) $value)) {
                    $fail('The login IP whitelist contains an invalid IP address or CIDR range.');
                }
            }],
            'role_ids' => ['sometimes', 'array', 'max:1'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'id')->where('is_active', true)],
        ]);

        if (! $this->privilegeGuard->canManageUser($request->user(), $adminUser)) {
            return response()->json(['message' => 'This administrator exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }
        $fixedRoleCode = self::FIXED_ACCOUNT_ROLES[$adminUser->username] ?? null;
        if (array_key_exists('username', $data) && $data['username'] !== $adminUser->username) {
            $code = $fixedRoleCode === null ? 'ADMIN_USERNAME_IMMUTABLE' : 'BUILTIN_ADMIN_IDENTITY_PROTECTED';

            return response()->json(['message' => 'Administrator username cannot be modified after creation.', 'code' => $code], 422);
        }
        if ($fixedRoleCode !== null) {
            if (array_key_exists('role_ids', $data)) {
                $fixedRoleId = AdminRole::query()->where('code', $fixedRoleCode)->valueOrFail('id');
                if ($data['role_ids'] !== [$fixedRoleId]) {
                    return response()->json(['message' => 'Built-in administrator role cannot be modified.', 'code' => 'BUILTIN_ADMIN_ROLE_PROTECTED'], 422);
                }
            }
        }
        if ($adminUser->is($request->user())
            && array_key_exists('is_active', $data)
            && (bool) $data['is_active'] !== (bool) $adminUser->is_active) {
            return response()->json(['message' => 'You cannot modify the status of your own account.', 'code' => 'ADMIN_SELF_STATUS_CHANGE_DENIED'], 422);
        }
        if ($adminUser->is($request->user()) && array_key_exists('role_ids', $data)) {
            $currentRoleIds = $adminUser->roles()->pluck('admin_roles.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $submittedRoleIds = collect($data['role_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($submittedRoleIds !== $currentRoleIds) {
                return response()->json(['message' => 'You cannot modify the role of your own account.', 'code' => 'ADMIN_SELF_ROLE_CHANGE_DENIED'], 422);
            }
        }
        if ($adminUser->is($request->user())) {
            unset($data['is_active'], $data['role_ids']);
        }
        if (array_key_exists('role_ids', $data) && ! $this->privilegeGuard->canAssignRoles($request->user(), $data['role_ids'])) {
            return response()->json(['message' => 'Role assignment exceeds your authority.', 'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED'], 403);
        }
        DB::transaction(function () use ($data, $request, $adminUser): void {
            $trackedFields = ['username', 'name', 'is_active', 'two_factor_enabled', 'login_ip_whitelist'];
            $before = $adminUser->only($trackedFields);
            if (array_key_exists('login_ip_whitelist', $data)) {
                $data['login_ip_whitelist'] = LoginIpWhitelist::normalize($data['login_ip_whitelist']);
            }
            $adminUser->fill(Arr::except($data, ['role_ids', 'two_factor_enabled']))->save();
            if (array_key_exists('two_factor_enabled', $data)) {
                $data['two_factor_enabled'] ? $this->twoFactor->enable($adminUser) : $this->twoFactor->disable($adminUser);
            }
            if (array_key_exists('role_ids', $data)) {
                $adminUser->roles()->sync($data['role_ids']);
            }
            $this->audit->record($request->user(), 'system.user.updated', $adminUser, ['before' => $before, 'after' => $adminUser->fresh()->only($trackedFields), 'roles_changed' => array_key_exists('role_ids', $data)]);
        });

        return response()->json(['user' => $adminUser->load('roles:id,code,name')]);
    }

    public function destroy(Request $request, AdminUser $adminUser): JsonResponse
    {
        if (array_key_exists($adminUser->username, self::FIXED_ACCOUNT_ROLES)) {
            return response()->json([
                'message' => 'Built-in administrator accounts cannot be deleted.',
                'code' => 'PROTECTED_ADMIN_USER_DELETE_DENIED',
            ], 422);
        }

        if (! $this->privilegeGuard->canManageUser($request->user(), $adminUser)) {
            return response()->json([
                'message' => 'This administrator exceeds your authority.',
                'code' => 'ADMIN_PRIVILEGE_ESCALATION_DENIED',
            ], 403);
        }

        DB::transaction(function () use ($adminUser, $request): void {
            $this->audit->record($request->user(), 'system.user.deleted', $adminUser, [
                'before' => $adminUser->load('roles:id,code,name')->toArray(),
            ]);
            $adminUser->tokens()->delete();
            $adminUser->delete();
        });

        return response()->json(status: 204);
    }
}
