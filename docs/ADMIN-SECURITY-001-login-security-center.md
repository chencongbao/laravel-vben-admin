# ADMIN-SECURITY-001 登录安全与安全中心

## 目标

为所有安装本包的后台提供可验证的阶梯登录风控、受保护的 2FA 挑战、限时访问令牌以及超级管理员使用的安全事件与 IP 封禁管理页面。

## 范围

- 按 IP、用户名以及 IP+用户名组合累计登录失败风险。
- 第 5、10、20 次失败分别锁定组合 10 分钟、30 分钟、2 小时。
- 同一 IP 在 24 小时内尝试至少 10 个不同用户名时自动封禁 2 小时。
- 2FA 挑战绑定 IP 与 User-Agent，最多失败 5 次，成功后原子消费。
- 超级管理员在非 `local` 环境强制启用并完成 Google 2FA。
- Sanctum Token 默认 12 小时过期；账号安全信息变化时撤销已有 Token。
- 安全中心查看风险事件，人工封禁/解除 IP，处理风险事件。
- 发布 `AdminSecurityRiskDetected` 事件，宿主项目可监听并接入邮件、Telegram 等告警通道。

## 不包含

- 短信或邮件验证码、恢复码、可信设备、地理位置识别。
- 包内直接绑定具体邮件、Telegram 或第三方告警供应商。
- 普通业务用户登录安全。

## 页面

`/system/security` 包含“风险事件”和“IP 封禁”两个标签页，列表均按 `id DESC`。页面与处理按钮分别使用 `system.security.view`、`system.security.update`。

## API

- `GET /api/admin/system/security/events`
- `POST /api/admin/system/security/events/{id}/resolve`
- `GET /api/admin/system/security/ip-blocks`
- `POST /api/admin/system/security/ip-blocks`
- `POST /api/admin/system/security/ip-blocks/{id}/release`

全部接口要求后台认证和对应权限；写接口记录稳定操作日志。

## 数据库

- `admin_security_events`：风险代码、级别、账号、用户名、IP、UA、已脱敏上下文和处理状态。
- `admin_login_ip_blocks`：IP、原因、自动/人工来源、到期与解除信息。
- 新 migration 可回滚；删除顺序先 IP 封禁表，再风险事件表。

## 权限与默认授权

- `system.security.view`：查看页面和列表。
- `system.security.update`：处理风险、封禁和解除 IP。
- 页面菜单关联 `system.security.view`。
- 超级管理员隐式拥有全部权限；内置 `manager` 默认获得安全中心菜单及上述查看、处理权限。

## 安全规则

- 登录失败始终返回稳定错误码，不返回账号是否存在。
- 缓存键只保存用户名/IP 的 SHA-256 摘要；安全事件不保存密码、验证码、Token 或 2FA 密钥。
- 人工封禁只接受完整 IPv4/IPv6，不接受 CIDR；同一 IP 不能存在两个有效封禁。
- IP 白名单与风险封禁独立执行，命中风险封禁时不会进入密码验证。
- 正式部署必须正确配置可信代理，保证 `Request::ip()` 不接受伪造的转发头。

## 验收标准

1. 连续 5 次失败后返回 HTTP 429 与 `LOGIN_TEMPORARILY_LOCKED`，并产生风险事件。
2. 未认证访问安全中心返回 401，无权限返回 403。
3. 超级管理员可以封禁和解除 IP，重复有效封禁返回 422。
4. 安全写操作生成可查询操作日志。
5. 非本地环境超级管理员首次登录必须完成 2FA；挑战从不同 IP/UA 提交时失败。
6. 新 Token 带 `expires_at`，用户状态、密码、角色、2FA 或登录白名单变化后旧 Token 被撤销。
7. 中英文页面和错误提示均显示翻译内容，不显示原始键。
