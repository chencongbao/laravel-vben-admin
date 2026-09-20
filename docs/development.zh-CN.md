# Laravel Vben Admin 二次开发指南

> 所有新功能的请求字段、权限和批量写入必须遵循 [后台输入与权限安全规范](security.zh-CN.md)。前端权限仅负责显示，服务端授权与字段白名单是强制验收项。

本文面向安装 `chencongbao/laravel-vben-admin` 后继续开发后台功能的开发者与 AI Agent。目标是让新增功能遵循现有 Laravel/Vben 架构、权限、安全、审计和交付规则，而不是只做到“页面能打开”。

## AI 开发必须优先检查的四项要求

每次开发或修改后台功能，AI 必须首先建立并在完成前复核以下四项清单。多语言、日志、权限和安全是同一功能的组成部分，不能留到后续补做。最终交付报告必须分别写明四项的实现和验证结果；适用项缺失时不得宣布功能完成。

### 1. 多语言完整性

1. 同时建立 `resources/admin/locales/zh-CN` 与 `resources/admin/locales/en-US` 文件，两边文件名和键结构完全一致。
2. 页面标题、菜单名称、字段、占位、帮助说明、按钮、状态、弹窗、成功/失败提示、空状态及验证提示全部使用翻译键。
3. 菜单或权限名称需要展示多语言时保存稳定翻译键，不以当前中文名称作为权限判断条件。
4. 服务端返回稳定错误码和字段错误；前端按照当前语言转换，禁止显示翻译键、异常类名、原始 `Forbidden` 或仅英文服务端信息。
5. 构建后必须在中文和英文界面各验证一次。看到 `demo.title` 之类原始键表示功能未完成，即使 JSON 文件已经存在也不能验收。

### 2. 写操作日志

1. 新增、修改、删除、授权、审核、发布、启停、配置及批量写入成功后记录操作日志；列表查询等只读接口不记录。
2. 使用稳定事件码，绑定实际操作者和实际业务对象，并记录真实的修改前后差异；无变化更新不生成日志。
3. 日志与业务写入保持一致事务边界，业务回滚时不能留下成功日志。批量修改按实际变化对象生成可以按对象编号查询的记录。
4. 请求上下文需要记录 IP、方法、路径、路由、User-Agent 和 Request ID；密码、Token、Secret、Cookie、Authorization、验证码、2FA 密钥和第三方凭据必须递归脱敏。
5. 测试至少覆盖成功日志、无变化不写日志、对象查询和敏感字段脱敏。
6. 一个请求包含多类业务变更时，应按实际业务动作分别记录。例如姓名变化记录 `auth.profile.updated`，头像变化记录 `auth.avatar.updated`；两者同时变化写两条日志。

### 3. 服务端权限

1. 每个页面至少定义查看权限；新增、编辑、删除、导出、审核、发布等真实操作使用独立稳定权限码。
2. 菜单关联查看权限，Laravel 路由使用 `auth:sanctum`、`admin.user` 及对应 `admin.permission:*` 或 `admin.super-admin`。
3. 前端菜单和按钮使用相同权限码控制展示，但前端隐藏不构成授权，服务端必须独立拒绝越权请求。
4. 明确超级管理员、管理员和普通角色的数据范围及默认授权；新权限不得在同步时覆盖已有人工角色授权。
5. 测试至少覆盖允许访问、未认证 401、无权限 403、角色边界及越权参数。

### 4. 输入与数据安全

1. 所有请求由 Laravel Validator 验证，控制器或 Form Request 使用明确字段白名单；不能直接持久化 `$request->all()`。
2. 多表、权限、状态和敏感配置写入需要评估事务、并发、幂等、失败回滚及审计一致性。
3. 密码使用 Laravel Password 规则；上传限制类型、大小和尺寸；URL、文件路径、外部请求、动态组件和重定向必须限制允许范围。
4. 密码、Token、密钥、凭据、内部异常及服务器绝对路径不得进入 API 响应、前端提示或普通日志。
5. 测试至少覆盖非法输入 422、敏感数据脱敏和本功能关键失败路径；仅做前端校验或静态检查不算安全验证。

## 让 Codex 在宿主项目中自动遵守本指南

包仓库根目录的 `AGENTS.md` 会自动约束直接在包源码中进行的开发，但 Composer 安装后的 `vendor` 目录不是宿主项目规则的作用域。为了让 Codex 在宿主项目开发新功能时也自动读取本指南，请在宿主项目根目录的 `AGENTS.md` 中加入：

```md
## Laravel Vben Admin 开发规则

- 开发任何后台数据库、API、权限、菜单或 Vue 页面前，必须完整阅读 `vendor/chencongbao/laravel-vben-admin/docs/development.zh-CN.md`。
- 同时遵守该包 `AGENTS.md` 中的架构边界、安全规则、验证要求和完成报告要求。
- 如果使用 Composer Path Repository，请按实际包路径读取上述两个文件；不得直接修改普通 Composer 安装产生的 `vendor` 文件。
```

若团队不提交 `vendor`，仍可在安装依赖后读取上述路径。建议把这一段随宿主项目代码一起提交，以确保后续每次 Codex 任务都能发现规则，而不是依赖开发者临时提醒。

## 1. 先判断功能应该放在哪里

系统分成“通用后台包”和“宿主业务项目”两层。

### 应放在本包的能力

- 管理员认证、Google 2FA、登录 IP 白名单和会话管理；
- 管理员、角色、权限、菜单、系统设置；
- 通用操作审计、登录日志；
- 所有安装该包的 Laravel 项目都需要且语义一致的后台基础能力。

### 应放在宿主项目的能力

- 比赛、联赛、球队、直播源、媒体处理等业务实体；
- 业务状态机和业务权限；
- 只属于某个部署或某个平台的页面、接口和任务；
- 第三方业务接口、业务凭据和业务配置。

判断标准：如果另一个完全不同业务的 Laravel 项目安装本包后不应该自动拥有该功能，它通常就不属于本包。

