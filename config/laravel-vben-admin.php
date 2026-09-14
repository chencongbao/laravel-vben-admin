<?php

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;

return [
    // Browser path for the compiled administration SPA. The API remains fixed at /api/admin.
    'path' => env('VBEN_ADMIN_PATH', 'admin'),

    'route' => [
        'middleware' => ['api'],
    ],

    'auth' => [
        'model' => AdminUser::class,
        'token_name' => 'vben-admin',
    ],

    'activity_log' => [
        'log_name' => 'admin',
        'clean_after_days' => (int) env('VBEN_ADMIN_ACTIVITY_LOG_DAYS', 365),
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
        // Used only by the legacy log backfill/drop migrations.
        'login_logs' => 'admin_login_logs',
        'audit_logs' => 'admin_audit_logs',
    ],
];
