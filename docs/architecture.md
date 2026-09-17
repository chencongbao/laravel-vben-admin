# Architecture

## Product shape

Laravel Vben Admin is delivered as three independently upgradeable artifacts:

1. `chencongbao/laravel-vben-admin`: Laravel authentication, authorization, navigation, settings, audit foundations and APIs.
2. `laravel-vben-admin-starter`: the packaged Vben Admin application with project-owned Vue overrides for deployment-specific pages such as the workspace.
3. `@chencongbao/laravel-vben-admin-client`: a future optional TypeScript client for DTOs, errors and API contract checks.

The Composer package ships the default compiled application and its frontend source. Consuming projects must not edit `vendor`; they publish project-owned overrides under `resources/admin`, and the package build resolves those files explicitly. The Starter does not implement authoritative permission decisions.

## Invariants

- Stable permission codes, not HTTP paths, are the authorization identity.
- Laravel performs every authoritative permission check.
- Menu visibility and operation authorization are separate concerns.
- Super administrator access is derived from a protected role, not duplicated into every permission pivot.
- Package system data is synchronized idempotently and never resets assignments.
- Database menu records contain a `view_key`; the Vben application maps it through a local component allowlist.
- The business platform and supplier platform share package code only, never runtime identity or authorization data.
- The fixed workspace route resolves a project-owned `resources/admin/workspace/index.vue` only when `VBEN_ADMIN_WORKSPACE` is supplied at build time; otherwise it uses the package default.
- Authentication risk state is package-owned: short-lived counters live in Laravel Cache while durable security events and IP blocks live in package tables. Host applications may listen to `AdminSecurityRiskDetected` without changing the login controller.
- Reportable Laravel exceptions are passed to `chencongbao/foundation`, which writes the protected exception log and dispatches Telegram delivery. Failed administrator logins are written to the database login log and then dispatch the package `SendTelegramMessage` job without creating a second local exception log. Telegram credentials remain deployment environment variables and are never package defaults.
- `vben-admin:install` and `vben-admin:update` are the normal deployment entry points. Both are repeatable, preserve project-owned configuration and extensions, and delegate to the lower-level migration, synchronization, build and publication commands.
- Frontend asset publication is staged and validated before directory activation so a failed copy does not first remove the currently published administration UI.

## Delivery stages

1. Package boot, migrations, safe installation, login, current user, permission middleware and navigation contract.
2. Administrator, role, permission and menu management APIs with audit coverage. Implemented in the initial working tree.
3. Settings definitions and unified Spatie Activitylog storage for login and operation logs, with compatible query APIs, guarded legacy-table backfill and legacy-table removal. Implemented.
4. Vben 5.7 Starter integration. Initial API wiring and system pages are implemented; runtime and end-to-end authorization tests remain pending on Node.js 22+ and a Laravel 13 host application.
5. Versioned API contract, generated TypeScript client and compatibility matrix.
