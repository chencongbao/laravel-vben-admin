# Laravel Vben Admin 开发约束

本文件适用于本仓库以及通过 Path Repository 直接开发本包的 Codex/AI Agent。

## 开始开发前必须阅读

1. `docs/development.zh-CN.md`
2. `docs/architecture.md`
3. `docs/api.md`
4. 涉及安装、升级或发布时再阅读 `docs/installation.zh-CN.md`

不得只根据页面截图猜测接口、字段、权限或数据库结构。先检查当前实现和迁移，再确定最小修改范围。

## 不可破坏的架构规则

- 包只负责通用后台能力；赛事、直播源、媒体和其他业务领域代码属于宿主项目。
- 后台 API 前缀固定为 `/api/admin`；浏览器访问路径才由 `VBEN_ADMIN_PATH` 控制。
- Laravel 服务端是权限边界。前端隐藏按钮不能代替 `admin.permission:*` 或 `admin.super-admin` 校验。
- 权限使用稳定业务代码标识；数据库菜单的 `view_key` 必须由前端本地白名单映射，禁止按数据库字符串任意动态导入组件。
- 数据库变更只能新增 migration，不直接修改目标数据库结构，不改写已经发布的历史 migration。
- 写操作需要验证、事务（涉及多表时）、权限和审计；审计中不得记录密码、Token、密钥或完整凭据。
- API 错误提供稳定 `code`；前端根据当前语言翻译，不直接依赖英文 `message`。
- `APP_ENV=local` 仅按既有认证规则跳过管理员 IP 白名单和 Google 2FA；不得把该绕过扩大到非本地环境。
- 不修改 `vendor`、`node_modules` 或宿主项目的无关代码，不覆盖用户已有改动。

## 完成标准

- API变化同步 `docs/api.md`；安装或部署变化同步 `docs/installation.zh-CN.md`；架构边界变化同步 `docs/architecture.md`。
- PHP至少执行语法检查和 Pint；前端至少执行 Vue TypeScript检查。
- 有行为逻辑时增加对应单元或功能测试；构建前端后使用 `vben-admin:publish-assets --force` 发布并验证实际页面。
- 最终报告修改文件、数据库/API/配置变化、部署步骤、测试结果及未完成的运行时验证，不得把静态检查表述为真实环境验证。

