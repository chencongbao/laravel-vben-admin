<?php

namespace Chencongbao\LaravelVbenAdmin\Definitions;

use InvalidArgumentException;

final class PermissionDefinition
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly bool $sensitive = false,
    ) {
        if (! preg_match('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/', $code)) {
            throw new InvalidArgumentException("Invalid permission code [{$code}].");
        }
    }
}
