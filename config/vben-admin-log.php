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
        'auth.avatar.updated' => ['type' => 'updated', 'module' => 'auth.profile', 'label_key' => 'system.auditLog.actionNames.avatarUpdated'],
        'auth.password.updated' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.passwordUpdated'],
        'auth.profile.updated' => ['type' => 'updated', 'module' => 'auth.profile', 'label_key' => 'system.auditLog.actionNames.profileUpdated'],
        'auth.session.revoked' => ['type' => 'deleted', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.sessionRevoked'],
        'auth.sessions.revoked' => ['type' => 'deleted', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.sessionsRevoked'],
        'auth.two-factor.confirmed' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorConfirmed'],
        'auth.two-factor.disabled' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorDisabled'],
        'auth.two-factor.enabled' => ['type' => 'updated', 'module' => 'auth.security', 'label_key' => 'system.auditLog.actionNames.twoFactorEnabled'],
        'system.menu.created' => ['type' => 'created', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuCreated'],
        'system.menu.deleted' => ['type' => 'deleted', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuDeleted'],
        'system.menu.reordered' => ['type' => 'updated', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuReordered'],
        'system.menu.updated' => ['type' => 'updated', 'module' => 'system.menus', 'label_key' => 'system.auditLog.actionNames.menuUpdated'],
        'system.permission.created' => ['type' => 'created', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionCreated'],
        'system.permission.deleted' => ['type' => 'deleted', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionDeleted'],
        'system.permission.reordered' => ['type' => 'updated', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionReordered'],
        'system.permission.updated' => ['type' => 'updated', 'module' => 'system.permissions', 'label_key' => 'system.auditLog.actionNames.permissionUpdated'],
        'system.role.access-updated' => ['type' => 'updated', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleAccessUpdated'],
        'system.role.created' => ['type' => 'created', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleCreated'],
        'system.role.deleted' => ['type' => 'deleted', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleDeleted'],
        'system.role.updated' => ['type' => 'updated', 'module' => 'system.roles', 'label_key' => 'system.auditLog.actionNames.roleUpdated'],
        'system.setting.updated' => ['type' => 'updated', 'module' => 'system.settings', 'label_key' => 'system.auditLog.actionNames.settingsUpdated'],
        'system.settings.updated' => ['type' => 'updated', 'module' => 'system.settings', 'label_key' => 'system.auditLog.actionNames.settingsUpdated'],
        'system.theme-settings.updated' => ['type' => 'updated', 'module' => 'system.theme-settings', 'label_key' => 'system.auditLog.actionNames.themeSettingsUpdated'],
        'system.user.created' => ['type' => 'created', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userCreated'],
        'system.user.deleted' => ['type' => 'deleted', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userDeleted'],
        'system.user.updated' => ['type' => 'updated', 'module' => 'system.users', 'label_key' => 'system.auditLog.actionNames.userUpdated'],
    ],

    'subjects' => [
        'AdminMenu' => 'system.auditLog.subjectTypes.menu',
        'AdminPermission' => 'system.auditLog.subjectTypes.permission',
        'AdminRole' => 'system.auditLog.subjectTypes.role',
        'AdminSetting' => 'system.auditLog.subjectTypes.setting',
        'AdminUser' => 'system.auditLog.subjectTypes.user',
    ],

    // Host applications may register model-specific field translation keys.
    'fields' => [],
];
