# Laravel Vben Admin

`chencongbao/laravel-vben-admin` is a Laravel 13 API foundation for independently deployed Vben Admin 5.7 applications.

The package currently provides the backend foundation:

- Composer package auto-discovery;
- package-owned migrations for administrators, roles, permissions, menus, settings and audit tables;
- an isolated administrator model and Sanctum token ability;
- login, logout and current-administrator APIs;
- effective permission and dynamic menu APIs;
- server-side permission middleware;
- an idempotent system-data synchronizer with `--dry-run`;
- an interactive command for creating the first super administrator;
- module contracts for registering business permissions and menus.
- CRUD APIs for administrators, roles, permissions and menus;
- transactional role access assignment;
- login logging and redacted write-operation auditing;
- protection for system roles, system permissions, system menus and self-demotion.

The repository also contains an editable Vben Admin 5.7.0 Starter in `frontend/`. It is based on the official `v5.7.0` tag and connects directly to this package's Laravel API. This repository does not modify files inside `vendor` or `node_modules`.

## Requirements

- PHP 8.4
- Laravel 13
- Laravel Sanctum 5
- MySQL 8.0+ for the initial supported database target
- Node.js `^22.18.0 || ^24.0.0` and pnpm `>=10` for the Vben Starter

## Vben Starter

```bash
cd frontend
corepack enable
pnpm install
pnpm dev:antd
```

Development and production default to the same-origin `/api/admin` API. For a separately deployed frontend, override `VITE_GLOB_API_URL` in an environment-specific file and configure Laravel CORS explicitly.

The Starter uses backend access mode. Laravel returns the effective menu tree and permissions, while `frontend/apps/web-antd/src/api/core/menu.ts` maps trusted `view_key` values to local Vue components. Unknown view keys are never interpreted as arbitrary imports.

## Install

```bash
composer require chencongbao/laravel-vben-admin

php artisan vben-admin:install
php artisan migrate
php artisan vben-admin:sync --dry-run
php artisan vben-admin:sync
php artisan vben-admin:create-admin
```

The installer deliberately does not run migrations or create a fixed default password. Production database changes remain explicit deployment steps.

## API foundation

All paths use the configurable `api/admin` prefix:

```text
POST /api/admin/auth/login
POST /api/admin/auth/logout
GET  /api/admin/auth/me
PATCH /api/admin/auth/profile
PUT  /api/admin/auth/password
GET  /api/admin/access/permissions
GET  /api/admin/access/menus
```

Protected requests use the returned Bearer token. Administrator tokens are created with the `admin` ability and the package verifies both the token ability and administrator model.

## Protect a host route

```php
Route::post('/api/admin/matches/{match}/publish', PublishMatchController::class)
    ->middleware(['auth:sanctum', 'admin.user', 'admin.permission:match.publish']);
```

The Vben UI may hide inaccessible buttons, but Laravel middleware remains the authorization boundary.

## Register a business module

Implement `AdminModule` in the host project and register it during application boot:

```php
use Chencongbao\LaravelVbenAdmin\Contracts\AdminModule;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Definitions\MenuDefinition;
use Chencongbao\LaravelVbenAdmin\Definitions\PermissionDefinition;

final class MatchAdminModule implements AdminModule
{
    public function key(): string
    {
        return 'matches';
    }

    public function permissions(): array
    {
        return [
            new PermissionDefinition('match.view', 'View matches'),
            new PermissionDefinition('match.publish', 'Publish matches', sensitive: true),
        ];
    }

    public function menus(): array
    {
        return [
            new MenuDefinition(
                code: 'match.list',
                title: 'Matches',
                type: 'page',
                routeName: 'MatchList',
                routePath: '/matches',
                viewKey: 'match.list',
                permissionCode: 'match.view',
            ),
        ];
    }
}

app(ModuleRegistry::class)->register(new MatchAdminModule());
```

Then preview and apply definitions:

```bash
php artisan vben-admin:sync --dry-run
php artisan vben-admin:sync
```

Synchronization never deletes custom records or resets role assignments.

## Architecture boundary

Business and supplier platforms install the same package into different Laravel applications. They must not share administrator tables, tokens, cookies, cache prefixes, audit records or application keys.

The package owns generic administration only. Match, broadcast, source, media and commentary state machines belong to their host applications.