禁止直接修改 `vendor/chencongbao/laravel-vben-admin`。需要共同维护包时使用 Composer Path Repository；仅开发业务功能时，把 PHP 代码放在宿主项目 `app/Admin`，前端页面放在宿主项目约定的可编辑后台源码中。

## 2. 开发前检查清单

开始修改前依次确认：

1. 阅读根目录 `AGENTS.md`、本文件、`architecture.md` 和 `api.md`。
2. 使用 `git status --short` 确认已有改动，保留不属于当前任务的文件。
3. 搜索现有控制器、模型、migration、权限代码、菜单 `view_key` 和前端页面，优先复用已有结构。
4. 明确本次是否涉及数据库、API、权限、审计、配置、状态变化和前端构建。
5. 涉及数据库或认证状态时，先写清字段、默认值、兼容方式、回滚方式和防锁死方案，再执行修改。

推荐先定义一个可验证目标，例如：“有 `match.publish` 权限的管理员可以发布比赛；无权限请求服务端返回403并记录审计”。不要把多个无关模块塞进同一个任务。

## 3. 目录与所有权

```text
config/                         包配置，仅放部署级配置
database/migrations/            包拥有的数据结构变更
routes/admin.php                固定 /api/admin 下的包路由
src/Contracts/                  宿主扩展契约
src/Definitions/                权限和菜单定义值对象
src/Http/Controllers/           包通用后台控制器
src/Http/Middleware/            认证、权限、超级管理员中间件
src/Models/                     包通用模型
src/Services/                   可测试的领域服务
src/Support/                    无状态支持类和系统设置定义
frontend/apps/web-antd/src/     Vben后台应用源码
frontend/packages/              Vben共享包；只有真正通用时才修改
tests/Unit/                     纯逻辑单元测试
tests/Feature/                  Laravel/Testbench接口和安装测试
docs/                           架构、API、安装和开发文档
```

控制器负责请求验证、权限边界和响应编排；可复用或容易出错的逻辑放进 `Services`，避免堆进 Vue 页面或控制器私有方法。

## 4. 新增一个业务后台模块

下面顺序适用于比赛、订单、内容等宿主业务模块。

安装脚手架中的 Demo 仅是代码连接示例，不是已启用的业务模块：默认不注册菜单和权限，不写数据库，也不会出现在后台菜单中。不要把 Demo 的无模块状态复制到真实功能；真实业务页面必须继续遵守本节的菜单、权限、日志和安全规则。

### 4.1 定义权限

权限代码使用稳定的“领域.动作”语义，不使用 URL 或中文名称。例如：

```text
match.view
match.create
match.update
match.publish
```

查询权限一般不是敏感权限；发布、删除、退款、密钥和权限分配等操作应标记为敏感。前端可用 `v-access:code` 控制展示，但服务端路由必须同时校验：

```php
Route::post('/api/admin/matches/{match}/publish', PublishMatchController::class)
    ->middleware([
        'auth:sanctum',
        'admin.user',
        'admin.permission:match.publish',
    ]);
```

### 4.2 注册模块和菜单

宿主项目实现 `AdminModule`，通过 `ModuleRegistry` 注册权限与菜单。菜单记录只保存稳定 `view_key`，不保存任意 Vue 文件路径。
菜单管理页不对管理员暴露 `route_name` 和 `view_key` 输入框：自定义菜单会根据路由路径自动生成稳定菜单编码与路由名称，外部链接则根据完整 URL 生成稳定编码与路由名称；页面类型同时生成 `view_key`，目录和外部链接的 `view_key` 为空。
菜单管理不提供启用或隐藏设置，有权限的有效菜单均按后台维护的树结构显示。菜单表不保存 `is_active`、`is_hidden` 字段，菜单管理接口和系统数据同步也不得接受或写入这两个字段。
工作台 `dashboard.workspace` 是登录后默认菜单，不参与菜单管理和拖拽排序；服务端必须在查询顺序与返回的 `meta.order` 两层保证其始终位于侧边栏第一位。工作台页签必须设置为固定且不可关闭，同时禁用取消固定，关闭当前、关闭其他及批量关闭均不得移除工作台。

工作台的业务内容属于宿主项目。需要完整修改时执行 `php artisan vben-admin:publish-workspace`，只编辑宿主项目的 `resources/admin/workspace/index.vue`，不得修改普通 Composer 安装的 `vendor`。构建时通过 `VBEN_ADMIN_WORKSPACE` 传入该文件的绝对路径；未传入时继续使用共享包默认工作台。项目工作台可以自由组合 Vue 组件和调用项目 API，但接口权限仍必须由 Laravel 服务端校验。

注册后先预览，再同步：

```bash
php artisan vben-admin:sync --dry-run
php artisan vben-admin:sync
```

同步是幂等的，不应删除自定义记录，也不应重置角色分配。

### 4.3 增加前端页面映射

后端菜单返回 `view_key` 后，由：

```text
frontend/apps/web-antd/src/api/core/menu.ts
```

将合法的点分段键转换为项目页面路径，并只在编译期页面表中匹配组件。例如 `match.list` 转换为 `/match/list/index`。未知键或没有被编译的页面保持进入404/兜底页，禁止将数据库值直接传给动态 `import()`。

宿主项目业务页面统一放在 `resources/admin/pages`，例如 `view_key=match.list` 对应 `resources/admin/pages/match/list/index.vue`。`vben-admin:build` 会把 `pages`、`api`、`locales`、`components` 同步到共享前端的受控构建区，再通过静态 `import.meta.glob` 编译；数据库值只用于匹配编译期页面表，不参与任意动态导入。

宿主 API 路由统一放在 `routes/admin.php`。包会自动添加 `api` 中间件组和 `/api/admin` 前缀，项目路由仍须声明 `auth:sanctum`、`admin.user` 及逐操作的 `admin.permission:*` 校验。

前端构建环境必须使用 Node.js `^22.18.0 || ^24.0.0` 和 pnpm `>=10.0.0`。`vben-admin:build` 会在执行依赖安装前检查版本；不得为兼容 Node.js 18 而跳过 workspace 的 `postinstall`，否则生成的内部包可能不完整。

