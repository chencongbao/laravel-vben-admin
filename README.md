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
- Laravel Sanctum 4.3+
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

The browser path is configurable, while the API path is deliberately fixed:

```dotenv
VBEN_ADMIN_PATH=admin
```

For a different path, build with the matching Vite base and then publish the compiled assets:

```bash
cd frontend
VITE_BASE=/control-center/ pnpm build:antd
cd ..
php artisan vben-admin:publish-assets
```

`vben-admin:publish-assets` copies the compiled application to `public/{VBEN_ADMIN_PATH}`. It refuses unsafe, reserved, or non-empty destinations unless `--force` is explicitly provided. The Laravel API always remains at `/api/admin` and project PHP extensions belong in `app/Admin`.

To own and freely edit the workspace in a consuming project, publish its Vue source once:

```bash
php artisan vben-admin:publish-workspace
```

Edit `resources/admin/workspace/index.vue`, then build the package frontend with `VBEN_ADMIN_WORKSPACE` pointing to that file. The command prints the exact build command for the current project. Without this environment variable the shared default workspace remains active.

## Install

完整的中文安装、启动、Path 仓库联调和前端构建说明见 [安装文档](docs/installation.zh-CN.md)。

安装后继续开发权限、菜单、API、数据库和Vben页面时，请先阅读 [二次开发指南](docs/development.zh-CN.md)。使用Codex或其他AI Agent开发本包时，仓库根目录的 [AGENTS.md](AGENTS.md) 会要求其遵守相同架构和验收规则；在宿主项目中开发时，请按二次开发指南提供的片段把规则接入宿主项目的 `AGENTS.md`。

登录日志与后台操作日志统一基于 `spatie/laravel-activitylog` 存储，并通过稳定的包契约记录、脱敏和查询；具体规则见二次开发指南的“权限与审计”章节。

列表页提供统一搜索面板、权限按钮、自动刷新、当前页 CSV 导出、多操作折叠和按需批量操作组件；大数据导出应在宿主项目按二次开发指南实现队列任务和独立服务端权限。

```bash
composer require chencongbao/laravel-vben-admin

php artisan vben-admin:install
```

The repeatable installer preserves existing configuration and project extensions, publishes missing scaffolding, runs migrations, synchronizes package-owned system data, creates the fixed administrators when missing, installs locked frontend dependencies, builds and atomically publishes the administration UI, and clears Laravel caches. After a Composer package upgrade, run:

```bash
composer update chencongbao/laravel-vben-admin
php artisan vben-admin:update
```

Use `vben-admin:update --dry-run` to preview an update. CI deployments may use `--skip-frontend` or `--skip-migrate` only when those stages are handled separately.

The initial accounts are:

```text
cmsadmin / admin (super administrator)
admin / admin (manager)
```

Running the installer again never resets an existing administrator's password. Change the default password immediately after the first login. Use `vben-admin:create-admin` when an additional super administrator is required.

## API foundation

All paths use the fixed `/api/admin` prefix:

```text
POST /api/admin/auth/login
POST /api/admin/auth/logout
GET  /api/admin/auth/me
PATCH /api/admin/auth/profile
PUT  /api/admin/auth/password
GET  /api/admin/auth/sessions
DELETE /api/admin/auth/sessions/{tokenId}
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
