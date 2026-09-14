<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\LoginIpWhitelist;
use PHPUnit\Framework\TestCase;

final class LoginIpWhitelistTest extends TestCase
{
    public function test_it_accepts_exact_addresses_and_cidr_ranges(): void
    {
        $service = new LoginIpWhitelist;
        $user = new AdminUser([
            'login_ip_whitelist' => ['203.0.113.8', '10.20.0.0/16', '2001:db8::/32'],
        ]);

        self::assertTrue($service->allows($user, '203.0.113.8'));
        self::assertTrue($service->allows($user, '10.20.5.9'));
        self::assertTrue($service->allows($user, '2001:db8::10'));
        self::assertFalse($service->allows($user, '198.51.100.1'));
    }

    public function test_empty_whitelist_denies_access(): void
    {
        $service = new LoginIpWhitelist;

        self::assertFalse($service->allows(new AdminUser([
            'login_ip_whitelist' => [],
        ]), '127.0.0.1'));
    }

    public function test_it_validates_supported_ip_rules(): void
    {
        self::assertTrue(LoginIpWhitelist::isValidRule('127.0.0.1'));
        self::assertTrue(LoginIpWhitelist::isValidRule('10.0.0.0/8'));
        self::assertTrue(LoginIpWhitelist::isValidRule('2001:db8::/32'));
        self::assertFalse(LoginIpWhitelist::isValidRule('10.0.0.0/33'));
        self::assertFalse(LoginIpWhitelist::isValidRule('not-an-ip'));
    }
}
