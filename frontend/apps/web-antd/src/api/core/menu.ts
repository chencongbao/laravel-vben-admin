import type { RouteRecordStringComponent } from '@vben/types';

import { requestClient } from '#/api/request';

/**
 * 获取用户所有菜单
 */
export async function getAllMenusApi() {
  type BackendMenu = {
    children?: BackendMenu[];
    code: string;
    meta: RouteRecordStringComponent['meta'];
    name: string;
    path: string;
    type: string;
    view_key?: null | string;
  };

  const response = await requestClient.get<{ menus: BackendMenu[] }>(
    '/access/menus',
  );
  const components: Record<string, string> = {
    'dashboard.workspace': '/dashboard/workspace/index',
    'system.audit-logs': '/system/audit-logs/index',
    'system.login-logs': '/system/login-logs/index',
    'system.menus': '/system/menus/index',
    'system.permissions': '/system/permissions/index',
    'system.roles': '/system/roles/index',
    'system.settings': '/system/settings/index',
    'system.users': '/system/users/index',
  };

  const mapMenu = (menu: BackendMenu): RouteRecordStringComponent => ({
    children: menu.children?.map(mapMenu),
    component:
      menu.type === 'directory'
        ? 'BasicLayout'
        : menu.view_key
          ? (components[menu.view_key] ?? '/_core/fallback/not-found')
          : '/_core/fallback/not-found',
    meta: menu.meta,
    name: menu.name || menu.code,
    path: menu.path,
  });

  return response.menus.map(mapMenu);
}
