<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operation action registry
    |--------------------------------------------------------------------------
    |
    | Keep machine codes stable and store translation keys only. Host projects
    | may add or override entries in their published configuration file.
    |
    */
    'actions' => [
        'auth.avatar.updated' => ['type' => 'updated', 'module' => 'auth.profile', 'label_key' => 'system.auditLog.actionNames.avatarUpdated', 'request_fields' => ['avatar']],
        'auth.password.updated' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.passwordUpdated'],
        'auth.profile.updated' => ['type' => 'updated', 'module' => 'auth.profile', 'label_key' => 'system.auditLog.actionNames.profileUpdated', 'request_fields' => ['name']],
        'auth.session.revoked' => ['type' => 'deleted', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.sessionRevoked'],
        'auth.sessions.revoked' => ['type' => 'deleted', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.sessionsRevoked'],
        'auth.two-factor.confirmed' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorConfirmed'],
        'auth.two-factor.disabled' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorDisabled'],
        'auth.two-factor.enabled' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorEnabled'],
        'system.menu.created' => ['type' => 'created', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuCreated', 'request_fields' => ['parent_id', 'code', 'title', 'type', 'icon', 'route_path', 'permission_ids']],
        'system.menu.deleted' => ['type' => 'deleted', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuDeleted'],
        'system.menu.reordered' => ['type' => 'updated', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuReordered', 'request_fields' => ['items']],
        'system.menu.updated' => ['type' => 'updated', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuUpdated', 'request_fields' => ['parent_id', 'code', 'title', 'type', 'icon', 'route_path', 'permission_ids']],
        'system.permission.created' => ['type' => 'created', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionCreated', 'request_fields' => ['parent_id', 'code', 'name', 'sort', 'menu_ids']],
        'system.permission.deleted' => ['type' => 'deleted', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionDeleted'],
        'system.permission.reordered' => ['type' => 'updated', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionReordered', 'request_fields' => ['items']],
        'system.permission.updated' => ['type' => 'updated', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionUpdated', 'request_fields' => ['parent_id', 'code', 'name', 'sort', 'menu_ids']],
        'system.role.access-updated' => ['type' => 'updated', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleAccessUpdated', 'request_fields' => ['permission_ids', 'menu_ids']],
        'system.role.created' => ['type' => 'created', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleCreated', 'request_fields' => ['code', 'name', 'permission_ids', 'menu_ids']],
        'system.role.deleted' => ['type' => 'deleted', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleDeleted'],
        'system.role.updated' => ['type' => 'updated', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleUpdated', 'request_fields' => ['code', 'name', 'permission_ids', 'menu_ids']],
        'system.security.event.resolved' => ['type' => 'updated', 'module' => 'system.security', 'label_key' => 'system.auditLog.actionNames.securityEventResolved', 'request_fields' => ['resolution_note']],
        'system.security.ip-block.created' => ['type' => 'created', 'module' => 'system.security', 'label_key' => 'system.auditLog.actionNames.securityIpBlockCreated', 'request_fields' => ['ip_address', 'reason_code', 'duration_hours']],
        'system.security.ip-block.released' => ['type' => 'updated', 'module' => 'system.security', 'label_key' => 'system.auditLog.actionNames.securityIpBlockReleased'],
        'system.setting.updated' => ['type' => 'updated', 'module' => 'system.settings', 'label_key' => 'system.auditLog.actionNames.settingsUpdated', 'request_fields' => ['settings']],
        'system.settings.updated' => ['type' => 'updated', 'module' => 'system.settings', 'label_key' => 'system.auditLog.actionNames.settingsUpdated', 'request_fields' => ['settings']],
        'system.theme-settings.updated' => ['type' => 'updated', 'module' => 'system.theme-settings', 'label_key' => 'system.auditLog.actionNames.themeSettingsUpdated', 'request_fields' => ['settings']],
        'system.user.created' => ['type' => 'created', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userCreated', 'request_fields' => ['username', 'name', 'is_active', 'two_factor_enabled', 'login_ip_whitelist', 'role_ids']],
        'system.user.deleted' => ['type' => 'deleted', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userDeleted'],
        'system.user.updated' => ['type' => 'updated', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userUpdated', 'request_fields' => ['name', 'is_active', 'two_factor_enabled', 'login_ip_whitelist', 'role_ids']],
    ],

    'subjects' => [
        'AdminMenu' => 'system.auditLog.subjectTypes.menu',
        'AdminPermission' => 'system.auditLog.subjectTypes.permission',
        'AdminRole' => 'system.auditLog.subjectTypes.role',
        'AdminSetting' => 'system.auditLog.subjectTypes.setting',
        'AdminSecurityEvent' => 'system.auditLog.subjectTypes.securityEvent',
        'AdminLoginIpBlock' => 'system.auditLog.subjectTypes.securityIpBlock',
        'AdminUser' => 'system.auditLog.subjectTypes.user',
    ],

    // Host applications may register model-specific field translation keys.
    'fields' => [],
];
