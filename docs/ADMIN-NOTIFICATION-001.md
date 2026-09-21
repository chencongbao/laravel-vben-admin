# ADMIN-NOTIFICATION-001 后台通知中心

## 目标与范围

将 Vben 默认硬编码通知替换为可被不同 Laravel 后台复用的系统通知中心。第一阶段包含数据库通知源、统一发布服务、个人已读/隐藏状态、右上角最近通知、全部通知页面和 60 秒可见页轮询；不包含 WebSocket、短信、邮件、Telegram 转发、通知后台管理或按角色投放。

## 数据与接口

- 新 migration：`admin_notifications` 保存通知正文、翻译键、参数、链接、来源、级别和有效期。
- 新 migration：`admin_notification_states` 以通知和用户唯一约束保存 `read_at` 与 `hidden_at`。
- 所有查询按 `published_at DESC, id DESC`，过期或尚未发布的记录不返回。
- API、字段和动作详见 `docs/api.md` 的 Notifications 章节。

## 权限与默认授权

- 读取和修改个人通知状态仅要求 `auth:sanctum` 与 `admin.user`。
- 不新增菜单、权限、角色关联或默认数据库授权；通知中心使用隐藏核心路由 `/notifications`。
- 包不开放发布 HTTP API。宿主业务必须先通过自己的权限与数据范围校验，再调用 `AdminNotificationPublisher`。

## 多语言、日志与安全

- 存储翻译键与参数，纯文本标题和内容作为回退；宿主可增加任意 locale。
- 上游业务写操作继续写审计日志；个人已读/隐藏/清空不制造审计噪音。
- Vue 只做文本插值，不使用 `v-html`；链接仅允许内部路径或 HTTPS。
- 参数和 metadata 禁止敏感键；通知不得承担密钥、凭据或完整请求体传递。
- 每个用户状态独立，直接访问已隐藏或不存在的通知返回 404。

## 部署与验收

1. 更新包后执行 `php artisan migrate --force`。
2. 构建并发布管理前端资源。
3. 验证两个账号能看到同一系统通知，但已读和隐藏互不影响。
4. 验证过期通知不可见，未登录 API 返回 401，危险链接和敏感 metadata 被拒绝。
5. 验证中英文以及宿主新增语言能优先使用翻译键，缺少翻译时显示纯文本回退。
