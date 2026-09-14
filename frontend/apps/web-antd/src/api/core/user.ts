import type { UserInfo } from '@vben/types';

import { preferences } from '@vben/preferences';

import { requestClient } from '#/api/request';

interface AuthUserPayload {
  avatar?: null | string;
  id: number;
  name: string;
  roles: Array<{ code: string; name: string }>;
  username: string;
}

/**
 * 获取用户信息
 */
export async function getUserInfoApi() {
  const response = await requestClient.get<{ user: AuthUserPayload }>(
    '/auth/me',
  );

  return {
    avatar: response.user.avatar || preferences.app.defaultAvatar,
    desc: response.user.roles.map(({ name }) => name).join('、'),
    homePath: '/workspace',
    realName: response.user.name,
    roles: response.user.roles.map(({ code }) => code),
    token: '',
    userId: String(response.user.id),
    username: response.user.username,
  } satisfies UserInfo;
}

export async function updateProfileApi(data: {
  avatar?: null | string;
  name: string;
}) {
  return requestClient.request<{ user: AuthUserPayload }>('/auth/profile', {
    data,
    method: 'PATCH',
  });
}

export interface DefaultAvatar {
  id: string;
  url: string;
}

export async function getDefaultAvatarsApi() {
  return requestClient.get<{ avatars: DefaultAvatar[] }>('/auth/avatars');
}

export async function uploadAvatarApi(file: File) {
  const data = new FormData();
  data.append('avatar', file);

  return requestClient.post<{ avatar: string }>('/auth/avatar', data);
}

export async function updatePasswordApi(data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}) {
  return requestClient.put<{ message: string }>('/auth/password', data);
}

export interface AdminSession {
  created_at: string;
  current: boolean;
  id: number;
  ip_address?: null | string;
  last_used_at?: null | string;
  user_agent?: null | string;
}

export async function getSessionsApi() {
  return requestClient.get<{ sessions: AdminSession[] }>('/auth/sessions');
}

export async function revokeSessionApi(id: number) {
  return requestClient.delete(`/auth/sessions/${id}`);
}

export async function revokeOtherSessionsApi() {
  return requestClient.delete<{ revoked_count: number }>('/auth/sessions');
}
