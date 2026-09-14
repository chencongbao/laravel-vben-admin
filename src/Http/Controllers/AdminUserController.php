<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\LoginIpWhitelist;
use Chencongbao\LaravelVbenAdmin\Services\TwoFactorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class AdminUserController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly Authorizer $authorizer,
        private readonly TwoFactorAuthentication $twoFactor,
    ) {}

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
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'login_ip_whitelist' => ['sometimes', 'array', 'max:100'],
            'login_ip_whitelist.*' => ['string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! LoginIpWhitelist::isValidRule((string) $value)) {
                    $fail('The login IP whitelist contains an invalid IP address or CIDR range.');
                }
            }],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists(config('laravel-vben-admin.tables.roles', 'admin_roles'), 'id')->where('is_active', true)],
        ]);

        if (array_key_exists('role_ids', $data) && ! $this->authorizer->allows($request->user(), 'system.user.assign-roles')) {
            return response()->json(['message' => 'Role assignment is not allowed.', 'code' => 'ADMIN_PERMISSION_DENIED'], 403);
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
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'login_ip_whitelist' => ['sometimes', 'array', 'max:100'],
            'login_ip_whitelist.*' => ['string', 'max:80', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! LoginIpWhitelist::isValidRule((string) $value)) {
                    $fail('The login IP whitelist contains an invalid IP address or CIDR range.');
                }
            }],
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
}
