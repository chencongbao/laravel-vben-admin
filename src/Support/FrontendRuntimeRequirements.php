<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

final class FrontendRuntimeRequirements
{
    public const NODE = '^22.18.0 || ^24.0.0';

    public const PNPM = '>=10.0.0';

    public static function supportsNode(string $version): bool
    {
        $normalized = preg_replace('/[-+].*$/', '', $version) ?? $version;
        $parts = explode('.', $normalized);

        if (count($parts) < 3) {
            return false;
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];

        return ($major === 22 && $minor >= 18) || $major === 24;
    }

    public static function supportsPnpm(string $version): bool
    {
        $normalized = preg_replace('/[-+].*$/', '', $version) ?? $version;

        return version_compare($normalized, '10.0.0', '>=');
    }
}