面向部署只使用两个幂等入口：首次安装执行 `php artisan vben-admin:install`，Composer 包升级后执行 `php artisan vben-admin:update`。两者默认完成 migration、系统数据同步、前端依赖安装、构建、原子发布和缓存清理；底层 `sync`、`build`、`publish-assets` 只用于开发与排错。已有配置、`app/Admin`、`routes/admin.php`、`resources/admin`、账号密码和非空角色授权均不得因重复安装或升级被覆盖。

页面中的操作按钮继续使用与服务端相同的权限代码。按钮隐藏只改善体验，不能作为安全控制。

## 5. 数据库开发规则

### 必须遵守

- 每次结构变化新增 migration；已经被其他环境执行的 migration 不回写、不改名。
- 表名通过 `config('laravel-vben-admin.tables.*')` 获取，不在模型、外键或验证规则里散落硬编码。
- 布尔字段写明确默认值；JSON字段在模型中声明 `array` cast；时间字段使用 `datetime` cast。
- 外键、唯一约束、查询索引和回滚逻辑必须与真实访问方式一致。
- 多表写入使用 `DB::transaction()`。
- migration 执行前查看 `php artisan migrate:status`；正式环境先备份并在测试环境演练。

### 数据迁移兼容性

新增强制安全字段时，要避免让现有管理员全部无法登录。必须说明旧数据默认行为、初始化方式和上线顺序。不得通过修改生产数据库或手写 SQL 绕过 migration。

## 6. API约定

### 项目主动异步发送 Telegram 消息

后台包统一提供 `SendTelegramMessage` Job 和 `TelegramMessageDispatcher`，底层复用 Foundation
的 Telegram 发送器、`notice` 队列、重试和失败日志。业务代码不得自行
拼接 Telegram API URL，也不得把 Bot Token 或 Chat ID 传入 Job Payload：

```php
use Chencongbao\LaravelVbenAdmin\Jobs\SendTelegramMessage;
use Chencongbao\LaravelVbenAdmin\Services\TelegramMessageDispatcher;

SendTelegramMessage::dispatch([
    'event' => 'example.completed',
    'object_id' => 1001,
], 'json', '业务通知');

final class ExampleService
{
    public function __construct(private TelegramMessageDispatcher $telegram) {}

    public function handle(): void
    {
        $this->telegram->text('任务处理完成', '业务通知');
        $this->telegram->json([
            'event' => 'example.completed',
            'object_id' => 1001,
        ], '业务通知');
    }
}
```

`text()` 会转义外部文本，`json()` 适合结构化内容。`html()` 只能接收调用方
已确认安全的 Telegram HTML，不得直接传入用户内容。实际发送依赖已运行的
`notice` 队列 Worker。

后台API固定使用 `/api/admin`，不要根据 `VBEN_ADMIN_PATH` 改动API前缀。浏览器路径和API路径是两套独立概念。

### 请求验证

- 所有输入通过 Laravel Validator 校验；不要把前端校验当成可信边界。
- 数组需要同时验证数组本身和每个元素。
- 更新接口使用 `sometimes` 明确部分更新语义。
- 密码使用 Laravel `Password` 规则；上传文件限制类型、大小和尺寸。

### 响应与错误

- 成功响应保持稳定数据结构。
- 可处理的业务错误同时返回稳定 `code` 和兜底 `message`。
- 前端根据 `code` 使用当前语言翻译，不能把服务端英文信息直接展示给中文用户。
- 未认证返回401，无权限返回403，验证失败返回422；不要全部包装成HTTP 200。

示例：

```json
{
  "code": "MATCH_NOT_PUBLISHABLE",
  "message": "The match cannot be published."
}
```

如果API有变化，同一个任务中更新 `docs/api.md`。

## 7. 权限与审计

每个写接口至少回答四个问题：

1. 哪个权限代码允许执行？
2. 是否属于超级管理员专属操作？
3. 是否需要事务？
4. 审计记录哪些变更？

审计应包含操作者、动作、对象、修改前后值和请求上下文。以下内容不得进入审计变更、普通日志或API用户信息：

- 密码及密码确认；
- Sanctum Token、挑战Token；
- Google 2FA密钥；
- API Key、Authorization头和第三方凭据。

敏感按钮必须同时具备服务端权限中间件；只加 `v-access` 不算完成。

### 7.1 统一日志规则

登录日志和操作日志统一写入 Spatie Activitylog 的 `activity_log` 表，通过 `log_type=login` 与 `log_type=operation` 区分。旧的 `admin_login_logs`、`admin_audit_logs` 表及对应 Eloquent 模型已经移除。业务代码不得重新创建或直接写这些旧表，也不要自行拼装 `activity_log` 数据。

通用写操作通过包提供的 `AuditRecorder` 记录：

```php
use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;

public function __construct(private readonly AuditRecorder $audit) {}

$this->audit->record(
    $request->user(),
    'match.published',
    $match,
    ['status' => ['before' => 'draft', 'after' => 'published']],
    ['source' => 'admin'],
);
```

动作名使用稳定的 `领域.资源.动作` 或 `领域.动作` 英文编码，不使用会变化的中文标题。`subject` 传被操作的 Eloquent 模型；没有单一对象的批量或配置操作传 `null`。一次请求修改多项配置时只写一条请求级日志，把各项前后值放入同一个 `changes`，避免循环生成碎片日志。

每个动作编码还必须注册到 `config/vben-admin-log.php` 的 `actions` 中，定义 `type`、`module`、`label_key` 和允许进入审计详情的 `request_fields` 请求字段白名单。未声明 `request_fields` 时不保存任何请求参数，不得恢复记录整个 `$request->all()` 的黑名单模式。配置只保存多语言键，不保存中文、英文或其他具体译文；每种前端语言在自己的语言文件中实现同一个键。宿主项目可以在发布后的配置中添加业务动作。未注册动作仍会写入和查询，接口返回空元数据、前端回退显示原始编码，但请求参数保持为空。`subjects` 与 `fields` 同样只配置多语言键；详情接口返回当前对象的字段键映射，前端翻译变更字段，缺失时回退原字段名。业务动作筛选展示全部已注册动作；操作者选项按角色数据范围提供当前用户，日志列表和详情继续执行服务端数据范围校验。

