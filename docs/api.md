# Admin API v1 draft

The default prefix is `/api/admin`. Except for login, routes require a Sanctum Bearer token with the `admin` ability and an active `AdminUser`.

## Application bootstrap

### `GET /api/admin/application`

Public, read-only bootstrap data used before the login page initializes. It returns the database-backed system name and login settings, the Vben locale mapped from Laravel's `config('app.locale')`, the supported locale list, and the fixed administration display timezone `Asia/Shanghai`. Before the settings table is available, values fall back to their registered defaults.

```json
{
  "name": "Laravel Vben Admin",
  "locale": "zh-CN",
  "supported_locales": ["zh-CN", "en-US"],
  "timezone": "Asia/Shanghai",
  "login_remember_me": true,
  "login_description": "安全、高效、易扩展的后台管理平台",
  "advanced_preferences": {
    "app": { "dynamicTitle": true, "contentCompact": "wide" },
    "sidebar": { "enable": true, "width": 224 },
    "widget": { "languageToggle": true, "themeToggle": true }
  },
  "login_theme": "default",
  "login_layout": "panel-right"
}
```

## Authentication and effective access

| Method | Path | Permission |
| --- | --- | --- |
| POST | `/auth/login` | Public, rate limited |
| POST | `/auth/logout` | Authenticated administrator |
| GET | `/auth/me` | Authenticated administrator |
| GET | `/auth/avatars` | Authenticated administrator; list built-in avatars |
| POST | `/auth/avatar` | Authenticated administrator, rate limited; upload avatar |
| GET | `/auth/two-factor` | Authenticated administrator; read Google 2FA status |
| POST | `/auth/two-factor/enable` | Authenticated administrator; require setup at next login |
| POST | `/auth/two-factor/disable` | Authenticated administrator; current TOTP required after setup |
| POST | `/auth/two-factor/challenge` | Public, rate limited; complete the short-lived login challenge |
| PATCH | `/auth/profile` | Authenticated administrator |
| PUT | `/auth/password` | Authenticated administrator, rate limited |
| GET | `/auth/sessions` | Authenticated administrator; own sessions only |
| DELETE | `/auth/sessions` | Revoke all other sessions |
| DELETE | `/auth/sessions/{tokenId}` | Revoke one other owned session |
| GET | `/access/permissions` | Authenticated administrator |
| GET | `/access/menus` | Authenticated administrator |

Login, profile updates, and `GET /auth/me` return the administrator's effective role summaries as `roles`, with each item containing `code` and `name`. The administration header uses the login username and localized built-in role name instead of demo account or subscription data.

Profile updates accept `name` and an optional absolute avatar URL or one of 30 built-in avatar identifiers such as `default:avatar-1`. Avatar uploads use multipart field `avatar`; accepted formats are JPEG, PNG and WebP, with a 2 MB limit and dimensions from 64x64 through 4096x4096. Uploaded files are stored on Laravel's `public` disk under an administrator-specific directory. Replacing an uploaded avatar removes that administrator's previous package-owned avatar file. Password updates require `current_password`, `password` and `password_confirmation`; the new password must contain upper- and lowercase letters and numbers with a minimum length of 12. A successful password change revokes the administrator's other tokens while preserving the current session.

Outside the `local` environment, every administrator must have a non-empty login IP whitelist containing the current request IP, either as an exact IPv4/IPv6 address or within a configured CIDR range. A non-empty list means the whitelist is enabled; an empty list means it is disabled. The whitelist is checked again when completing a 2FA challenge. A failed check returns HTTP 403 with `LOGIN_IP_NOT_ALLOWED` and writes a failed login log. When Google 2FA is enabled, a successful password and whitelist check returns HTTP 202 with a five-minute `challenge_token` instead of a Sanctum token. An unconfirmed user also receives an SVG QR data URL and manual secret. `POST /auth/two-factor/challenge` accepts the challenge and a six-digit TOTP; only a successful verification creates the administrator session. When Laravel is running with `APP_ENV=local`, login intentionally bypasses both the IP whitelist and 2FA checks without altering their saved configuration. Secrets are encrypted at rest and never appear in user payloads, audit changes, or ordinary logs.

Session endpoints are always scoped through the authenticated administrator's token relation. Each newly created access token records its login IP address and user agent. `GET /auth/sessions` returns `id`, `current`, `ip_address`, `user_agent`, `client_type`, `created_at`, and `last_used_at`. `client_type` is derived from the saved user agent and is one of `desktop`, `mobile`, `tablet`, `api`, `other`, or `unknown`; timestamps use Laravel's standard ISO-8601 serialization and the administration UI renders them in `Asia/Shanghai`. Tokens created before the session metadata migration may have null IP and user-agent values. The current token cannot be revoked through the session endpoint; normal logout must be used instead. Revocations are recorded in the audit log.

When any authenticated API request returns HTTP 401, the administration client treats the token as revoked or expired, clears all local authentication state, and immediately redirects to the login page. This forced local logout does not call `/auth/logout` again, preventing a recursive 401 loop when another session has already revoked the token. Concurrent 401 responses share one redirect operation.

Authentication error responses expose stable codes such as `INVALID_CREDENTIALS`, `CAPTCHA_INVALID`, `LOGIN_IP_NOT_ALLOWED`, `TWO_FACTOR_CHALLENGE_INVALID`, and `TWO_FACTOR_CODE_INVALID`. The administration client translates these codes using its active locale instead of displaying the server's fallback message directly.

## Administrators

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/users` | `system.user.view` |
| POST | `/system/users` | `system.user.create`; assigning roles additionally requires `system.user.assign-roles` |
| GET | `/system/users/{adminUser}` | `system.user.view` |
| PATCH | `/system/users/{adminUser}` | `system.user.update`; assigning roles additionally requires `system.user.assign-roles` |

Administrator create and update requests also accept `two_factor_enabled` and `login_ip_whitelist`. The whitelist is an array of at most 100 exact IPv4/IPv6 addresses or CIDR ranges; no separate enable switch exists. Enabling 2FA generates an encrypted secret and requires binding at the next non-local login; disabling it clears the saved secret and confirmation. An administrator cannot disable their current account or remove their own super-administrator role.

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
| GET | `/system/theme-settings` | Super administrator only |
| PUT | `/system/theme-settings` | Super administrator only |

The package owns the built-in `system.name`, `system.page_size`, `system.login_remember_me`, `system.login_description`, login appearance, administration appearance, `system.tabbar_*`, and `system.advanced_preferences` definitions; their runtime values are stored in `admin_settings` instead of duplicated in the published package configuration. The regular settings endpoint exposes the system name, page size, one language-independent login description, and whether the login page displays Remember Me. The dedicated theme settings endpoints expose login appearance, administration appearance, tab-bar behavior, and the remaining shared interface preferences, and require an active super-administrator role independently of assignable permissions. These values are returned by the public application bootstrap so the selected behavior and appearance are applied before authentication and after refresh. The supported types are string, bounded integer, boolean, enum, JSON, and IANA timezone. Secrets and credentials are deliberately unsupported.

Both login and administration themes accept Vben's built-in presets: `default`, `violet`, `pink`, `yellow`, `sky-blue`, `green`, `zinc`, `deep-green`, `deep-blue`, `orange`, `rose`, `neutral`, `slate`, and `gray`. Custom colors are not accepted by these enum settings.

Tab-bar settings control enablement, persistence, visit history, maximum tab count (`0` to `30`, where `0` means unlimited), drag sorting, mouse-wheel response, middle-click closing, tab icons, more and maximize buttons, and the `chrome`, `plain`, `card`, or `brisk` visual style.
