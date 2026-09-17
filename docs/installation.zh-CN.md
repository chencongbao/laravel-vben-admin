# Laravel Vben Admin 安装文档

本文说明如何把 `chencongbao/laravel-vben-admin` 安装到 Laravel 13 项目，并访问已经编译的 Vben Admin 5.7 后台。

安装完成后如需开发新的数据库、API、权限、菜单或Vue页面，请继续阅读 [二次开发指南](development.zh-CN.md)。该指南同时作为Codex/AI Agent的实现约束；直接开发包源码时由包根目录 `AGENTS.md` 约束，在宿主项目中开发时应按指南提供的片段接入宿主项目 `AGENTS.md`。

## 1. 环境要求

- PHP 8.4
- Laravel 13
- Composer 2
- Laravel 可以正常连接目标数据库
- 默认一键安装和升级会编译 Vue 前端，因此需要 Node.js `^22.18.0 || ^24.0.0` 和 pnpm `>=10`；由 CI 单独构建时可使用 `--skip-frontend`

后台 API 固定使用 `/api/admin`，不需要配置 API 地址。后台浏览器路径默认是 `/admin`，可以通过 `VBEN_ADMIN_PATH` 修改。

后台支持简体中文和英文。初始语言读取 Laravel 的 `config('app.locale')`；推荐默认配置：

数据库时间继续按UTC保存，API使用标准ISO-8601时间；管理后台所有时间统一转换为 `Asia/Shanghai`（北京时间，UTC+8）展示，不随服务器、浏览器或操作系统时区变化。

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
```

`vben-admin:install` 会自动完成以下工作：

1. 发布缺失的包配置，但不覆盖已有配置；
2. 补齐 `app/Admin`、`routes/admin.php` 和 `resources/admin` 项目扩展脚手架，但不覆盖已有业务文件；
3. 发布缺失的 Sanctum migration，并执行所有待执行 migration；
4. 同步内置角色、权限、菜单和关联规则；
5. 创建或恢复 `cmsadmin`、`admin` 固定角色关系，但不重置已有密码；
6. 按锁文件安装前端依赖，合并项目扩展，编译并原子发布到 `public/{VBEN_ADMIN_PATH}`；
7. 执行 `optimize:clear` 清理 Laravel 缓存。

该命令可以安全重复执行，用于首次安装失败后的恢复。重复执行不会重复插入系统数据，不会覆盖项目扩展或已有配置，也不会重置账号密码。只有明确需要恢复包默认配置时才使用：

```bash
php artisan vben-admin:install --overwrite-config
```

由 CI 单独构建前端，或当前服务器没有 Node.js 时可以使用 `--skip-frontend`；数据库由独立发布阶段处理时可以附加 `--skip-migrate`。跳过步骤后必须由部署流程补做，不能把跳过后的状态当成完整安装。

安装会直接创建统一的 Spatie Activitylog `activity_log` 表，不再创建旧的 `admin_login_logs` 和 `admin_audit_logs` 表。本项目只支持全新安装，不提供旧版本数据库的迁移或日志回填路径。

默认登录信息：

```text
超级管理员：cmsadmin / admin（固定绑定 administrator 角色）
管理员：admin / admin（固定绑定 manager 角色）
```

两个内置账号的用户名和角色关系不可在用户管理或 API 中修改。如果项目中已经存在同名管理员，重复执行安装命令不会覆盖其密码，但会恢复上述固定角色关系。正式环境首次登录后应立即修改两个默认密码。

全新安装会把当前内置 RBAC 基线完整写入数据库：

- 创建并固定 `administrator`（超级管理员）和 `manager`（管理员）两个系统角色；
- 创建当前系统管理、系统日志、配置管理的权限父子树，只包含当前产品保留的权限；
- 创建工作台、系统管理、系统日志、配置管理及其子菜单，并写入 `parent_id`、前端路由、图标和 `sort`；
- 使用 `admin_permission_menus` 写入每个系统菜单对应的访问权限；
- `cmsadmin` 只绑定 `administrator`，`admin` 只绑定 `manager`；
- `administrator` 通过超级管理员规则隐式拥有全部权限和菜单，不批量写入角色权限、角色菜单关联；
- 新安装的 `manager` 默认关联内置系统权限和菜单，包括安全中心的查看、风险处理权限和菜单；但不关联固定工作台、菜单管理、权限管理、主题配置，也不关联 `system.menu.*`、`system.permission.*`、`system.theme-setting.view`；工作台由登录态自动提供，不写入 `admin_role_menus`。只有 `administrator` 超级管理员可以进入菜单管理、权限管理和主题配置。

`vben-admin:sync` 会保留已有自定义数据和非空角色授权。只有当内置 `manager` 的权限或菜单关联为空时，才补齐上述默认权限或默认菜单；非空授权视为超级管理员已经人工调整，不在同步时覆盖。

`vben-admin:publish-assets` 是供开发和排错使用的底层命令，会把包内已经编译的前端资源原子发布到 Laravel 的 `public/admin`。它先复制到同级临时目录并校验 `index.html`，再切换目录；准备失败时保留已有线上资源。如果目标目录已经存在且需要更新，执行：

```bash
php artisan vben-admin:publish-assets --force
```

默认来源是当前安装包内的 `frontend/apps/web-antd/dist`。如果本地 Path 仓库通过复制方式安装，或者构建产物位于共享包目录，可以用 `--source` 指定编译目录：

```bash
php artisan vben-admin:publish-assets --force \
  --source=/absolute/path/to/laravel-vben-admin/frontend/apps/web-antd/dist
