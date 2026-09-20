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
        'token_ttl_minutes' => (int) env('VBEN_ADMIN_TOKEN_TTL', 720),
        'force_super_admin_two_factor' => (bool) env('VBEN_ADMIN_FORCE_SUPER_ADMIN_2FA', true),
    ],

    'activity_log' => [
        'log_name' => 'admin',
        'clean_after_days' => (int) env('VBEN_ADMIN_ACTIVITY_LOG_DAYS', 365),
    ],

    'alerts' => [
        'system_exceptions' => (bool) env('VBEN_ADMIN_ALERT_SYSTEM_EXCEPTIONS', true),
        'login_failures' => (bool) env('VBEN_ADMIN_ALERT_LOGIN_FAILURES', true),
    ],

    'login_failure_alerts' => [
        'window_seconds' => (int) env('VBEN_ADMIN_LOGIN_ALERT_WINDOW', 600),
        'thresholds' => [1, 5, 10, 20],
        'global_limit' => (int) env('VBEN_ADMIN_LOGIN_ALERT_GLOBAL_LIMIT', 30),
    ],

    'tables' => [
        'users' => 'admin_users',
        'roles' => 'admin_roles',
        'permissions' => 'admin_permissions',
        'menus' => 'admin_menus',
        'user_roles' => 'admin_user_roles',
        'role_permissions' => 'admin_role_permissions',
        'role_menus' => 'admin_role_menus',
        'permission_menus' => 'admin_permission_menus',
        'settings' => 'admin_settings',
        'security_events' => 'admin_security_events',
        'login_ip_blocks' => 'admin_login_ip_blocks',
    ],
];
