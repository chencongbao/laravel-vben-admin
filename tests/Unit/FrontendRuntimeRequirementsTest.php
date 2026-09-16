<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Unit;

use Chencongbao\LaravelVbenAdmin\Support\FrontendRuntimeRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendRuntimeRequirementsTest extends TestCase
{
    #[DataProvider('nodeVersions')]
    public function test_it_checks_supported_node_versions(string $version, bool $expected): void
    {
        self::assertSame($expected, FrontendRuntimeRequirements::supportsNode($version));
    }

    #[DataProvider('pnpmVersions')]
    public function test_it_checks_supported_pnpm_versions(string $version, bool $expected): void
    {
        self::assertSame($expected, FrontendRuntimeRequirements::supportsPnpm($version));
    }

    public static function nodeVersions(): array
    {
        return [
            ['18.20.3', false],
            ['22.17.9', false],
            ['22.18.0', true],
            ['22.23.2', true],
            ['23.11.1', false],
            ['24.0.0', true],
            ['25.0.0', false],
            ['invalid', false],
        ];
    }

    public static function pnpmVersions(): array
    {
        return [
            ['9.15.0', false],
            ['10.0.0', true],
            ['10.33.4', true],
        ];
    }
}