```

`--source` 必须指向包含 `index.html` 的 `dist` 目录；未传该参数时保持原有默认行为。

### Laravel 项目扩展与一键构建

安装命令会调用 `vben-admin:publish-project`，在宿主 Laravel 项目中创建不会随 Composer 升级被覆盖的扩展层：

```text
app/Admin/                  模块和后台控制器
routes/admin.php            项目后台 API 路由，自动使用 /api/admin 前缀
resources/admin/pages       项目 Vue 页面
resources/admin/api         项目前端 API
resources/admin/locales     项目中英文语言包
resources/admin/components  项目组件
resources/admin/workspace   项目工作台
```

已有文件默认保留。需要单独补齐缺失脚手架时执行：

```bash
php artisan vben-admin:publish-project
```

安装时发布的 Demo 只用于展示页面、前端 API、Laravel 控制器和路由如何连接。Demo 不注册 `AdminModule`，不创建菜单、权限或菜单权限关联，也不会由 `vben-admin:sync` 写入数据库。`app/Admin/modules.php` 默认返回空数组。开始开发真实业务功能时，再按二次开发指南创建业务模块，并完整实现多语言、日志、权限和安全要求。

项目扩展会在构建前同步到共享前端的受控构建区；业务源码始终以 Laravel 项目中的 `resources/admin` 为准。日常修改业务前端后，可以单独构建并发布：

```bash
php artisan vben-admin:build --install --publish --force
```

依赖已安装时省略 `--install`。本地需要使用另一个共享前端工作区时通过 `--frontend=/absolute/path/to/frontend` 指定。构建命令会绕过 Turbo 结果缓存，避免项目扩展变化被旧缓存遮蔽。

构建命令会在安装依赖前校验 Node.js 和 pnpm 版本。不支持的版本会直接停止，并显示实际版本和要求，不再进入 `postinstall` 后才失败。`--install` 会按照锁文件强制重建依赖，因此也可修复曾经使用错误 Node.js 版本安装后遗留的缺失原生绑定。如果看到 Node.js 18 等版本错误，可使用项目现有的 Node 版本管理器切换：

```bash
nvm install 22.23.0
nvm use 22.23.0
corepack enable
corepack prepare pnpm@10.33.4 --activate
node --version
pnpm --version
php artisan vben-admin:build --install --publish --force
```

`node --version` 必须满足 `^22.18.0 || ^24.0.0`，`pnpm --version` 必须至少为 `10.0.0`。如果没有使用 nvm，请通过服务器实际采用的 Node.js 安装方式升级；不要通过忽略 engines 或跳过 `postinstall` 强行安装，因为构建依赖本身不支持 Node.js 18。

### 项目直接修改工作台

工作台允许由每个 Laravel 项目完整接管，不需要修改共享包或 `vendor`。在宿主项目根目录执行一次：

```bash
php artisan vben-admin:publish-workspace
```

命令会创建 `resources/admin/workspace/index.vue`，已有文件默认不会被覆盖；只有确认需要恢复共享包模板时才使用 `--force`。此后可以在该 Vue 文件中自由修改布局、组件、接口和交互。

修改后进入本包的 `frontend` 目录，按命令输出设置项目工作台的绝对路径并构建，例如：

```bash
VBEN_ADMIN_WORKSPACE="/path/to/laravel/resources/admin/workspace/index.vue" VITE_BASE=/admin/ pnpm build:antd
php artisan vben-admin:publish-assets --force
```

`VBEN_ADMIN_WORKSPACE` 只在前端构建时使用，不是 Laravel 运行时配置。没有设置时自动使用共享包默认工作台。多个项目共用同一个 Path Repository 时，每次为目标项目构建都必须传入该项目自己的文件路径，生成的静态资源仍分别发布到各项目的 `public/{VBEN_ADMIN_PATH}`。

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

管理员登录安全规则与 Laravel 环境关联：`APP_ENV=local` 会跳过管理员 IP 白名单和 Google 2FA，便于先完成本地初始化；其他环境会强制要求当前登录 IP 命中该管理员的白名单，然后才允许进入 2FA 绑定或验证。白名单填写任意有效规则即自动开启，清空即关闭，没有独立开关。切换到正式环境前，必须在“管理员”页面为每个需要登录的账号填写准确的 IPv4、IPv6 或 CIDR，否则账号会被拒绝登录。

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
php artisan vben-admin:update
```

