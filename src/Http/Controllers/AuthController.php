<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string', 'max:120'], 'password' => ['required', 'string']]);

        /** @var class-string<AdminUser> $model */
        $model = config('laravel-vben-admin.auth.model', AdminUser::class);
        $user = $model::query()->where('username', $credentials['username'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            $this->writeLoginLog($request, $credentials['username'], $user, false, $user && ! $user->is_active ? 'ACCOUNT_DISABLED' : 'INVALID_CREDENTIALS');
            throw ValidationException::withMessages(['username' => ['The provided credentials are invalid.']]);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->writeLoginLog($request, $credentials['username'], $user, true);

        return response()->json([
            'token' => $user->createToken(config('laravel-vben-admin.auth.token_name', 'vben-admin'), ['admin'])->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userPayload(AdminUser $user): array
    {
        return ['id' => $user->getKey(), 'username' => $user->username, 'name' => $user->name, 'avatar' => $user->avatar, 'is_active' => $user->is_active];
    }

    private function writeLoginLog(Request $request, string $username, ?AdminUser $user, bool $succeeded, ?string $failureCode = null): void
    {
        AdminLoginLog::query()->create([
            'user_id' => $user?->getKey(),
            'username' => $username,
            'succeeded' => $succeeded,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'failure_code' => $failureCode,
        ]);
    }
}