记录器自动补充操作者、IP、HTTP 方法、请求路径、User-Agent，并且只记录当前动作 `request_fields` 明确允许的请求参数。白名单通过后仍执行第二层递归脱敏：包含 `password`、`token`、`secret`、`credential`、`authorization`、`cookie`、`private_key`、`captcha`、`totp`、`two_factor` 的键替换为 `[REDACTED]`；上传内容只记录文件元数据。新增字段必须先判断是否确有审计价值，敏感字段不得加入白名单。

登录流程由包内部的 `LoginRecorder` 统一记录成功、失败原因和 `client_type`。宿主业务不要调用它伪造登录事件。日志写入应与关键业务写入处于合理的事务边界；审计失败是否阻断业务必须在需求中明确，安全和权限类变更默认要求同时成功。

查询页面继续使用 `/system/login-logs` 与 `/system/audit-logs`，不要直接把 Spatie 模型结构暴露为新的前端契约。数据库中的 `created_at` 按 UTC 保存，API 返回 ISO-8601，前端统一转换为北京时间。

## 8. 认证和会话不可破坏规则

- Token撤销或过期后，前端收到401必须清理本地认证并跳转登录页，不能再次用失效Token调用退出接口。
- Bearer Token 只持久化到当前标签页的 `sessionStorage`，关闭标签页后失效于浏览器存储；锁屏密码只保存在运行内存中，不得进入 Local Storage、Session Storage 或其他持久化介质。
- 多个并发401只能触发一次退出跳转。
- 管理员会话只允许查询和撤销自己的Token。
- 当前会话不能通过“撤销其他会话”接口删除，正常退出走 `/auth/logout`。
- 非 `local` 环境必须先命中管理员IP白名单，才能进入Google 2FA绑定或验证。
- IP白名单有内容即开启，留空即未开启；支持IPv4、IPv6和CIDR。
- `APP_ENV=local` 跳过IP白名单和Google 2FA，但不删除或修改已保存配置。
- 认证错误使用稳定错误码并跟随前端语言显示。

修改这些行为时必须覆盖成功、拒绝、Token失效、并发请求和非本地环境分支。

## 9. 系统设置开发

需要后台动态修改的非敏感通用设置使用 `admin_settings` 和 `SystemSettings` 定义，不要随意增加 `.env`。部署必须在启动前确定、涉及基础设施或密钥的值才放配置文件/环境变量。

密码校验必须统一使用 `AdminPasswordPolicy`，不得在控制器、命令或前端单独写死另一套规则。`system.password_strength=weak` 表示至少 6 位，不强制字符组合；`strong` 表示至少 12 位并包含大小写字母和数字，且为默认值。策略变化只约束后续新增或修改的密码，不主动使已有密码失效。

用户新增、用户编辑和个人修改密码界面必须通过 `/auth/password-policy` 读取当前策略，并同时更新密码输入框 placeholder、可见帮助文字与前端即时验证；服务端仍以 `AdminPasswordPolicy` 为最终校验依据。

系统设置需要明确：键名、类型、默认值、验证规则、公开启动配置是否可见、管理权限和刷新后生效方式。密钥和凭据不得进入通用设置接口。

## 10. Vben前端开发规则

