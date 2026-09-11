<?php

namespace Chencongbao\LaravelVbenAdmin\Definitions;

final class MenuDefinition
{
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly string $type,
        public readonly ?string $parentCode = null,
        public readonly ?string $routeName = null,
        public readonly ?string $routePath = null,
        public readonly ?string $viewKey = null,
        public readonly ?string $permissionCode = null,
        public readonly int $sort = 0,
    ) {}
}
