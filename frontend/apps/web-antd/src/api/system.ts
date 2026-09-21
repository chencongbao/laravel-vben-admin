import { requestClient } from './request';

export interface PageResult<T = Record<string, any>> {
  current_page: number;
  data: T[];
  per_page: number;
  total: number;
}

export function getResource(path: string, params?: Record<string, any>) {
  return requestClient.get<PageResult>(path, { params });
}

export function getCollection<T = Record<string, any>>(path: string) {
  return requestClient.get<T>(path);
}

export function createResource(path: string, data: Record<string, any>) {
  return requestClient.post(path, data);
}

export function updateResource(
  path: string,
  id: number,
  data: Record<string, any>,
) {
  return requestClient.request(`${path}/${id}`, { data, method: 'PATCH' });
}

export function deleteResource(path: string, id: number) {
  return requestClient.delete(`${path}/${id}`);
}

export function getResourceDetail<T = Record<string, any>>(
  path: string,
  id: number,
) {
  return requestClient.get<T>(`${path}/${id}`);
}

export function updateRoleAccess(
  id: number,
  data: { menu_ids: number[]; permission_ids: number[] },
) {
  return requestClient.put(`/system/roles/${id}/access`, data);
}

export function reorderMenus(
  items: Array<{ id: number; parent_id: null | number; sort: number }>,
) {
  return requestClient.put('/system/menus/reorder', { items });
}

export function reorderPermissions(
  items: Array<{ id: number; parent_id: null | number; sort: number }>,
) {
  return requestClient.put('/system/permissions/reorder', { items });
}

export function updateSettings(settings: Array<{ key: string; value: any }>) {
  return requestClient.put<{ settings: SettingItem[] }>('/system/settings', {
    settings,
  });
}

export function uploadSystemLogo(file: File) {
  return requestClient.upload<{ logo: string }>('/system/settings/logo', {
    logo: file,
  });
}

export function resetSystemLogo() {
  return requestClient.delete<{ logo: null }>('/system/settings/logo');
}

export function updateThemeSettings(
  settings: Array<{ key: string; value: any }>,
) {
  return requestClient.put<{ settings: SettingItem[] }>(
    '/system/theme-settings',
    { settings },
  );
}

export interface SettingItem {
  key: string;
  type:
    | 'asset'
    | 'boolean'
    | 'enum'
    | 'integer'
    | 'json'
    | 'string'
    | 'timezone';
  value: any;
}