- 新增后台业务功能必须作为完整能力交付：页面标题、说明、字段、占位提示、按钮、状态、确认提示、成功提示、空状态和错误提示同时维护 `zh-CN`、`en-US`，两种语言的文件名和键结构一致；前端与服务端表单验证必须跟随当前语言，服务端使用稳定错误码或字段错误由前端语言包映射，禁止直接显示 `validation.regex`、翻译键、异常类名或单一语言原始消息。数据表、字段、索引、外键和约束必须使用新的可回滚 migration，禁止修改已经发布或执行的 migration。每个可进入的后台功能必须同时注册菜单和权限：至少包含页面查看权限，新增、编辑、删除、导出、审核等操作按实际能力拆分稳定权限码；菜单关联查看权限，服务端路由绑定权限中间件，前端按钮使用同一权限码。菜单、权限、父子关系和关联关系必须进入模块注册或同步流程，保证全新安装可写入、重复同步幂等且不覆盖人工角色授权，并在任务文档中明确默认角色是否授权。API、数据库、菜单路径、权限码、默认授权、部署命令及 migration/安装/同步/允许/拒绝/中英文验证测试必须同步完成，缺少任一项不得标记新功能完成。
- 页面优先放在 `frontend/apps/web-antd/src/views`，API封装放在 `src/api`。
- 复用Vben和Ant Design Vue现有组件、主题变量和布局，不写只适配一张截图的固定定位。
- 后台一级页面必须通过 `Page` 的 `title` 保留内容区顶部当前页面标题导航；页面内部布局、列表工具栏或配置Tabs不能替代或移除这一层导航。
- 文案同时维护 `zh-CN` 和 `en-US`；错误优先按稳定API错误码翻译。语言文件按功能域拆分：跨模块复用文案进入 `common.json`，认证、个人中心、系统管理和配置分别使用 `auth.json`、`profile.json`、`system.json`、`configuration.json`；新业务使用独立同名文件。刷新、筛选、查询、重置、自动刷新、导出等跨列表操作统一复用 `common.actions`，编号、创建时间等跨列表字段复用 `common.fields`，时间单位复用 `common.time`。中文编号显示“编号”，英文保持“ID”。禁止重新创建承载全部页面文案的单体 `page.json`，各语言的文件名和键结构必须一致。
- 数据库日期时间按UTC保存，API返回带时区的标准ISO-8601值；后台所有面向用户的时间统一使用 `src/utils/datetime.ts` 转换为 `Asia/Shanghai`（北京时间，UTC+8）并显示为 `YYYY-MM-DD HH:mm:ss`。禁止使用浏览器本地时区、服务器系统时区或在页面内重复实现格式化函数。
- 表格必须考虑加载、空数据、分页、窄屏横向滚动和操作权限。
- 所有普通后台数据列表默认按主键 `id` 倒序查询（`ORDER BY id DESC`），确保最新创建的数据位于最前；前端不得再把当前页数据二次反转。业务存在明确时间序列字段时，可以按该字段倒序并使用 `id DESC` 作为稳定的次级排序。菜单、权限等可拖拽父子树不属于普通数据列表，必须继续按持久化的 `sort` 和层级关系展示，不能用全局倒序破坏人工排序。
- 分页列表必须使用 `src/utils/pagination.ts` 的 `createAdminPagination()` 初始化分页；系统设置 `system.page_size` 通过公开应用启动配置注入，作为所有列表首次打开时的默认每页数量，并自动加入分页下拉选项。所有 Ant Pagination 的分页数量选择器均由全局表格样式提供紧凑且足以容纳最长单位文案的宽度，并按页面语言统一补充分页单位（中文为“{数量} 条/页”，英文为“{数量} / page”）；下拉选项和关闭下拉后的选中值都必须显示完整单位，选中内容需要为右侧箭头保留独立空间并保持左右视觉留白平衡。用户在列表内选择其他每页数量只覆盖当前页面；后端未收到 `per_page` 时必须使用 `AdminPagination::defaultPageSize()`，不得在控制器或Vue页面硬编码 `20`。
- 列表页统一使用 `src/components/system/list-toolbar.vue` 组织顶部操作：刷新、筛选、重置、列显示和批量操作放左侧；自动刷新、导出、新增、历史记录等功能放右侧。操作栏位于表格上方，直接排列按钮，不使用Card、背景、边框或额外内边距；Table所在容器使用 `admin-table-card`，内容内边距为0并统一直角，只保留Table自身单层外框和内部横竖分隔线，避免边框叠加变粗。不得把所有按钮无层级地堆在同一侧，窄屏时必须允许自动换行。
- 列表手动刷新统一使用 `src/components/system/list-refresh-button.vue`。它与顶栏刷新按钮共同调用Vben `useRefresh()`，只重新渲染当前路由页面，不刷新整个浏览器。`auto-refresh.vue` 在倒计时结束后也调用相同的 `useRefresh()`；三处入口必须保持完全相同的局部页面刷新语义，仅触发时机不同。页面不得自行实现其他刷新逻辑。
- 顶部操作统一使用 `src/components/system/permission-button.vue`：按钮必须传入Iconify图标，使用直角样式；需要授权的业务按钮通过 `permission` 传入后台权限编码，无权限时组件自动隐藏。`permission` 支持单个编码或编码数组（数组为满足任一权限即可显示），不需要权限的刷新、筛选、重置可以不传。
- 后台公共视觉规则统一维护在 `src/styles/admin-ui.css`（按钮、复选框、列表工具栏、搜索Card、搜索字段、行操作和批量操作）与 `src/styles/admin-table.css`（Table）中；公共组件和业务页面只输出结构及约定类名，不得在单个Vue文件内重复定义或覆盖这些样式。所有Ant Design按钮和复选框统一使用直角样式，圆形状态指示、头像、开关等非按钮元素不受此规则影响。Table直角规则必须同时覆盖固定表头的 `.ant-table-header`、滚动表体 `.ant-table-body` 及其内部 table，不能只清除外层容器和表头单元格的圆角。
- 菜单管理树使用 `@he-tree/vue` 的 `Draggable`，独立手柄和菜单标题区域均可启动拖拽，右侧业务操作按钮不得触发拖拽；必须保留清晰的投放占位、折叠目录悬停自动展开、边缘滚动、键盘树导航、无权限及保存期间禁用拖拽、失败后恢复服务端顺序。组件只负责前端交互，完整树校验、防循环、`system.menu.update` 服务端权限、事务和审计仍由菜单重排接口负责。
- 菜单管理在桌面端使用左侧菜单树、右侧新增/编辑表单的双栏工作区，点击树节点直接在右侧编辑；窄屏自动改为上下排列。表单沿用菜单实体已有字段，菜单仅绑定单个访问权限编码；角色对菜单和权限的批量授权仍统一在角色管理维护，不得在菜单表单重复实现另一套角色授权关系。
- 菜单新增必须提交唯一 `code`、标题 `title` 和类型 `type`；`parent_id` 为空表示顶级菜单，且不得选择固定工作台作为父级。菜单图标为空时服务端统一回退为 `lucide:list`。目录和页面的 `route_path` 是后台前端路由而不是 API 路径，服务端允许为空；外部链接类型必须填写完整的 `http://` 或 `https://` 地址，服务端拒绝其他协议、清空 `view_key`，有效菜单接口通过 `meta.link` 和 `meta.openInNewWindow` 让前端在新标签页安全打开。自定义菜单前端根据路由或外部地址生成稳定 `code`、`route_name`，只有页面类型生成 `view_key`。父级不得指向自身或后代，系统菜单身份不可修改，固定工作台不可编辑或删除。
- 权限新增必须提交唯一稳定 `code` 和名称 `name`；权限码格式为小写点分段，例如 `system.user.create`。`parent_id` 为空表示根权限，操作权限应挂到对应功能查看权限下面。权限表单不包含说明、启用、敏感、HTTP 方法和 HTTP 路径字段；`sort` 仍作为树形顺序的持久化字段，由拖拽排序维护。父级不得指向自身或后代，系统权限身份不可修改。
- 菜单与权限是多对多关系，唯一事实来源为 `admin_permission_menus`：从菜单表单提交 `permission_ids` 与从权限表单提交 `menu_ids` 完全等价，都会在同一事务中全量同步同一组关联行；提交空数组表示清空关联，编辑请求省略关联字段表示保持原关系。`admin_menus.permission_code` 已移除，不得恢复或同时维护第二套关系。
- 菜单关联权限只定义该菜单的访问要求和前端 `meta.authority`，不等于把权限授予角色；角色编辑必须分别保存 `admin_role_menus` 和 `admin_role_permissions`。非超级管理员只有同时拥有该角色菜单且满足菜单关联权限时才看到菜单；未关联任何权限的角色菜单只校验角色菜单关系。超级管理员隐式拥有全部权限并可查看全部菜单，固定工作台对所有已登录后台用户可见。
- 内置 `manager` 管理员角色只允许 `administrator` 超级管理员通过角色管理页编辑其菜单和权限授权；其他角色即使拥有 `system.role.update`，服务端也必须返回 `ADMIN_SUPER_ADMIN_REQUIRED`。管理员角色的标识、内置名称和启用状态固定。管理员及其他非超级管理员在拥有 `system.role.create`、`system.role.update` 时，可以新增和编辑普通自定义角色，但列表、详情和编辑均不得暴露 `administrator`、`manager` 两个内置角色，并且只能把自己实际拥有的菜单与权限分配给普通角色。角色编辑器必须通过角色模块的 `/system/roles/access-options` 加载当前操作者可分配的菜单和权限树，不得依赖权限管理或菜单管理列表接口；新安装的默认授权不勾选菜单管理、权限管理，但超级管理员后续可以主动分配，系统同步必须保留非空的人工授权，不得再次强制撤销。
- 用户管理允许具有 `system.user.update` 的管理员编辑自己的姓名、密码、登录白名单和 2FA 等账号资料，但当前登录账号不能修改自己的角色或启用状态。前端角色控件必须显示角色名称而不是裸角色 ID，并在编辑本人时禁用角色和状态；服务端对角色或状态的实际变更分别返回 `ADMIN_SELF_ROLE_CHANGE_DENIED`、`ADMIN_SELF_STATUS_CHANGE_DENIED`。
- 用户新增表单必须通过 `/system/users/role-options` 加载角色选项。超级管理员可以看到所有启用且非超级管理员的角色；其他操作者只看到权限集合和菜单集合均不超过自身有效授权的角色。服务端新增和编辑执行同一子集校验，绕过选项接口提交越级角色返回 `ADMIN_PRIVILEGE_ESCALATION_DENIED`。任何操作者都不得通过用户新增或编辑分配 `is_super_admin=true` 的角色；新增时返回 `ADMIN_SUPER_ROLE_ASSIGNMENT_DENIED`，编辑时返回 `ADMIN_PRIVILEGE_ESCALATION_DENIED`。
- 用户删除使用独立的 `system.user.delete` 权限并记录 `system.user.deleted` 审计。只有固定内置账号 `cmsadmin`、`admin` 禁止删除，前端不显示其删除按钮，服务端直接调用返回 `PROTECTED_ADMIN_USER_DELETE_DENIED`；之后通过用户管理新增的账号均可删除，包括分配 `manager` 管理员角色的账号。非超级管理员仍不得越权删除持有超级管理员角色的账号，服务端返回 `ADMIN_PRIVILEGE_ESCALATION_DENIED`。删除用户时同步撤销其访问令牌，角色关联由外键级联清理。
- 服务端授权始终以路由中间件绑定的稳定权限 `code` 为准，菜单路由、菜单是否显示和按钮隐藏都不能替代服务端权限校验。菜单、权限的新增、编辑、删除分别要求对应的 `system.menu.*`、`system.permission.*` 权限，并记录操作审计。
- 查看与写入必须使用独立权限：系统设置使用 `system.setting.view` / `system.setting.update`，主题配置使用 `system.theme-setting.view` / `system.theme-setting.update`；拥有查看权限不得调用保存接口，前端保存按钮也必须绑定对应更新权限。
- 菜单和权限的新增、编辑、拖拽排序不仅要验证关联 ID 存在，还必须验证当前操作者有权分配每个 `parent_id`、`permission_ids` 和 `menu_ids`；超出操作者可分配范围统一返回 `ADMIN_PRIVILEGE_ESCALATION_DENIED`，不得借关联关系挂载本人不可见的父级、菜单或权限。
- 后台管理员身份摘要统一使用 `src/composables/use-admin-identity.ts`：第一行显示本地化角色名称，第二行优先显示去除首尾空格后的姓名，姓名为空时回退用户名。顶部用户入口和个人中心资料卡必须复用该逻辑，不得分别拼接显示字段。
- 路由页面必须保持唯一根元素。全局内容区使用 `Transition mode="out-in"`，路由组件若以Fragment形式输出多个根节点，离开页面时可能无法完成过渡并导致下一个页面内容不挂载；Modal、Drawer等同页节点也必须包在该页面的唯一根容器内。
- 数据列表统一添加 `admin-data-table` 类并启用 Ant Design Vue 的 `bordered`；纵向滚动统一通过 `useAdminTableScrollY()` 根据数据区在当前视口中的实际位置动态计算原生 `scroll.y`，窗口尺寸或上方内容高度变化时自动重算，固定表头和分页，只让中间数据行在表格内部滚动。公共逻辑必须根据数据区实际溢出状态维护 `admin-data-table--scrollable-y`：需要纵向滚动时保留 Ant Table 的滚动条占位列，数据未溢出时使用 `overflow-y: auto`、隐藏该占位列，并同步将固定右列的 `right` 偏移归零，避免少量数据右侧残留滚动条宽度的空白边界。表格横向滚动轨道统一由 `admin-table.css` 隐藏，不得按页面或溢出像素添加特殊类；真实宽表仍保留横向滚动能力。已有横向滚动的页面需要同时保留 `scroll.x`。不要使用固定的视口减数或 `sticky` 代替动态表格内部滚动。表格分页控件统一整体居中。表格使用有区分度的浅色表头、横向行线和纵向列线；悬停行需要完整高亮，固定在右侧的操作列背景必须与当前行一致。
- 默认数据表使用中等紧凑密度：表头单元格内边距为 `10px 12px`，数据单元格为 `9px 12px`，普通行视觉高度约44px；除触控专用页面或复杂多行内容外，不得自行放大行高。
- 行操作只有一至两项时可以直接展示；三项及以上时保留一个高频主操作，其余收入“更多”菜单。菜单按业务操作、辅助操作、危险操作分组，危险操作置底并二次确认；复杂流程进入详情抽屉，不继续扩大操作列。
- Table行操作使用 `PermissionButton` 的 `icon-only` 模式，点击区域固定为 `32px × 32px`，并必须提供 `tooltip`（该值同时作为 `aria-label`）。固定在右侧的操作列由全局表格样式使用紧凑内边距并居中，按钮及间距必须完全位于列宽内，不得溢出到纵向滚动条区域。桌面端最多直显三个高频图标和一个“更多”图标；移动端只保留“更多”。删除、强制下线等危险操作使用危险色并二次确认，不得只用颜色表达含义。
- 普通操作按钮统一使用当前主题色的边框、文字和图标，主按钮使用主题色背景；危险操作统一使用红色边框、文字和图标，不随普通按钮颜色覆盖。
- 每个可进入的后台菜单页面必须在标题下方提供简短描述，说明该页面的用途和主要管理范围；中英文描述统一维护在页面语言包中。
- 后台卡片、输入框、页签和预览控件的圆角必须使用全局 `--radius` 主题变量，不得写死像素值；头像、状态圆点等必须保持圆形的业务元素除外。
- 后台保存的明暗模式是新浏览器的全局默认值；用户通过顶栏明暗按钮选择的 `light`、`dark` 或 `auto` 是当前浏览器个人偏好，必须本地持久化并在刷新后优先恢复。顶栏切换不得要求系统设置权限，也不得直接修改全局后台配置。
- 所有列表页必须提供“筛选”入口，筛选区默认至少包含使用 `common.fields.id` 的“编号”精确搜索；业务页面按需追加名称、状态、时间等字段，不得移除默认编号条件。`resource-page.vue` 与 `log-page.vue` 会自动补齐编号字段，独立列表页必须显式接入。列表搜索条件使用 `list-search-panel.vue` 独立直角Card展示，具体字段使用 `list-search-field.vue` 输出“左侧固定标签 + 右侧输入控件”的直角连体样式。右侧占位提示直接使用字段名称，不重复添加“请输入”或“请选择”。搜索区默认显示并只占一行，字段按实际内容宽度自适应排列，查询和重置紧跟最后一个可见搜索字段，不得被推到Card最右侧。条件在当前宽度下超过一行时，组件自动显示“展开/收起”，工具栏左侧“筛选”按钮仍可整体显示或隐藏搜索Card。不要把多个输入框直接塞进工具栏，也不要让搜索条件改变表格宽度。
- 需要定时更新的页面使用 `auto-refresh.vue`。组件按页面 `storageKey` 保存开关和间隔，页面不可见或请求尚未结束时暂停倒计时；触发时只调用列表加载方法，不刷新整个浏览器页面。
- 小数据和当前页导出使用 `table-export-button.vue`，导出按钮放工具栏右侧并传入查看或导出权限。组件会添加 UTF-8 BOM、转义 CSV 内容并防止公式注入。大数据导出不得把全部记录一次性拉到浏览器，应参考异步任务模式：保存当前筛选条件、队列分块生成文件、限制同一管理员并发任务、提供进度和历史下载，并增加独立服务端导出权限。
- 统一行操作可使用 `table-row-actions.vue`。调用方按显示优先级传入动作：前三个在桌面端显示为图标，其余进入“更多”；移动端全部进入“更多”。组件负责权限过滤和布局，危险操作的确认弹窗及服务端权限仍由业务页面负责。
- 可选批量操作使用 `table-batch-actions.vue`，仅在业务页面明确引入并传入操作项时显示，不默认添加到所有列表。按钮统一显示“批量图标 + 文字 + 向下箭头”，选中数量直接放在按钮文字中，展开时箭头向上；下拉菜单使用直角、统一宽度和“操作图标 + 文字”结构，危险操作默认红字并仅在悬停时显示浅红背景。下拉菜单不提供额外的“清空选择”，用户通过表格复选框取消选择，批量操作成功后页面自动清空选择。页面负责Table行选择状态和批量接口，组件负责选中数量、权限过滤、禁用状态、下拉菜单及危险操作样式；批量写操作必须使用服务端专用接口、权限校验、事务和操作日志，不得在前端循环调用单条写接口。
- 登录日志和操作日志均保持只读，只提供查看权限和查询、筛选、导出能力。
- 表单必须考虑新增、编辑、服务端验证失败、重复提交和保存后的数据刷新。
- 不直接修改 `node_modules`；共享包只有被多个页面真实复用时才修改。

