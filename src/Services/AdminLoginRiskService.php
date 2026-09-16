<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Events\AdminSecurityRiskDetected;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginIpBlock;
use Chencongbao\LaravelVbenAdmin\Models\AdminSecurityEvent;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AdminLoginRiskService
{
    private const WINDOW_SECONDS = 86400;

    public function blocked(Request $request, string $username): ?array
    {
        $ip = (string) ($request->ip() ?? '');
        $databaseBlock = $ip === '' ? null : AdminLoginIpBlock::query()
            ->where('ip_address', $ip)
            ->whereNull('released_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
        if ($databaseBlock !== null) {
            return ['code' => 'LOGIN_IP_BLOCKED', 'retry_after' => $databaseBlock->expires_at?->diffInSeconds(now(), true)];
        }

        $until = (int) Cache::get($this->pairLockKey($ip, $username), 0);
        if ($until > time()) {
            return ['code' => 'LOGIN_TEMPORARILY_LOCKED', 'retry_after' => $until - time()];
        }

        return null;
    }

    public function recordFailure(Request $request, string $username, ?AdminUser $user, string $reason): ?array
    {
        $username = Str::lower(trim($username));
        $ip = (string) ($request->ip() ?? 'unknown');
        $pairCount = $this->increment($this->pairCountKey($ip, $username));
        $this->increment($this->usernameCountKey($username));
        $this->increment($this->ipCountKey($ip));
        $duration = $this->lockDuration($pairCount);

        if ($duration > 0) {
            Cache::put($this->pairLockKey($ip, $username), time() + $duration, $duration);
        }

        $usernamesKey = $this->ipUsernamesKey($ip);
        $usernames = Cache::get($usernamesKey, []);
        $usernames[hash('sha256', $username)] = true;
        Cache::put($usernamesKey, array_slice($usernames, -100, null, true), self::WINDOW_SECONDS);

        if (in_array($pairCount, [5, 10, 20], true)) {
            $this->event('auth.login.risk_threshold', $pairCount >= 20 ? 'critical' : 'warning', $request, $username, $user, [
                'failure_count' => $pairCount,
                'failure_code' => $reason,
                'lock_seconds' => $duration,
            ]);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false && count($usernames) >= 10 && ! $this->hasActiveDatabaseBlock($ip)) {
            AdminLoginIpBlock::query()->create([
                'ip_address' => $ip,
                'reason_code' => 'MULTIPLE_USERNAME_ATTACK',
                'is_automatic' => true,
                'expires_at' => now()->addHours(2),
            ]);
            $this->event('auth.login.ip_auto_blocked', 'critical', $request, $username, $user, [
                'distinct_username_count' => count($usernames),
                'expires_in' => 7200,
            ]);
        }

        return $this->blocked($request, $username);
    }

    public function recordSuccess(Request $request, string $username): void
    {
        $ip = (string) ($request->ip() ?? 'unknown');
        $username = Str::lower(trim($username));
        Cache::forget($this->pairCountKey($ip, $username));
        Cache::forget($this->pairLockKey($ip, $username));
        Cache::forget($this->usernameCountKey($username));
    }

    public function recordEvent(string $code, string $severity, Request $request, string $username, ?AdminUser $user, array $context = []): void
    {
        $this->event($code, $severity, $request, $username, $user, $context);
    }

    private function event(string $code, string $severity, Request $request, string $username, ?AdminUser $user, array $context): void
    {
        $event = AdminSecurityEvent::query()->create([
            'code' => $code,
            'severity' => $severity,
            'admin_user_id' => $user?->getKey(),
            'username' => $username !== '' ? $username : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'context' => $context,
        ]);
        AdminSecurityRiskDetected::dispatch($event);
    }

    private function increment(string $key): int
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, self::WINDOW_SECONDS);
        }
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, self::WINDOW_SECONDS);

        return $count;
    }

    private function lockDuration(int $attempts): int
    {
        return match (true) {
            $attempts >= 20 => 7200,
            $attempts >= 10 => 1800,
            $attempts >= 5 => 600,
            default => 0,
        };
    }

    private function hasActiveDatabaseBlock(string $ip): bool
    {
        return AdminLoginIpBlock::query()->where('ip_address', $ip)->whereNull('released_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    private function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    private function pairCountKey(string $ip, string $username): string
    {
        return 'laravel-vben-admin:login-risk:pair:'.$this->digest($ip.'|'.Str::lower(trim($username)));
    }

    private function pairLockKey(string $ip, string $username): string
    {
        return 'laravel-vben-admin:login-risk:pair-lock:'.$this->digest($ip.'|'.Str::lower(trim($username)));
    }

    private function usernameCountKey(string $username): string
    {
        return 'laravel-vben-admin:login-risk:user:'.$this->digest($username);
    }

    private function ipCountKey(string $ip): string
    {
        return 'laravel-vben-admin:login-risk:ip:'.$this->digest($ip);
    }

    private function ipUsernamesKey(string $ip): string
    {
        return 'laravel-vben-admin:login-risk:ip-users:'.$this->digest($ip);
    }
}
