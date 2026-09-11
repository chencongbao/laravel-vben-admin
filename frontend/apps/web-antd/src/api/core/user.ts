import type { UserInfo } from '@vben/types';

import { requestClient } from '#/api/request';

/**
 * 获取用户信息
 */
export async function getUserInfoApi() {
  const response = await requestClient.get<{
    user: { avatar?: null | string; id: number; name: string; username: string };
  }>('/auth/me');

  return {
    avatar: response.user.avatar ?? '',
    desc: '',
    homePath: '/system/users',
    realName: response.user.name,
    roles: [],
    token: '',
    userId: String(response.user.id),
    username: response.user.username,
  } satisfies UserInfo;
}

export async function updateProfileApi(data: { avatar?: null | string; name: string }) {
  return requestClient.patch<{ user: { avatar?: null | string; id: number; name: string; username: string } }>('/auth/profile', data);
}

export async function updatePasswordApi(data: {
  current_password: string;
  password: string;
  password_confirmation: string;
}) {
  return requestClient.put<{ message: string }>('/auth/password', data);
}
