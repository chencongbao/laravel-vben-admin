# 多语言目录

语言文件按功能域拆分，文件名就是翻译键的一级命名空间。不要把所有页面文案重新堆进单个 `page.json`。

- `common.json`：跨功能复用的按钮、状态、选择提示等公共文案。
- `auth.json`：登录、身份认证和角色显示名称。
- `profile.json`：个人中心、头像、密码、会话和 Google 2FA。
- `dashboard.json`：工作台和统计概览。
- `configuration.json`：主题、布局、标签栏与系统界面配置。
- `system.json`：用户、角色、权限、菜单、系统设置和日志。
- `demos.json`：演示页面，生产业务不得依赖。

新增独立业务模块时，应新增与模块名一致的语言文件，例如 `matches.json`、`orders.json`，并使用 `matches.list.title`、`orders.actions.export` 等键。只有被多个功能共同复用的文案才放入 `common.json`。

`index.ts` 会通过 `import.meta.glob('./langs/**/*.json')` 自动加载文件，无需逐个注册。每种语言必须保持相同文件名和键结构。