顶部操作按钮调用示例：

```vue
<script setup lang="ts">
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
</script>

<template>
  <ListToolbar>
    <template #left>
      <PermissionButton icon="lucide:refresh-cw" @click="load">刷新</PermissionButton>
      <PermissionButton icon="lucide:filter" @click="search">筛选</PermissionButton>
    </template>
    <template #right>
      <PermissionButton
        icon="lucide:plus"
        permission="article.create"
        type="primary"
        @click="openCreate"
      >
        新增文章
      </PermissionButton>
    </template>
  </ListToolbar>
</template>
```

这里的 `article.create` 必须先由包模块或宿主模块注册到后台权限表，并分配给角色；登录后 `/access/permissions` 返回当前管理员权限，组件据此动态显示。超级管理员的 `*` 权限自动通过。前端组件只负责界面可见性，对应写接口仍必须配置 `admin.permission:article.create` 服务端中间件。

修改源码后至少运行：

```bash
cd frontend/apps/web-antd
../../node_modules/.bin/vue-tsc --noEmit --skipLibCheck
```

列表工具组件示例：

```vue
<ListToolbar>
  <template #left>
    <PermissionButton icon="lucide:refresh-cw" @click="load">刷新</PermissionButton>
    <PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">筛选</PermissionButton>
    <AutoRefresh :loading="loading" storage-key="match-list" @refresh="load" />
  </template>
  <template #right>
    <TableExportButton
      :columns="exportColumns"
      filename="比赛列表"
      permission="match.view"
      :rows="rows"
    />
    <PermissionButton icon="lucide:plus" permission="match.create" type="primary">新增</PermissionButton>
  </template>
</ListToolbar>
```

