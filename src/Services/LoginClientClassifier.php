<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

final class LoginClientClassifier
{
    public function classify(?string $userAgent): string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return 'unknown';
        }

        if (preg_match('/curl|postmanruntime|httpie|insomnia/i', $userAgent)) {
            return 'api';
        }

        if (preg_match('/ipad|tablet|kindle|silk|android(?!.*mobile)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|windows phone/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/windows nt|macintosh|x11|cros|linux/i', $userAgent)) {
            return 'desktop';
        }

        return 'other';
    }
}
