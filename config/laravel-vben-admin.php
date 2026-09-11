<?php

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

return [
    'route' => [
        'prefix' => 'api/admin',
        'middleware' => ['api'],
    ],

    'auth' => [
        'model' => AdminUser::class,
        'token_name' => 'vben-admin',
    ],

    'tables' => [
        'users' => 'admin_users',
        'roles' => 'admin_roles',
        'permissions' => 'admin_permissions',
        'menus' => 'admin_menus',
        'user_roles' => 'admin_user_roles',
        'role_permissions' => 'admin_role_permissions',
        'role_menus' => 'admin_role_menus',
        'settings' => 'admin_settings',
        'login_logs' => 'admin_login_logs',
        'audit_logs' => 'admin_audit_logs',
    ],

    'settings' => [
        'system.name' => ['type' => 'string', 'default' => 'Laravel Vben Admin'],
        'system.default_locale' => ['type' => 'string', 'default' => 'en'],
        'system.default_timezone' => ['type' => 'timezone', 'default' => 'UTC'],
        'system.page_size' => ['type' => 'integer', 'default' => 20, 'min' => 10, 'max' => 100],
    ],
];
