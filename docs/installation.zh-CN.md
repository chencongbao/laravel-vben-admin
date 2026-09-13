# Laravel Vben Admin 安装文档

本文说明如何把 `chencongbao/laravel-vben-admin` 安装到 Laravel 13 项目，并访问已经编译的 Vben Admin 5.7 后台。

## 1. 环境要求

- PHP 8.4
- Laravel 13
- Composer 2
- Laravel 可以正常连接目标数据库
- 只有修改或重新编译 Vue 前端时，才需要 Node.js `^22.18.0 || ^24.0.0` 和 pnpm `>=10`

后台 API 固定使用 `/api/admin`，不需要配置 API 地址。后台浏览器路径默认是 `/admin`，可以通过 `VBEN_ADMIN_PATH` 修改。

后台支持简体中文和英文。初始语言读取 Laravel 的 `config('app.locale')`；推荐默认配置：

后台时区读取 Laravel 的 `config('app.timezone')`，不在 `laravel-vben-admin.php` 中重复配置。

```dotenv
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN
```

`zh`、`zh_CN`、`zh-CN` 映射为 Vben 的 `zh-CN`，`en`、`en_US`、`en-US` 映射为 `en-US`，其他值回退为简体中文。

## 2. 正式 Composer 安装

在 Laravel 项目根目录执行：

```bash
composer require chencongbao/laravel-vben-admin
php artisan vben-admin:install
php artisan storage:link
php artisan vben-admin:publish-assets
```

`vben-admin:install` 会自动完成以下工作：

1. 发布 `config/laravel-vben-admin.php`；
2. 在项目不存在 Sanctum 迁移时发布 `personal_access_tokens` 迁移；
3. 执行所有待执行的数据库 migration；
4. 同步内置的 `administrator`（超级管理员）、`manager`（管理员）、权限和菜单；
5. 首次安装时创建默认超级管理员。

默认登录信息：

```text
用户名：admin
密码：admin
```

如果项目中已经存在用户名为 `admin` 的管理员，重复执行安装命令不会覆盖其密码。正式环境首次登录后应立即修改默认密码。

`vben-admin:publish-assets` 会把包内已经编译的前端资源复制到 Laravel 的 `public/admin`。如果目标目录已经存在且需要更新，执行：

```bash
php artisan vben-admin:publish-assets --force
```

`storage:link` 用于让管理员自行上传的头像可以通过 Web 访问；如果项目已经创建过 `public/storage` 链接，无需重复执行。

## 3. 本地 Path 仓库安装

包还没有发布到 Packagist，或者需要在本机同步开发包时，在 Laravel 项目的 `composer.json` 中加入：

```json
{
  "repositories": {
    "laravel-vben-admin": {
      "type": "path",
      "url": "/Users/chencongbao/data/wwwroot/PACKAGES/laravel-vben-admin"
    }
  }
}
```

然后执行：

```bash
composer require chencongbao/laravel-vben-admin:@dev
php artisan vben-admin:install
php artisan vben-admin:publish-assets
```

Composer Path 仓库通常会把项目的 `vendor/chencongbao/laravel-vben-admin` 链接到本地包目录，因此修改 PHP 包代码后一般不需要重复安装。修改自动加载结构后执行：

```bash
composer dump-autoload
```

## 4. 启动和访问

本地开发可以在 Laravel 项目根目录执行：

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

浏览器访问：

```text
http://127.0.0.1:8000/admin/
```

登录：

```text
用户名：admin
密码：admin
```

终端窗口必须保持运行。按 `Control + C` 停止开发服务器。

正式环境应由 Nginx、Apache、Laravel Octane 或其他受支持的运行方式提供服务，并把站点根目录指向 Laravel 项目的 `public` 目录，不要指向项目根目录。

## 5. 修改后台访问路径

在 Laravel 项目的 `.env` 中配置：

```dotenv
VBEN_ADMIN_PATH=control-center
```

浏览器入口会变成：

```text
https://你的域名/control-center/
```

API 地址仍然固定为：

```text
https://你的域名/api/admin
```

修改路径后，前端必须使用相同的 Vite base 重新构建，再发布到新的目录：

```bash
cd /path/to/laravel-vben-admin/frontend
corepack enable
pnpm install
VITE_BASE=/control-center/ pnpm build:antd
cd ..
php artisan vben-admin:publish-assets --force
```

路径不要填写开头或结尾的 `/`。例如使用 `control-center`，不要填写 `/control-center/`。`api` 和 `api/admin` 是保留路径，不能作为后台浏览器路径。

修改 `.env` 后如果 Laravel 开启过配置缓存，执行：

```bash
php artisan optimize:clear
```

## 6. 更新包

正式 Composer 依赖更新：

```bash
composer update chencongbao/laravel-vben-admin
php artisan vben-admin:install
php artisan vben-admin:publish-assets --force
```

安装命令可以重复执行：它会执行新增 migration 并同步包拥有的基础权限和菜单，但不会删除业务项目的自定义记录，也不会重置已有 `admin` 密码。

更新前建议备份数据库，并先在测试环境验证 migration 和自定义后台模块兼容性。

## 7. 常用命令

```bash
# 安装或升级基础结构
php artisan vben-admin:install

# 预览角色、权限和菜单同步内容，不写数据库
php artisan vben-admin:sync --dry-run

# 同步基础角色、权限和菜单
php artisan vben-admin:sync

# 交互式创建额外的超级管理员
php artisan vben-admin:create-admin

# 首次发布已编译前端资源
php artisan vben-admin:publish-assets

# 覆盖更新已发布前端资源
php artisan vben-admin:publish-assets --force
```

## 8. 常见问题

### 页面返回 404

确认已经执行 `php artisan vben-admin:publish-assets`，并检查 `public/{VBEN_ADMIN_PATH}/index.html` 是否存在。正式 Web 服务器的站点根目录必须是 Laravel 的 `public` 目录。

### 页面打开但 JavaScript 或 CSS 返回 404

前端构建时的 `VITE_BASE` 必须与 `VBEN_ADMIN_PATH` 对应。默认路径使用 `/admin/`；例如 `VBEN_ADMIN_PATH=control-center` 时使用 `VITE_BASE=/control-center/` 重新构建。

### 登录提示账号或密码错误

默认账号只在首次安装且不存在 `admin` 时创建。重复安装不会把已经修改的密码重置为 `admin`。可以通过 `php artisan vben-admin:create-admin` 创建其他超级管理员。

### 提示 `personal_access_tokens` 表不存在

执行：

```bash
php artisan vben-admin:install
```

安装器会在需要时发布 Sanctum migration 并运行 migration。

### 修改包的 Vue 代码后页面没有变化

修改 Vue 源码后必须重新构建并覆盖发布：

```bash
cd /path/to/laravel-vben-admin/frontend
pnpm install
VITE_BASE=/admin/ pnpm build:antd
cd ..
php artisan vben-admin:publish-assets --force
```

## 9. 项目扩展边界

- 公共后台包代码保持可升级，不在业务项目中直接修改 `vendor`；
- Laravel 业务后台扩展放在项目的 `app/Admin`；
- 项目自有前端页面和资源放在 `resources/admin`；
- 业务权限和菜单通过包提供的模块契约注册，然后执行 `vben-admin:sync`；
- 敏感操作必须同时经过 Laravel 服务端权限校验，不能只依赖前端隐藏按钮。