这里的前端导出只包含传入的 `rows`。如果产品文案是“导出全部”，必须实现有权限校验的服务端异步导出接口，不能用当前页导出冒充全量导出。

批量操作按需接入示例：

```vue
<TableBatchActions
  :actions="[
    { key: 'enable', label: '批量启用', icon: 'lucide:circle-check', permission: 'article.update' },
    { key: 'disable', label: '批量禁用', icon: 'lucide:circle-slash', permission: 'article.update', danger: true },
  ]"
  :selected-count="selectedRowKeys.length"
  @action="handleBatchAction"
/>
```

调用页面还需给Table配置 `row-selection`，并在 `handleBatchAction` 中调用对应的服务端批量接口。没有批量业务的页面不要引入该组件。

生产构建和发布：

```bash
cd frontend
VBEN_ADMIN_WORKSPACE="/path/to/laravel/resources/admin/workspace/index.vue" \
VITE_BASE=/admin/ corepack pnpm \
  --filter=@chencongbao/laravel-vben-admin-web run build

cd /path/to/laravel-host
php artisan vben-admin:publish-assets --force
```


如果配置了其他 `VBEN_ADMIN_PATH`，`VITE_BASE` 必须使用相同路径并带首尾 `/`。
如果项目没有发布自有工作台，可省略 `VBEN_ADMIN_WORKSPACE`，构建会使用包内默认页面。

