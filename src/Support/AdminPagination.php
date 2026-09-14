<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

final class AdminPagination
{
    public static function defaultPageSize(): int
    {
        return min(max((int) SystemSettings::value('system.page_size'), 10), 100);
    }

    public static function perPage(?int $requested = null): int
    {
        return $requested === null
            ? self::defaultPageSize()
            : min(max($requested, 1), 100);
    }
}
