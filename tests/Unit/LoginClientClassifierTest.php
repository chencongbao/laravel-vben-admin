<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Services\LoginClientClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoginClientClassifierTest extends TestCase
{
    #[DataProvider('userAgents')]
    public function test_it_classifies_login_clients(?string $userAgent, string $expected): void
    {
        self::assertSame($expected, (new LoginClientClassifier)->classify($userAgent));
    }

    public static function userAgents(): array
    {
        return [
            'desktop mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'desktop'],
            'desktop windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'desktop'],
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile', 'mobile'],
            'android phone' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) Mobile', 'mobile'],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X)', 'tablet'],
            'android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X910)', 'tablet'],
            'api client' => ['PostmanRuntime/7.43.0', 'api'],
            'other client' => ['CustomAgent/1.0', 'other'],
            'unknown client' => [null, 'unknown'],
        ];
    }
}