`vben-admin:update` 会补齐缺失脚手架、执行待执行 migration、幂等同步系统数据、按新锁文件安装前端依赖、重新构建并原子发布资源，最后清理缓存。它不会创建或重置默认账号，不会覆盖已有配置和业务扩展，也不会重置非空角色授权。命令可以重复执行；中途失败后修复原因并再次执行即可。

升级前可以只预览而不写数据库或文件：

```bash
php artisan vben-admin:update --dry-run
```

由 CI 单独处理前端时使用 `--skip-frontend`，由独立数据库发布阶段执行 migration 时使用 `--skip-migrate`。当前迁移基线仍只支持空数据库全新安装；`update` 只负责同一迁移基线内的后续包版本更新，不提供旧版数据库跨基线升级路径。

## 7. 常用命令

```bash
# 全新安装基础结构
php artisan vben-admin:install

# Composer 更新包后的完整升级
php artisan vben-admin:update

# 预览升级动作，不写数据库或文件
php artisan vben-admin:update --dry-run

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

# 发布项目可自由修改的工作台Vue源码
php artisan vben-admin:publish-workspace

# 按保留天数清理过期 activity_log（建议由 Laravel Scheduler 定期调用）
php artisan activitylog:clean
```

日志默认保留 365 天，可在宿主项目 `.env` 中调整：

```dotenv
VBEN_ADMIN_ACTIVITY_LOG_DAYS=365
VBEN_ADMIN_TOKEN_TTL=720
VBEN_ADMIN_FORCE_SUPER_ADMIN_2FA=true
```

`VBEN_ADMIN_TOKEN_TTL` 是后台 Sanctum Token 的最大有效分钟数，默认 12 小时。正式环境建议保持 `VBEN_ADMIN_FORCE_SUPER_ADMIN_2FA=true`；超级管理员首次登录会进入 Google 2FA 绑定流程。包升级后统一执行 `php artisan vben-admin:update`。正式环境还必须正确配置 Laravel 可信代理，否则 IP 白名单和自动封禁可能取得错误的代理 IP。

修改后如启用了配置缓存，执行 `php artisan optimize:clear`。正式环境应先确认审计保留合规要求，再配置清理周期；不要把清理命令加入每次请求或每次登录流程。

## 8. 常见问题

### 页面返回 404

确认已经成功执行 `php artisan vben-admin:install` 或 `php artisan vben-admin:update`，并检查 `public/{VBEN_ADMIN_PATH}/index.html` 是否存在。正式 Web 服务器的站点根目录必须是 Laravel 的 `public` 目录。

### 页面打开但 JavaScript 或 CSS 返回 404

前端构建时的 `VITE_BASE` 必须与 `VBEN_ADMIN_PATH` 对应。默认路径使用 `/admin/`；例如 `VBEN_ADMIN_PATH=control-center` 时使用 `VITE_BASE=/control-center/` 重新构建。

### 登录提示账号或密码错误

默认账号只在首次安装且对应用户名不存在时创建。重复安装不会把已经修改的密码重置为 `admin`，也不会改动同名账号已有角色。可以通过 `php artisan vben-admin:create-admin` 创建其他超级管理员。

### 提示 `personal_access_tokens` 表不存在

执行：

```bash
php artisan vben-admin:install
```

安装器会在需要时发布 Sanctum migration 并运行 migration。

### 修改包的 Vue 代码后页面没有变化

修改 Vue 源码后可使用底层构建命令重新构建并发布：

```bash
php artisan vben-admin:build --publish --force
```

## 9. 项目扩展边界

- 公共后台包代码保持可升级，不在业务项目中直接修改 `vendor`；
- Laravel 业务后台扩展放在项目的 `app/Admin`；
- 项目自有前端页面和资源放在 `resources/admin`；
- 业务权限和菜单通过包提供的模块契约注册，然后执行 `vben-admin:sync`；
- 敏感操作必须同时经过 Laravel 服务端权限校验，不能只依赖前端隐藏按钮。
日志动作、操作类型、模块、多语言键和对象多语言键集中维护在发布后的 `config/vben-admin-log.php`。配置项只保存翻译键；实际文案放在各语言文件中，因此新增第三种及更多语言时无需修改日志配置或数据库。包升级新增的系统动作应与项目自定义动作合并检查，项目自定义稳定动作码不得在升级时被静默删除。
