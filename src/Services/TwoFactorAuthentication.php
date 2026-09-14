<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorAuthentication
{
    private const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function enable(AdminUser $user): void
    {
        if ($user->two_factor_enabled && is_string($user->two_factor_secret) && $user->two_factor_secret !== '') {
            return;
        }

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function disable(AdminUser $user): void
    {
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function issueChallenge(AdminUser $user): array
    {
        if (! is_string($user->two_factor_secret) || $user->two_factor_secret === '') {
            $this->enable($user);
            $user->refresh();
        }

        $token = Str::random(64);
        Cache::put($this->challengeKey($token), ['user_id' => $user->getKey()], now()->addSeconds(self::CHALLENGE_TTL_SECONDS));

        $result = [
            'challenge_token' => $token,
            'expires_in' => self::CHALLENGE_TTL_SECONDS,
            'setup_required' => $user->two_factor_confirmed_at === null,
        ];

        if ($result['setup_required']) {
            $result['secret'] = $user->two_factor_secret;
            $result['qr_code'] = $this->qrCodeDataUrl($user);
        }

        return $result;
    }

    public function resolveChallenge(string $token): ?AdminUser
    {
        $challenge = Cache::get($this->challengeKey($token));

        if (! is_array($challenge) || ! isset($challenge['user_id'])) {
            return null;
        }

        /** @var class-string<AdminUser> $model */
        $model = config('laravel-vben-admin.auth.model', AdminUser::class);

        return $model::query()->find($challenge['user_id']);
    }

    public function verify(AdminUser $user, string $code): bool
    {
        return is_string($user->two_factor_secret)
            && $this->google2fa->verifyKey($user->two_factor_secret, $code, 1);
    }

    public function consumeChallenge(string $token): void
    {
        Cache::forget($this->challengeKey($token));
    }

    private function challengeKey(string $token): string
    {
        return 'laravel-vben-admin:2fa-challenge:'.hash('sha256', $token);
    }

    private function qrCodeDataUrl(AdminUser $user): string
    {
        $issuer = (string) config('app.name', 'Laravel Vben Admin');
        $uri = $this->google2fa->getQRCodeUrl($issuer, $user->username, $user->two_factor_secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 2), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($uri));
    }
}
