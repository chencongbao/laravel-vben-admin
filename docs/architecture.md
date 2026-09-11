# Architecture

## Product shape

Laravel Vben Admin is delivered as three independently upgradeable artifacts:

1. `chencongbao/laravel-vben-admin`: Laravel authentication, authorization, navigation, settings, audit foundations and APIs.
2. `laravel-vben-admin-starter`: an editable Vben Admin application starter owned by the consuming project after creation.
3. `@chencongbao/laravel-vben-admin-client`: a future optional TypeScript client for DTOs, errors and API contract checks.

The Composer package does not ship an editable Vue application into `vendor`. The Starter does not implement authoritative permission decisions.

## Invariants

- Stable permission codes, not HTTP paths, are the authorization identity.
- Laravel performs every authoritative permission check.
- Menu visibility and operation authorization are separate concerns.
- Super administrator access is derived from a protected role, not duplicated into every permission pivot.
- Package system data is synchronized idempotently and never resets assignments.
- Database menu records contain a `view_key`; the Vben application maps it through a local component allowlist.
- The business platform and supplier platform share package code only, never runtime identity or authorization data.

## Delivery stages

1. Package boot, migrations, safe installation, login, current user, permission middleware and navigation contract.
2. Administrator, role, permission and menu management APIs with audit coverage. Implemented in the initial working tree.
3. Settings definitions, login logs and audit query APIs. Implemented in the initial working tree.
4. Vben 5.7 Starter integration. Initial API wiring and system pages are implemented; runtime and end-to-end authorization tests remain pending on Node.js 22+ and a Laravel 13 host application.
5. Versioned API contract, generated TypeScript client and compatibility matrix.