## 11. 测试与验证分层

不要把一种检查说成另一种证明。

### 静态检查

```bash
php -l path/to/file.php
vendor/bin/pint --test
cd frontend/apps/web-antd
../../node_modules/.bin/vue-tsc --noEmit --skipLibCheck
```

静态检查只能证明语法、格式或类型，不能证明接口和页面真的可用。

### 自动化测试

- 纯逻辑类放 `tests/Unit`；
- 路由、认证、权限、数据库和命令放 `tests/Feature`；
- 至少覆盖成功、无权限、无效输入和关键边界；
- migration需要验证全新安装和已有数据库升级两条路径。

包安装开发依赖后运行：

```bash
composer install
vendor/bin/phpunit
```

### 运行时验证

- 在Laravel宿主中执行migration并检查状态；
- 使用真实HTTP请求验证状态码、错误码和数据库结果；
- 构建并发布前端后在浏览器检查页面、刷新、错误提示和权限；
- 外部服务、真实邮件、真实支付或真实设备没有验证时必须明确标为未验证。

## 12. 新功能标准流程

1. 确认功能属于包还是宿主项目。
2. 写清目标、范围、不包含内容和验收标准。
3. 检查现有代码、文档、数据库和Git状态。
4. 设计数据结构、API、权限、审计、错误码和页面状态。
5. 数据库变化先写migration。
6. 实现服务端验证、授权、事务和审计。
7. 实现前端API封装、页面、权限展示和中英文文案。
8. 同步API、安装或架构文档。
9. 执行静态检查、单元/功能测试和运行时验证。
10. 构建并发布静态资源，确认浏览器加载的是新Hash资源。
11. 输出修改文件、数据库/API/配置变化、部署步骤、测试结果和未完成项。

## 13. 任务说明模板

后续交给开发者或Codex时，建议使用以下模板：

```markdown
# 功能名称

## 目标
一句话描述可验证结果。

## 范围
- 允许修改的后端、前端和文档模块。

## 不包含
- 本次不开发的相邻能力。

## 数据库
- 新增字段、索引、默认值、旧数据处理和回滚。

## API
- 方法、路径、认证、权限、请求、响应和稳定错误码。

## 页面
- 入口、字段、操作以及加载/空/错误/权限状态。

## 权限与审计
- 权限代码、敏感级别和审计内容。

## 验收标准
- 成功场景。
- 无权限场景。
- 无效输入和失败恢复。
- 刷新、重新登录和多语言场景。

## 验证
- 静态检查。
- 自动化测试。
- Laravel运行时请求。
- 浏览器运行时检查。
```

使用此模板不代表需求自动成立；如果需求与现有代码、API文档或宿主业务规则冲突，必须先列出冲突并确认，不能擅自选择一种实现。

## 14. 操作日志规则

- 后台新增、修改、删除、授权、审核、发布、启停、敏感配置和批量写入成功后必须通过 `AuditRecorder` 记录；查询和没有真实变化的空更新不记录。
- `event` 使用稳定的小写点分段编码，前端同时维护中英文动作名称。`causer` 绑定实际后台账号，存在业务模型时 `subject` 必须绑定实际模型和主键。
- 新增保存新值，修改保存真实的修改前后差异，删除保存删除前快照。批量写入按实际变化的对象分别记录，并在上下文保存批次编号和影响数量。
- HTTP 操作记录 IP、方法、路径、路由名称、User-Agent 和可用的 Request ID；非 HTTP 操作记录任务或命令来源，不伪造请求信息。
- 请求参数、变更和上下文在写入前统一递归脱敏；密码、Token、Secret、Credential、Authorization、Cookie、私钥、验证码、TOTP 和 2FA 密钥不得明文进入日志。
- 操作日志页面只读，服务端要求 `system.audit.view`。列表只返回摘要，详情接口返回完整的已脱敏变更和请求上下文；不提供删除接口。操作者筛选使用服务端返回的账号下拉列表：超级管理员可选全部账号，其他账号不得看到超级管理员；相同数据范围必须同时应用于列表和详情接口。
- 测试至少覆盖：真实写入、空更新不写、动作编码、操作者、对象关联、修改前后值、筛选、详情权限、递归脱敏及 `id DESC`。

## 15. 登录日志可见范围

- 登录日志查询必须执行服务端数据范围限制，不能只依赖页面或菜单隐藏。
- 具有启用中超级管理员角色的账号可查看全部登录日志；其他账号只可查看非超级管理员的日志。
- 登录日志操作者筛选使用服务端返回的账号下拉列表；超级管理员可选全部账号，其他账号的选项中排除具有启用中超级管理员角色的账号。
- 排除范围同时检查日志 `causer_id` 绑定的超级管理员账号和登录日志属性中的超级管理员用户名，从而覆盖登录成功以及密码、验证码、IP 白名单等失败记录。
- 新增或修改登录认证流程时，必须测试超级管理员可见全部日志、非超级管理员看不到超级管理员成功及失败日志，并确认普通管理员日志仍可见。
