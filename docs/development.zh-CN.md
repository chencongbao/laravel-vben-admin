# Laravel Vben Admin 二次开发指南

本文面向安装 `chencongbao/laravel-vben-admin` 后继续开发后台功能的开发者与 AI Agent。目标是让新增功能遵循现有 Laravel/Vben 架构、权限、安全、审计和交付规则，而不是只做到“页面能打开”。

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

注册后先预览，再同步：

```bash
php artisan vben-admin:sync --dry-run
php artisan vben-admin:sync
```

同步是幂等的，不应删除自定义记录，也不应重置角色分配。

### 4.3 增加前端页面映射

后端菜单返回 `view_key` 后，必须在：

```text
frontend/apps/web-antd/src/api/core/menu.ts
```

把该键显式映射到本地页面组件。未知键保持进入404/兜底页，禁止将数据库值直接传给动态 `import()`。

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

记录器自动补充操作者、IP、HTTP 方法、请求路径、User-Agent 和经过脱敏的请求参数。包含 `password`、`token`、`secret`、`credential`、`authorization`、`cookie`、`private_key`、`captcha`、`totp`、`two_factor` 的键会递归替换为 `[REDACTED]`；上传内容只记录文件元数据。新增敏感字段时必须同步扩展脱敏规则和测试。

登录流程由包内部的 `LoginRecorder` 统一记录成功、失败原因和 `client_type`。宿主业务不要调用它伪造登录事件。日志写入应与关键业务写入处于合理的事务边界；审计失败是否阻断业务必须在需求中明确，安全和权限类变更默认要求同时成功。

查询页面继续使用 `/system/login-logs` 与 `/system/audit-logs`，不要直接把 Spatie 模型结构暴露为新的前端契约。数据库中的 `created_at` 按 UTC 保存，API 返回 ISO-8601，前端统一转换为北京时间。

## 8. 认证和会话不可破坏规则

- Token撤销或过期后，前端收到401必须清理本地认证并跳转登录页，不能再次用失效Token调用退出接口。
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

系统设置需要明确：键名、类型、默认值、验证规则、公开启动配置是否可见、管理权限和刷新后生效方式。密钥和凭据不得进入通用设置接口。

## 10. Vben前端开发规则

