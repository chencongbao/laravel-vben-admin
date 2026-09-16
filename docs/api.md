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

Profile updates accept a required `name` of at most 120 characters and an optional absolute avatar URL or one of 30 built-in avatar identifiers such as `default:avatar-1`. The administration client validates the name before submission and maps server-side field validation to its active locale. Avatar uploads use multipart field `avatar`; accepted formats are JPEG, PNG and WebP, with a 2 MB limit and dimensions from 64x64 through 4096x4096. Uploaded files are stored on Laravel's `public` disk under an administrator-specific directory. Replacing an uploaded avatar removes that administrator's previous package-owned avatar file. Password updates require `current_password`, `password` and `password_confirmation`; the new password must contain upper- and lowercase letters and numbers with a minimum length of 12. A successful password change revokes the administrator's other tokens while preserving the current session.

Outside the `local` environment, every administrator must have a non-empty login IP whitelist containing the current request IP, either as an exact IPv4/IPv6 address or within a configured CIDR range. A non-empty list means the whitelist is enabled; an empty list means it is disabled. The whitelist is checked again when completing a 2FA challenge. A failed check returns HTTP 403 with `LOGIN_IP_NOT_ALLOWED` and writes a failed login log. When Google 2FA is enabled, a successful password and whitelist check returns HTTP 202 with a five-minute `challenge_token` instead of a Sanctum token. An unconfirmed user also receives an SVG QR data URL and manual secret. `POST /auth/two-factor/challenge` accepts the challenge and a six-digit TOTP; only a successful verification creates the administrator session. When Laravel is running with `APP_ENV=local`, login intentionally bypasses both the IP whitelist and 2FA checks without altering their saved configuration. Secrets are encrypted at rest and never appear in user payloads, audit changes, or ordinary logs.

Session endpoints are always scoped through the authenticated administrator's token relation. Each newly created access token records its login IP address and user agent. `GET /auth/sessions` returns `id`, `current`, `ip_address`, `user_agent`, `client_type`, `created_at`, and `last_used_at`. `client_type` is derived from the saved user agent and is one of `desktop`, `mobile`, `tablet`, `api`, `other`, or `unknown`; timestamps use Laravel's standard ISO-8601 serialization and the administration UI renders them in `Asia/Shanghai`. Tokens created before the session metadata migration may have null IP and user-agent values. The current token cannot be revoked through the session endpoint; normal logout must be used instead. Revocations are recorded in the audit log.

When any authenticated API request returns HTTP 401, the administration client treats the token as revoked or expired, clears all local authentication state, and immediately redirects to the login page. This forced local logout does not call `/auth/logout` again, preventing a recursive 401 loop when another session has already revoked the token. Concurrent 401 responses share one redirect operation.

Authentication error responses expose stable codes such as `INVALID_CREDENTIALS`, `CAPTCHA_INVALID`, `LOGIN_IP_NOT_ALLOWED`, `TWO_FACTOR_CHALLENGE_INVALID`, `TWO_FACTOR_CODE_INVALID`, and `CURRENT_PASSWORD_INCORRECT`. Validation responses retain Laravel's field-level `errors`; the administration client maps password fields and stable codes to its active locale instead of displaying Laravel translation keys or the server's fallback message directly.

