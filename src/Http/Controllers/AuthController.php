<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\LoginRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\LoginCaptcha;
use Chencongbao\LaravelVbenAdmin\Services\LoginClientClassifier;
use Chencongbao\LaravelVbenAdmin\Services\LoginIpWhitelist;
use Chencongbao\LaravelVbenAdmin\Services\TwoFactorAuthentication;
use Chencongbao\LaravelVbenAdmin\Support\AdminPasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly LoginRecorder $loginRecorder,
        private readonly LoginCaptcha $captcha,
        private readonly LoginClientClassifier $loginClientClassifier,
        private readonly LoginIpWhitelist $loginIpWhitelist,
        private readonly TwoFactorAuthentication $twoFactor,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'captcha_key' => ['nullable', 'string', 'max:80'],
            'captcha' => ['nullable', 'array', 'size:3'],
            'captcha.*.x' => ['required_with:captcha', 'integer', 'between:0,'.LoginCaptcha::WIDTH],
            'captcha.*.y' => ['required_with:captcha', 'integer', 'between:0,'.LoginCaptcha::HEIGHT],
            'captcha.*.i' => ['required_with:captcha', 'integer', 'between:0,2'],
            'captcha.*.t' => ['required_with:captcha', 'integer'],
        ]);

        if ($this->captcha->isRequired($request, $credentials['username'])
            && ! $this->captcha->verify($request, $credentials['username'], $credentials['captcha_key'] ?? null, $credentials['captcha'] ?? null)) {
            $this->loginRecorder->record($request, $credentials['username'], null, false, 'CAPTCHA_INVALID');

            return response()->json([
                'message' => 'The verification code is invalid or has expired.',
                'code' => 'CAPTCHA_INVALID',
                'captcha_required' => true,
            ], 422);
        }

        /** @var class-string<AdminUser> $model */
        $model = config('laravel-vben-admin.auth.model', AdminUser::class);
        $user = $model::query()->where('username', $credentials['username'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            $this->captcha->require($request, $credentials['username']);
            $this->loginRecorder->record($request, $credentials['username'], $user, false, $user && ! $user->is_active ? 'ACCOUNT_DISABLED' : 'INVALID_CREDENTIALS');

            return response()->json([
                'message' => 'The provided credentials are invalid.',
                'code' => 'INVALID_CREDENTIALS',
                'captcha_required' => true,
            ], 422);
        }

        $this->captcha->clear($request, $credentials['username']);

        if (! app()->environment('local') && ! $this->loginIpWhitelist->allows($user, $request->ip())) {
            $this->loginRecorder->record($request, $user->username, $user, false, 'LOGIN_IP_NOT_ALLOWED');

            return response()->json([
                'message' => '当前 IP 不在登录白名单中。',
                'code' => 'LOGIN_IP_NOT_ALLOWED',
            ], 403);
        }

        if ($user->two_factor_enabled && ! app()->environment('local')) {
            return response()->json([
                'two_factor_required' => true,
                ...$this->twoFactor->issueChallenge($user),
            ], 202);
        }

        return $this->completeLogin($request, $user, $credentials['username']);
    }

    public function completeTwoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $user = $this->twoFactor->resolveChallenge($data['challenge_token']);

        if (! $user || ! $user->is_active || ! $user->two_factor_enabled) {
            $this->loginRecorder->record($request, '', null, false, 'TWO_FACTOR_CHALLENGE_INVALID');

            return response()->json(['message' => 'The two-factor challenge is invalid or has expired.', 'code' => 'TWO_FACTOR_CHALLENGE_INVALID'], 422);
        }

        if (! app()->environment('local') && ! $this->loginIpWhitelist->allows($user, $request->ip())) {
            $this->loginRecorder->record($request, $user->username, $user, false, 'LOGIN_IP_NOT_ALLOWED');

            return response()->json(['message' => '当前 IP 不在登录白名单中。', 'code' => 'LOGIN_IP_NOT_ALLOWED'], 403);
        }

        if (! $this->twoFactor->verify($user, $data['code'])) {
            $this->loginRecorder->record($request, $user->username, $user, false, 'TWO_FACTOR_INVALID');

            return response()->json(['message' => 'The authentication code is invalid.', 'code' => 'TWO_FACTOR_CODE_INVALID'], 422);
        }

        $setupCompleted = $user->two_factor_confirmed_at === null;
        if ($setupCompleted) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
            $this->audit->record($user, 'auth.two-factor.confirmed', $user);
        }
        $this->twoFactor->consumeChallenge($data['challenge_token']);

        return $this->completeLogin($request, $user, $user->username);
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();

        return response()->json(['two_factor' => [
            'enabled' => $user->two_factor_enabled,
            'confirmed' => $user->two_factor_confirmed_at !== null,
        ]]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $this->twoFactor->enable($user);
        $user->tokens()->where('id', '<>', $user->currentAccessToken()?->getKey())->delete();
        $this->audit->record($user, 'auth.two-factor.enabled', $user);

        return response()->json(['message' => 'Two-factor authentication will be configured at the next login.']);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $rules = ['code' => [$user->two_factor_confirmed_at ? 'required' : 'nullable', 'string', 'regex:/^\d{6}$/']];
        $data = $request->validate($rules);

        if ($user->two_factor_confirmed_at && ! $this->twoFactor->verify($user, (string) ($data['code'] ?? ''))) {
            throw ValidationException::withMessages(['code' => ['The authentication code is invalid.']]);
        }

        $this->twoFactor->disable($user);
        $user->tokens()->where('id', '<>', $user->currentAccessToken()?->getKey())->delete();
        $this->audit->record($user, 'auth.two-factor.disabled', $user);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    private function completeLogin(Request $request, AdminUser $user, string $username): JsonResponse
    {
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->loginRecorder->record($request, $username, $user, true);

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

    public function captcha(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:120']]);

        return response()->json($this->captcha->issue($request, $data['username']));
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
        $after = $user->only(['name', 'avatar']);
        $nameChanged = $before['name'] !== $after['name'];
        $avatarChanged = $before['avatar'] !== $after['avatar'];

        if ($avatarChanged) {
            $this->deleteUploadedAvatar($user, $oldAvatar);
        }
        if ($nameChanged) {
            $this->audit->record($user, 'auth.profile.updated', $user, [
                'before' => ['name' => $before['name']],
                'after' => ['name' => $after['name']],
            ]);
        }
        if ($avatarChanged) {
            $this->audit->record($user, 'auth.avatar.updated', $user, [
                'before' => ['avatar' => $before['avatar']],
                'after' => ['avatar' => $after['avatar']],
            ]);
        }

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function avatars(): JsonResponse
    {
        return response()->json(['avatars' => collect($this->defaultAvatarIds())->map(fn (string $id) => [
            'id' => 'default:'.$id,
            'url' => $this->defaultAvatarUrl($id),
        ])]);
    }

    public function passwordPolicy(): JsonResponse
    {
        return response()->json(['password_policy' => AdminPasswordPolicy::payload()]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'],
        ], [
            'avatar.required' => '请选择要上传的头像图片。',
            'avatar.uploaded' => '头像上传失败，请确认文件不超过 2 MB 后重试。',
            'avatar.image' => '头像必须是有效的图片文件。',
            'avatar.mimes' => '头像仅支持 JPG、PNG 或 WebP 格式。',
            'avatar.max' => '头像文件不能超过 2 MB。',
            'avatar.dimensions' => '头像尺寸必须在 64×64 至 4096×4096 像素之间。',
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
            'password' => ['required', 'confirmed', 'different:current_password', AdminPasswordPolicy::rule()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'code' => 'CURRENT_PASSWORD_INCORRECT',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
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
            'client_type' => $this->loginClientClassifier->classify($token->user_agent),
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
        $user->loadMissing('roles');
        $avatar = $user->avatar;
        if (is_string($avatar) && str_starts_with($avatar, 'default:')) {
            $avatar = $this->defaultAvatarUrl(Str::after($avatar, 'default:'));
        }

        return [
            'id' => $user->getKey(),
            'username' => $user->username,
            'name' => $user->name,
            'avatar' => $avatar,
            'is_active' => $user->is_active,
            'roles' => $user->roles->map(fn ($role) => [
                'code' => $role->code,
                'name' => $role->name,
            ])->values(),
        ];
    }

    private function defaultAvatarIds(): array
    {
        return array_map(static fn (int $number): string => 'avatar-'.$number, range(1, 30));
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
}
