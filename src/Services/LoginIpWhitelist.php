<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Symfony\Component\HttpFoundation\IpUtils;

final class LoginIpWhitelist
{
    public function allows(AdminUser $user, ?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        $rules = array_values(array_filter($user->login_ip_whitelist ?? [], 'is_string'));

        return $rules !== [] && IpUtils::checkIp($ip, $rules);
    }

    public static function isValidRule(string $rule): bool
    {
        [$address, $prefix] = array_pad(explode('/', trim($rule), 2), 2, null);
        $version = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 4
            : (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : null);

        if ($version === null) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        return ctype_digit($prefix) && (int) $prefix <= ($version === 4 ? 32 : 128);
    }

    public static function normalize(array $rules): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $rule): string => trim((string) $rule),
            $rules,
        ))));
    }
}