## Administrators

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/users` | `system.user.view` |
| POST | `/system/users` | `system.user.create` |
| GET | `/system/users/{adminUser}` | `system.user.view` |
| PATCH | `/system/users/{adminUser}` | `system.user.update` |

`GET /system/users` accepts an optional positive integer `id` query parameter for exact identifier filtering. All paginated administration list endpoints follow the same default identifier-filter convention.

Super administrators can list and directly view every administrator account. All non-super administrators, including the built-in `admin` account, can list and directly view only users who do not hold a super-administrator role. A hidden super-administrator user returns HTTP 404 from the detail endpoint, preventing identifier probing; update attempts remain denied by the privilege-assignment guard.

Administrator create and update use a single-role selector. A username is required on creation and becomes immutable immediately after the user is stored; every edit form disables it, and an API attempt to change an ordinary username returns `ADMIN_USERNAME_IMMUTABLE`. Administrator passwords must contain at least 12 characters, including uppercase letters, lowercase letters, and numbers; the create and edit forms display this rule below the password field. The API keeps the compatible `role_ids` array field but accepts at most one distinct active role ID; an empty array leaves an ordinary administrator without a role. The built-in `cmsadmin` identity and its `administrator` role are fixed, as are the built-in `admin` identity and its `manager` role; their role field is omitted from the edit UI, and API attempts to change either fixed identity or role return `BUILTIN_ADMIN_IDENTITY_PROTECTED` or `BUILTIN_ADMIN_ROLE_PROTECTED`. When the built-in `admin` edits its own account, both role and status fields are omitted and are not submitted. When a super administrator edits the built-in `admin`, the fixed role remains omitted while the status field is displayed and may be updated. Re-running installation restores those exact role bindings without changing existing passwords. Requests also accept `two_factor_enabled` and `login_ip_whitelist`. The whitelist is an array of at most 100 exact IPv4/IPv6 addresses or CIDR ranges; no separate enable switch exists. Enabling 2FA generates an encrypted secret and requires binding at the next non-local login; disabling it clears the saved secret and confirmation. An administrator cannot disable their current account or remove their own super-administrator role.

## Roles

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/roles` | `system.role.view` |
| POST | `/system/roles` | `system.role.create` |
| GET | `/system/roles/{adminRole}` | `system.role.view` |
| PATCH | `/system/roles/{adminRole}` | `system.role.update` |
| DELETE | `/system/roles/{adminRole}` | `system.role.delete` |
| PUT | `/system/roles/{adminRole}/access` | `system.role.update` |

The role management UI creates and updates a role with `code`, `name`, `permission_ids`, and `menu_ids` in one atomic request. Role status and permission/menu counts are not exposed in the role list or editor. The existing `is_active` database field remains enforced by the server, and newly created roles use its enabled default. The built-in `administrator` role is immutable: its row has no edit action, and both general update and access-update APIs return `SYSTEM_ROLE_PROTECTED`. The built-in `manager` row exposes edit and authorization actions only to a super administrator; any non-super-administrator request to either update endpoint returns `ADMIN_SUPER_ADMIN_REQUIRED`. Its fixed code, name, and enabled state remain read-only. The editor shows the complete menu and permission trees, including menu and permission management, so a super administrator may explicitly assign them later. System roles remain non-deletable, and any other role assigned to administrators cannot be deleted.

Role menu selection is ancestor-complete. Selecting any child menu checks and submits every parent up to the root; clearing the final selected descendant clears its automatically selected parent. The server applies the same ancestor completion to role create, update, and access-update requests so direct API calls cannot persist a child menu without its parent.

Role permission selection follows the same ancestor-complete rule. Selecting a child permission checks and submits every parent permission up to the root. Role create, update, and access-update requests persist the completed permission hierarchy, preventing a page permission from being stored without the directory permission required to expose its parent menu.

On a fresh installation, `manager` receives the built-in system permissions and menus except the fixed workspace and the default menu-management/permission-management access. Specifically, the initial assignment omits `system.menus`, `system.permissions`, and the `system.menu.*` and `system.permission.*` operation permissions. The workspace is available to every authenticated administrator without a role-menu pivot. The `administrator` role remains implicit-full-access and does not require duplicated role-permission or role-menu rows. Synchronization fills these default manager assignments only when the corresponding assignment set is empty and preserves every non-empty customized assignment, including menu or permission management explicitly granted later by a super administrator.

`GET /system/roles` and `GET /system/permissions` accept an optional positive integer `id` query parameter for exact identifier filtering. Super administrators can list and view every role. All other administrators cannot list or directly view the built-in `administrator` and `manager` roles; this same filtered role collection is used by administrator role selectors.