- 页面优先放在 `frontend/apps/web-antd/src/views`，API封装放在 `src/api`。
- 复用Vben和Ant Design Vue现有组件、主题变量和布局，不写只适配一张截图的固定定位。
- 后台一级页面必须通过 `Page` 的 `title` 保留内容区顶部当前页面标题导航；页面内部布局、列表工具栏或配置Tabs不能替代或移除这一层导航。
- 文案同时维护 `zh-CN` 和 `en-US`；错误优先按稳定API错误码翻译。语言文件按功能域拆分：跨模块复用文案进入 `common.json`，认证、个人中心、系统管理和配置分别使用 `auth.json`、`profile.json`、`system.json`、`configuration.json`；新业务使用独立同名文件。刷新、筛选、查询、重置、自动刷新、导出等跨列表操作统一复用 `common.actions`，编号、创建时间等跨列表字段复用 `common.fields`，时间单位复用 `common.time`。中文编号显示“编号”，英文保持“ID”。禁止重新创建承载全部页面文案的单体 `page.json`，各语言的文件名和键结构必须一致。
- 数据库日期时间按UTC保存，API返回带时区的标准ISO-8601值；后台所有面向用户的时间统一使用 `src/utils/datetime.ts` 转换为 `Asia/Shanghai`（北京时间，UTC+8）并显示为 `YYYY-MM-DD HH:mm:ss`。禁止使用浏览器本地时区、服务器系统时区或在页面内重复实现格式化函数。
- 表格必须考虑加载、空数据、分页、窄屏横向滚动和操作权限。
- 列表页统一使用 `src/components/system/list-toolbar.vue` 组织顶部操作：刷新、筛选、重置、列显示和批量操作放左侧；自动刷新、导出、新增、历史记录等功能放右侧。操作栏位于表格上方，直接排列按钮，不使用Card、背景、边框或额外内边距；Table所在容器使用 `admin-table-card`，内容内边距为0并统一直角，只保留Table自身单层外框和内部横竖分隔线，避免边框叠加变粗。不得把所有按钮无层级地堆在同一侧，窄屏时必须允许自动换行。
- 列表手动刷新统一使用 `src/components/system/list-refresh-button.vue`。它与顶栏刷新按钮共同调用Vben `useRefresh()`，只重新渲染当前路由页面，不刷新整个浏览器。`auto-refresh.vue` 在倒计时结束后也调用相同的 `useRefresh()`；三处入口必须保持完全相同的局部页面刷新语义，仅触发时机不同。页面不得自行实现其他刷新逻辑。
- 顶部操作统一使用 `src/components/system/permission-button.vue`：按钮必须传入Iconify图标，使用直角样式；需要授权的业务按钮通过 `permission` 传入后台权限编码，无权限时组件自动隐藏。`permission` 支持单个编码或编码数组（数组为满足任一权限即可显示），不需要权限的刷新、筛选、重置可以不传。
- 后台公共视觉规则统一维护在 `src/styles/admin-ui.css`（按钮、复选框、列表工具栏、搜索Card、搜索字段、行操作和批量操作）与 `src/styles/admin-table.css`（Table）中；公共组件和业务页面只输出结构及约定类名，不得在单个Vue文件内重复定义或覆盖这些样式。所有Ant Design按钮和复选框统一使用直角样式，圆形状态指示、头像、开关等非按钮元素不受此规则影响。
- 数据列表统一添加 `admin-data-table` 类并启用Ant Design Vue的 `bordered`，使用有区分度的浅色表头、横向行线和纵向列线；悬停行需要完整高亮，固定在右侧的操作列背景必须与当前行一致。
- 默认数据表使用中等紧凑密度：表头单元格内边距为 `10px 12px`，数据单元格为 `9px 12px`，普通行视觉高度约44px；除触控专用页面或复杂多行内容外，不得自行放大行高。
- 行操作只有一至两项时可以直接展示；三项及以上时保留一个高频主操作，其余收入“更多”菜单。菜单按业务操作、辅助操作、危险操作分组，危险操作置底并二次确认；复杂流程进入详情抽屉，不继续扩大操作列。
- Table行操作使用 `PermissionButton` 的 `icon-only` 模式，点击区域固定为 `32px × 32px`，并必须提供 `tooltip`（该值同时作为 `aria-label`）。桌面端最多直显三个高频图标和一个“更多”图标；移动端只保留“更多”。删除、强制下线等危险操作使用危险色并二次确认，不得只用颜色表达含义。
- 普通操作按钮统一使用当前主题色的边框、文字和图标，主按钮使用主题色背景；危险操作统一使用红色边框、文字和图标，不随普通按钮颜色覆盖。
- 每个可进入的后台菜单页面必须在标题下方提供简短描述，说明该页面的用途和主要管理范围；中英文描述统一维护在页面语言包中。
- 后台卡片、输入框、页签和预览控件的圆角必须使用全局 `--radius` 主题变量，不得写死像素值；头像、状态圆点等必须保持圆形的业务元素除外。
- 后台保存的明暗模式是新浏览器的全局默认值；用户通过顶栏明暗按钮选择的 `light`、`dark` 或 `auto` 是当前浏览器个人偏好，必须本地持久化并在刷新后优先恢复。顶栏切换不得要求系统设置权限，也不得直接修改全局后台配置。
- 所有列表页必须提供“筛选”入口，筛选区默认至少包含使用 `common.fields.id` 的“编号”精确搜索；业务页面按需追加名称、状态、时间等字段，不得移除默认编号条件。`resource-page.vue` 与 `log-page.vue` 会自动补齐编号字段，独立列表页必须显式接入。列表搜索条件使用 `list-search-panel.vue` 独立直角Card展示，具体字段使用 `list-search-field.vue` 输出“左侧固定标签 + 右侧输入控件”的直角连体样式。右侧占位提示直接使用字段名称，不重复添加“请输入”或“请选择”。搜索区默认显示并只占一行，字段按实际内容宽度自适应排列，查询和重置紧跟最后一个可见搜索字段，不得被推到Card最右侧。条件在当前宽度下超过一行时，组件自动显示“展开/收起”，工具栏左侧“筛选”按钮仍可整体显示或隐藏搜索Card。不要把多个输入框直接塞进工具栏，也不要让搜索条件改变表格宽度。
- 需要定时更新的页面使用 `auto-refresh.vue`。组件按页面 `storageKey` 保存开关和间隔，页面不可见或请求尚未结束时暂停倒计时；触发时只调用列表加载方法，不刷新整个浏览器页面。
- 小数据和当前页导出使用 `table-export-button.vue`，导出按钮放工具栏右侧并传入查看或导出权限。组件会添加 UTF-8 BOM、转义 CSV 内容并防止公式注入。大数据导出不得把全部记录一次性拉到浏览器，应参考异步任务模式：保存当前筛选条件、队列分块生成文件、限制同一管理员并发任务、提供进度和历史下载，并增加独立服务端导出权限。
- 统一行操作可使用 `table-row-actions.vue`。调用方按显示优先级传入动作：前三个在桌面端显示为图标，其余进入“更多”；移动端全部进入“更多”。组件负责权限过滤和布局，危险操作的确认弹窗及服务端权限仍由业务页面负责。
- 可选批量操作使用 `table-batch-actions.vue`，仅在业务页面明确引入并传入操作项时显示，不默认添加到所有列表。按钮统一显示“批量图标 + 文字 + 向下箭头”，选中数量直接放在按钮文字中，展开时箭头向上；下拉菜单使用直角、统一宽度和“操作图标 + 文字”结构，危险操作默认红字并仅在悬停时显示浅红背景。下拉菜单不提供额外的“清空选择”，用户通过表格复选框取消选择，批量操作成功后页面自动清空选择。页面负责Table行选择状态和批量接口，组件负责选中数量、权限过滤、禁用状态、下拉菜单及危险操作样式；批量写操作必须使用服务端专用接口、权限校验、事务和操作日志，不得在前端循环调用单条写接口。
- 登录日志允许通过 `system.login-log.delete` 批量删除，每次限制1至100条。服务端必须验证所有编号均属于当前后台日志名下的登录日志，否则整批拒绝；删除行为写入 `system.login-log.batch-deleted` 操作日志。操作日志保持只读，登录日志删除后不可恢复。
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
VITE_BASE=/admin/ corepack pnpm \
  --filter=@chencongbao/laravel-vben-admin-web run build

cd /path/to/laravel-host
php artisan vben-admin:publish-assets --force
```

如果配置了其他 `VBEN_ADMIN_PATH`，`VITE_BASE` 必须使用相同路径并带首尾 `/`。

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
