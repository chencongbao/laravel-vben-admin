# Laravel Vben Admin Starter

This directory is an editable application based on the official Vben Admin `v5.7.0` tag (upstream commit `63a38dce49ba109f61607994e21ba921d8e970e9`). The upstream MIT license is retained in `LICENSE`.

Package-specific changes:

- only the Ant Design application and its required workspace packages are retained;
- Laravel's raw JSON response shape is used instead of the Vben mock envelope;
- Sanctum Bearer-token login, logout and current-user APIs are connected;
- backend dynamic menus and permission codes are enabled;
- administrator, role, permission, menu, setting and log pages call the package APIs;
- mock services are disabled.

Requirements are inherited from Vben 5.7.0: Node.js `^22.18.0 || ^24.0.0`, pnpm `>=10`.