## Permissions and menus

Permission CRUD uses `/system/permissions`; menu CRUD uses `/system/menus`. Each action has a distinct `system.permission.*` or `system.menu.*` permission. System records are protected, referenced permissions cannot be deleted, menus with children cannot be deleted, and cyclic menu or permission parents are rejected.

Menu create and update requests do not accept `is_active` or `is_hidden`. The menu table does not contain these columns; effective menus are displayed according to the stored hierarchy and the administrator's role and permission assignments.

Permission create and update requests accept `parent_id`, `code`, `name`, `sort`, and `menu_ids`. Permissions are effective whenever they exist; the permission table does not contain activation, sensitivity, HTTP method, or HTTP path metadata. Permission data and menu bindings are saved in one transaction. Responses include the related menus. Cyclic parents are rejected with `PERMISSION_CYCLE`, and a permission with children returns `PERMISSION_HAS_CHILDREN` when deletion is attempted. `PUT /api/admin/system/permissions/reorder` accepts the complete permission tree as `id`, `parent_id`, and `sort`, then saves hierarchy and order in one transaction. Stable permission codes and Laravel middleware remain authoritative.

Menu create requires unique `code`, `title`, and `type`; `parent_id` is nullable for a root menu, the fixed workspace cannot be used as a parent, and a blank icon falls back to `lucide:list`. `route_path` is a nullable frontend route rather than an API path. Menu create/update accepts optional `permission_ids` with at most 500 distinct existing IDs.

Menus and permissions share one many-to-many binding source in `admin_permission_menus`. Menu create/update accepts `permission_ids`; permission create/update accepts `menu_ids`. Both directions update the same pivot rows in the same transaction. Sending an empty array clears the bindings; omitting the relation field from an update preserves the existing bindings. Menu access metadata is generated from the related permission codes. The legacy `admin_menus.permission_code` column has been removed and must not be restored as a second source of truth.

Menu and permission management data is scoped to the signed-in administrator. Super administrators can list and directly view all manageable menus and all permissions. Every non-super administrator receives only menu IDs stored in their active roles' menu assignments and permission IDs stored in their active roles' permission assignments; unassigned records and their unassigned related bindings are omitted. Direct detail, update, and delete requests for an unassigned record return HTTP 404. Tree reorder requires the complete currently visible subset for non-super administrators and the complete global manageable tree for super administrators.

A menu-permission binding defines the permission requirement for displaying that menu; it does not grant the permission to a role. Non-super users see a non-workspace menu only when an active role is linked to the menu and, if that menu has permission bindings, the role has at least one of those permissions. Role menu and role permission assignments therefore remain separate and must both be saved. Server route middleware using stable permission codes remains authoritative.

`PUT /system/menus/reorder` requires `system.menu.update` and atomically accepts the complete manageable menu tree as `items` containing `id`, `parent_id`, and `sort`. Incomplete trees, the fixed workspace as parent, and cyclic parent relationships are rejected.

The effective menu API returns a tree. A non-super administrator only receives menus that are both assigned to one of their roles and allowed by their effective permission codes.

