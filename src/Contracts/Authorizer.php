<?php

namespace Chencongbao\LaravelVbenAdmin\Contracts;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

interface Authorizer
{
    public function allows(AdminUser $user, string $permission): bool;
}
