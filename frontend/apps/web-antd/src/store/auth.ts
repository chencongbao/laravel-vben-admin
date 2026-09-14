import type { Recordable, UserInfo } from '@vben/types';

import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { LOGIN_PATH } from '@vben/constants';
import { preferences } from '@vben/preferences';
import { resetAllStores, useAccessStore, useUserStore } from '@vben/stores';

import { notification } from 'ant-design-vue';
import { defineStore } from 'pinia';

import {
  completeTwoFactorChallengeApi,
  getAccessCodesApi,
  getUserInfoApi,
  loginApi,
  logoutApi,
} from '#/api';
import { $t } from '#/locales';

export const useAuthStore = defineStore('auth', () => {
  const accessStore = useAccessStore();
  const userStore = useUserStore();
  const router = useRouter();

  const loginLoading = ref(false);

  /**
   * 异步处理登录操作
   * Asynchronously handle the login process
   * @param params 登录表单数据
   */
  async function authLogin(
    params: Recordable<any>,
    onSuccess?: () => Promise<void> | void,
  ) {
    // 异步处理用户登录操作并获取 accessToken
    let userInfo: null | UserInfo = null;
    try {
      loginLoading.value = true;
      const result = await loginApi(params);

      if (result.two_factor_required) {
        return { twoFactor: result, userInfo: null };
      }

      userInfo = await finishLogin(result.token, onSuccess);

    } finally {
      loginLoading.value = false;
    }

    return { twoFactor: null, userInfo };
  }

  async function completeTwoFactorLogin(
    params: { challenge_token: string; code: string },
    onSuccess?: () => Promise<void> | void,
  ) {
    try {
      loginLoading.value = true;
      const result = await completeTwoFactorChallengeApi(params);
      const userInfo = await finishLogin(result.token, onSuccess);

      return { userInfo };
    } finally {
      loginLoading.value = false;
    }
  }

  async function finishLogin(
    accessToken?: string,
    onSuccess?: () => Promise<void> | void,
  ) {
    let userInfo: null | UserInfo = null;

      // 如果成功获取到 accessToken
      if (accessToken) {
        accessStore.setAccessToken(accessToken);

        // 获取用户信息并存储到 accessStore 中
        const [fetchUserInfoResult, accessCodes] = await Promise.all([
          fetchUserInfo(),
          getAccessCodesApi(),
        ]);

        userInfo = fetchUserInfoResult;

        userStore.setUserInfo(userInfo);
        accessStore.setAccessCodes(accessCodes);

        if (accessStore.loginExpired) {
          accessStore.setLoginExpired(false);
        } else {
          onSuccess
            ? await onSuccess?.()
            : await router.push(
                userInfo.homePath || preferences.app.defaultHomePath,
              );
        }

        const welcomeName = userInfo?.realName || userInfo?.username;
        if (welcomeName) {
          notification.success({
            description: `${$t('authentication.loginSuccessDesc')}：${welcomeName}`,
            duration: 3,
            message: $t('authentication.loginSuccess'),
          });
        }
      }

    return userInfo;
  }

  async function logout(
    redirect: boolean = true,
    requestServer: boolean = true,
  ) {
    if (requestServer) {
      try {
        await logoutApi();
      } catch {
        // Token 已失效时仍继续清理本地登录状态。
      }
    }
    resetAllStores();
    accessStore.setLoginExpired(false);

    // 回登录页带上当前路由地址
    await router.replace({
      path: LOGIN_PATH,
      query: redirect
        ? {
            redirect: encodeURIComponent(router.currentRoute.value.fullPath),
          }
        : {},
    });
  }

  async function fetchUserInfo() {
    const userInfo = await getUserInfoApi();
    userStore.setUserInfo(userInfo);
    return userInfo;
  }

  function $reset() {
    loginLoading.value = false;
  }

  return {
    $reset,
    authLogin,
    completeTwoFactorLogin,
    fetchUserInfo,
    loginLoading,
    logout,
  };
});