## Logs

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/login-logs` | `system.login-log.view` |
| GET | `/system/login-logs/operators` | `system.login-log.view` |
| GET | `/system/audit-logs` | `system.audit.view` |
| GET | `/system/audit-logs/actions` | `system.audit.view` |
| GET | `/system/audit-logs/operators` | `system.audit.view` |
| GET | `/system/audit-logs/{activity}` | `system.audit.view` |

Both endpoints read the shared Spatie Activitylog `activity_log` table. `log_type=login` identifies authentication events and `log_type=operation` identifies administration changes; the stable API paths remain unchanged so existing pages do not depend on Spatie's storage schema.

Both log endpoints accept an optional positive integer `id` query parameter for exact activity identifier filtering. Their existing feature-specific filters remain available and are combined with `id` when supplied.

The two `/operators` endpoints return the administrators available to the signed-in account's operator filter. A super administrator receives every administrator; every non-super administrator receives only accounts without an active super-administrator role. Login-log filtering submits the selected `username`, while operation-log filtering submits the selected `actor_id`.

`GET /system/audit-logs/actions` returns the distinct stable operation codes available in the signed-in account's visible operation logs as `{code, type, module, label_key}` objects. `label_key` is an i18n key rather than translated text, so every installed frontend language can translate the same definition. The administration client displays localized, searchable business-action options and submits only `code` through the existing `action` filter. Unknown codes return nullable metadata and fall back to the original code without blocking logging or querying. The operation-log list omits the change-count column; complete redacted changes remain available from the detail endpoint.

The same scope is enforced on log data, not only on dropdown options. Super administrators can view all login and operation logs. Every non-super administrator is excluded from login and operation records caused by a super administrator, and cannot retrieve a hidden operation-log detail directly by ID.

Login-log items expose the existing `username`, `succeeded`, `failure_code`, `ip_address`, and `created_at` fields, plus `client_type` and `user_agent`. Audit-log items expose the existing actor, action, subject, changes, context, IP, and creation time fields, plus `description`, `method`, `path`, and `user_agent`. Timestamps use standard ISO-8601 serialization and are rendered as Beijing time by the administration client.

Audit changes, context, and captured request input recursively redact keys containing password, token, secret, credential, authorization, cookie, private key, captcha, TOTP, or two-factor data. Uploaded files are represented only by safe metadata. Login and operation records are read-only through these APIs.

Updating several system or theme settings in one request creates one `system.settings.updated` operation record containing all changed keys and their before/after values. A request that produces no value changes does not create an audit record.

## Settings

| Method | Path | Permission |
| --- | --- | --- |
| GET | `/system/settings` | `system.setting.view` |
| PUT | `/system/settings` | `system.setting.view` |
| GET | `/system/theme-settings` | `system.theme-setting.view` |
| PUT | `/system/theme-settings` | `system.theme-setting.view` |

The package owns the built-in `system.name`, `system.page_size`, `system.login_remember_me`, `system.login_description`, login appearance, administration appearance, `system.tabbar_*`, and `system.advanced_preferences` definitions; their runtime values are stored in `admin_settings` instead of duplicated in the published package configuration. Configuration permissions are page-level: `system.setting.view` and `system.theme-setting.view` each authorize both viewing and editing their corresponding page. Watermark, footer, copyright, tenant mode, form-fullscreen, default-table-size, and report-title preferences are intentionally not registered by this package. These values are returned by the public application bootstrap so the selected behavior and appearance are applied before authentication and after refresh. `system.page_size` is also the default for every paginated administration endpoint when `per_page` is omitted and for every list created by the shared frontend pagination factory; an explicit bounded `per_page` or a page-size selection remains a request/page-local override. The page-size selector includes the configured value even when it is not one of the common presets. The supported types are string, bounded integer, boolean, enum, JSON, and IANA timezone. Secrets and credentials are deliberately unsupported.

Setting validation errors use the concrete `settings.{key}` error field. The administration request layer maps known system setting fields to localized, user-facing messages and falls back to a shared localized settings error instead of displaying internal setting keys or server-side English validation text.

Both login and administration themes accept Vben's built-in presets: `default`, `violet`, `pink`, `yellow`, `sky-blue`, `green`, `zinc`, `deep-green`, `deep-blue`, `orange`, `rose`, `neutral`, `slate`, and `gray`. Custom colors are not accepted by these enum settings.

Tab-bar settings control enablement, persistence, visit history, maximum tab count (`0` to `30`, where `0` means unlimited), drag sorting, mouse-wheel response, middle-click closing, tab icons, more and maximize buttons, and the `chrome`, `plain`, `card`, or `brisk` visual style.

Breadcrumbs are enabled by default and display the home entry by default. Administrators can change both behaviors from Theme Settings.
