<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

final class AuthController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

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

        $accessToken = $user->createToken(config('laravel-vben-admin.auth.token_name', 'vben-admin'), ['admin']);
        $accessToken->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
        ])->save();

        return response()->json([
            'token' => $accessToken->plainTextToken,
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

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isAllowedAvatar((string) $value)) {
                    $fail('The selected avatar is invalid.');
                }
            }],
        ]);
        $before = $user->only(['name', 'avatar']);
        $oldAvatar = $user->avatar;
        $user->update($data);
        if ($oldAvatar !== $user->avatar) {
            $this->deleteUploadedAvatar($user, $oldAvatar);
        }
        $this->audit->record($user, 'auth.profile.updated', $user, ['before' => $before, 'after' => $user->only(['name', 'avatar'])]);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function avatars(): JsonResponse
    {
        return response()->json(['avatars' => collect($this->defaultAvatarIds())->map(fn (string $id) => [
            'id' => 'default:'.$id,
            'url' => $this->defaultAvatarUrl($id),
        ])]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'],
        ]);

        $oldAvatar = $user->avatar;
        $file = $request->file('avatar');
        $filename = Str::uuid()->toString().'.'.$file->extension();
        $path = $file->storeAs('laravel-vben-admin/avatars/'.$user->getKey(), $filename, 'public');
        $avatar = Storage::disk('public')->url($path);
        $user->forceFill(['avatar' => $avatar])->save();
        $this->deleteUploadedAvatar($user, $oldAvatar);
        $this->audit->record($user, 'auth.avatar.updated', $user, ['before' => ['avatar' => $oldAvatar], 'after' => ['avatar' => $avatar]]);

        return response()->json(['avatar' => $avatar, 'user' => $this->userPayload($user)]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }

        $user->forceFill(['password' => $data['password']])->save();
        $currentTokenId = $user->currentAccessToken()?->getKey();
        if ($currentTokenId !== null) {
            $user->tokens()->where('id', '<>', $currentTokenId)->delete();
        }
        $this->audit->record($user, 'auth.password.updated', $user, ['other_tokens_revoked' => true]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->getKey();
        $sessions = $user->tokens()->latest('id')->get()->map(fn ($token) => [
            'id' => $token->getKey(),
            'current' => $token->getKey() === $currentTokenId,
            'ip_address' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
        ]);

        return response()->json(['sessions' => $sessions]);
    }

    public function destroySession(Request $request, int $tokenId): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        if ($user->currentAccessToken()?->getKey() === $tokenId) {
            return response()->json(['message' => 'Use logout to revoke the current session.', 'code' => 'CURRENT_SESSION_PROTECTED'], 422);
        }

        $token = $user->tokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();
        $this->audit->record($user, 'auth.session.revoked', $user, [], ['token_id' => $tokenId]);

        return response()->json(status: 204);
    }

    public function destroyOtherSessions(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->getKey();
        $query = $user->tokens();
        if ($currentTokenId !== null) {
            $query->where('id', '<>', $currentTokenId);
        }
        $revoked = $query->delete();
        $this->audit->record($user, 'auth.sessions.revoked', $user, [], ['revoked_count' => $revoked]);

        return response()->json(['revoked_count' => $revoked]);
    }

    private function userPayload(AdminUser $user): array
    {
        $avatar = $user->avatar;
        if (is_string($avatar) && str_starts_with($avatar, 'default:')) {
            $avatar = $this->defaultAvatarUrl(Str::after($avatar, 'default:'));
        }

        return ['id' => $user->getKey(), 'username' => $user->username, 'name' => $user->name, 'avatar' => $avatar, 'is_active' => $user->is_active];
    }

    private function defaultAvatarIds(): array
    {
        return ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'];
    }

    private function defaultAvatarUrl(string $id): string
    {
        return '/'.trim((string) config('laravel-vben-admin.path', 'admin'), '/').'/avatars/'.$id.'.svg';
    }

    private function isAllowedAvatar(string $avatar): bool
    {
        if (str_starts_with($avatar, 'default:')) {
            return in_array(Str::after($avatar, 'default:'), $this->defaultAvatarIds(), true);
        }

        if (str_starts_with($avatar, Storage::disk('public')->url('laravel-vben-admin/avatars/'))) {
            return true;
        }

        return filter_var($avatar, FILTER_VALIDATE_URL) !== false;
    }

    private function deleteUploadedAvatar(AdminUser $user, ?string $avatar): void
    {
        if (! is_string($avatar)) {
            return;
        }

        $prefix = Storage::disk('public')->url('laravel-vben-admin/avatars/'.$user->getKey().'/');
        if (! str_starts_with($avatar, $prefix)) {
            return;
        }

        Storage::disk('public')->delete(Str::after($avatar, Storage::disk('public')->url('')));
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
