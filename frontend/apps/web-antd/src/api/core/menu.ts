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
  const componentFor = (viewKey: string) =>
    /^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/.test(viewKey)
      ? `/${viewKey.replaceAll('.', '/')}/index`
      : '/_core/fallback/not-found';

  const mapMenu = (menu: BackendMenu): RouteRecordStringComponent => ({
    children: menu.children?.map(mapMenu),
    component:
      menu.type === 'directory'
        ? 'BasicLayout'
        : menu.view_key
          ? componentFor(menu.view_key)
          : '/_core/fallback/not-found',
    meta: menu.meta,
    name: menu.name || menu.code,
    path: menu.path,
  });

  return response.menus.map(mapMenu);
}
