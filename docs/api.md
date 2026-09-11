# Admin API v1 draft

The default prefix is `/api/admin`. Except for login, routes require a Sanctum Bearer token with the `admin` ability and an active `AdminUser`.

## Authentication and effective access

| Method | Path | Permission |
| --- | --- | --- |
| POST | `/auth/login` | Public, rate limited |
| POST | `/auth/logout` | Authenticated administrator |
| GET | `/auth/me` | Authenticated administrator |
| GET | `/access/permissions` | Authenticated administrator |
| GET | `/access/menus` | Authenticated administrator |

## Administrators

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/users` | `system.user.view` |
| POST | `/system/users` | `system.user.create`; assigning roles additionally requires `system.user.assign-roles` |
| GET | `/system/users/{adminUser}` | `system.user.view` |
| PATCH | `/system/users/{adminUser}` | `system.user.update`; assigning roles additionally requires `system.user.assign-roles` |

An administrator cannot disable their current account or remove their own super-administrator role.

## Roles

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/roles` | `system.role.view` |
| POST | `/system/roles` | `system.role.create` |
| GET | `/system/roles/{adminRole}` | `system.role.view` |
| PATCH | `/system/roles/{adminRole}` | `system.role.update` |
| DELETE | `/system/roles/{adminRole}` | `system.role.delete` |
| PUT | `/system/roles/{adminRole}/access` | `system.role.assign-access` |

System roles cannot be modified or deleted. A role assigned to administrators cannot be deleted.

## Permissions and menus

Permission CRUD uses `/system/permissions`; menu CRUD uses `/system/menus`. Each action has a distinct `system.permission.*` or `system.menu.*` permission. System records are protected, referenced permissions cannot be deleted, menus with children cannot be deleted, and cyclic menu parents are rejected.

The effective menu API returns a tree. A non-super administrator only receives menus that are both assigned to one of their roles and allowed by their effective permission codes.

## Logs

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/login-logs` | `system.login-log.view` |
| GET | `/system/audit-logs` | `system.audit.view` |

Audit changes recursively redact keys containing password, token, secret, credential or authorization. Audit records are append-only through this API.

## Settings

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/settings` | `system.setting.view` |
| PUT | `/system/settings` | `system.setting.update` |

Only keys registered in `config/laravel-vben-admin.php` are accepted. The initial types are string, bounded integer, boolean and IANA timezone. Secrets and credentials are deliberately unsupported.
