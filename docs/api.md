# Admin API v1 draft

The default prefix is `/api/admin`. Except for login, routes require a Sanctum Bearer token with the `admin` ability and an active `AdminUser`.

## Application bootstrap

### `GET /api/admin/application`

Public, read-only bootstrap data used before the login page initializes. It returns only the Vben locale mapped from Laravel's `config('app.locale')` and the supported locale list.

```json
{
  "locale": "zh-CN",
  "supported_locales": ["zh-CN", "en-US"]
}
```

## Authentication and effective access

| Method | Path | Permission |
| --- | --- | --- |
| POST | `/auth/login` | Public, rate limited |
| POST | `/auth/logout` | Authenticated administrator |
| GET | `/auth/me` | Authenticated administrator |
| PATCH | `/auth/profile` | Authenticated administrator |
| PUT | `/auth/password` | Authenticated administrator, rate limited |
| GET | `/auth/sessions` | Authenticated administrator; own sessions only |
| DELETE | `/auth/sessions` | Revoke all other sessions |
| DELETE | `/auth/sessions/{tokenId}` | Revoke one other owned session |
| GET | `/access/permissions` | Authenticated administrator |
| GET | `/access/menus` | Authenticated administrator |

Profile updates accept `name` and an optional absolute avatar URL. Password updates require `current_password`, `password` and `password_confirmation`; the new password must contain upper- and lowercase letters and numbers with a minimum length of 12. A successful password change revokes the administrator's other tokens while preserving the current session.

Session endpoints are always scoped through the authenticated administrator's token relation. The current token cannot be revoked through the session endpoint; normal logout must be used instead. Revocations are recorded in the audit log.

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
| POST | `/system/roles` | `system.role.create` and `system.role.assign-access` |
| GET | `/system/roles/{adminRole}` | `system.role.view` |
| PATCH | `/system/roles/{adminRole}` | `system.role.update` and `system.role.assign-access` |
| DELETE | `/system/roles/{adminRole}` | `system.role.delete` |
| PUT | `/system/roles/{adminRole}/access` | `system.role.assign-access` |

Creating and updating a non-super role accepts `code`, `name`, `is_active`, `permission_ids`, and `menu_ids` in one atomic request. The built-in `administrator` role is immutable and has implicit full access. The built-in `manager` identity is protected, while its access assignments can be updated. A role assigned to administrators cannot be deleted.

## Permissions and menus

Permission CRUD uses `/system/permissions`; menu CRUD uses `/system/menus`. Each action has a distinct `system.permission.*` or `system.menu.*` permission. System records are protected, referenced permissions cannot be deleted, menus with children cannot be deleted, and cyclic menu parents are rejected.

`PUT /system/menus/reorder` requires `system.menu.update` and atomically accepts the complete menu tree as `items` containing `id`, `parent_code`, and `sort`. Incomplete trees and cyclic parent relationships are rejected.

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
