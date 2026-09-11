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
