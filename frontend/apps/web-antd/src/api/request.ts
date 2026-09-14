/**
 * 该文件可自行根据业务逻辑进行调整
 */
import type { RequestClientOptions } from '@vben/request';

import { useAppConfig } from '@vben/hooks';
import { preferences } from '@vben/preferences';
import {
  authenticateResponseInterceptor,
  defaultResponseInterceptor,
  errorMessageResponseInterceptor,
  RequestClient,
} from '@vben/request';
import { useAccessStore } from '@vben/stores';

import { message } from 'ant-design-vue';

import { $t } from '#/locales';
import { useAuthStore } from '#/store';

const { apiURL } = useAppConfig(import.meta.env, import.meta.env.PROD);

const localizedErrorKeys: Record<string, string> = {
  ADMIN_PRIVILEGE_ESCALATION_DENIED: 'common.errors.privilegeEscalationDenied',
  CAPTCHA_INVALID: 'authentication.errors.captchaInvalid',
  CURRENT_PASSWORD_INCORRECT: 'profile.password.currentIncorrect',
  INVALID_CREDENTIALS: 'authentication.errors.invalidCredentials',
  LOGIN_IP_NOT_ALLOWED: 'authentication.errors.loginIpNotAllowed',
  TWO_FACTOR_CHALLENGE_INVALID:
    'authentication.errors.twoFactorChallengeInvalid',
  TWO_FACTOR_CODE_INVALID: 'authentication.errors.twoFactorCodeInvalid',
};

const validationErrorKeys: Record<string, string> = {
  current_password: 'profile.password.currentRequired',
  name: 'profile.basic.nameInvalid',
  password: 'profile.password.requirements',
  password_confirmation: 'profile.password.mismatch',
  'settings.system.login_description':
    'system.settingsForm.messages.loginDescriptionInvalid',
  'settings.system.login_remember_me':
    'system.settingsForm.messages.loginRememberMeInvalid',
  'settings.system.name': 'system.settingsForm.messages.systemNameInvalid',
  'settings.system.page_size': 'system.settingsForm.messages.pageSizeInvalid',
};

function createRequestClient(baseURL: string, options?: RequestClientOptions) {
  const client = new RequestClient({
    ...options,
    baseURL,
  });
  let reAuthenticating: null | Promise<void> = null;

  /**
   * 重新认证逻辑
   */
  async function doReAuthenticate() {
    if (reAuthenticating) return reAuthenticating;

    reAuthenticating = (async () => {
      console.warn('Access token or refresh token is invalid or expired. ');
      const authStore = useAuthStore();
      await authStore.logout(true, false);
    })().finally(() => {
      reAuthenticating = null;
    });

    return reAuthenticating;
  }

  /**
   * 刷新token逻辑
   */
  async function doRefreshToken(): Promise<string> {
    throw new Error('Laravel Vben Admin uses revocable tokens without refresh.');
  }

  function formatToken(token: null | string) {
    return token ? `Bearer ${token}` : null;
  }

  // 请求头处理
  client.addRequestInterceptor({
    fulfilled: async (config) => {
      const accessStore = useAccessStore();

      config.headers.Authorization = formatToken(accessStore.accessToken);
      config.headers['Accept-Language'] = preferences.app.locale;
      return config;
    },
  });

  client.addResponseInterceptor(
    defaultResponseInterceptor({
      codeField: 'code',
      dataField: 'data',
      successCode: 0,
    }),
  );

  // 处理返回的响应数据格式
  // token过期的处理
  client.addResponseInterceptor(
    authenticateResponseInterceptor({
      client,
      doReAuthenticate,
      doRefreshToken,
      enableRefreshToken: preferences.app.enableRefreshToken,
      formatToken,
    }),
  );

  // 通用的错误处理,如果没有进入上面的错误处理逻辑，就会进入这里
  client.addResponseInterceptor(
    errorMessageResponseInterceptor((msg: string, error) => {
      // 这里可以根据业务进行定制,你可以拿到 error 内的信息进行定制化处理，根据不同的 code 做不同的提示，而不是直接使用 message.error 提示 msg
      // 当前mock接口返回的错误字段是 error 或者 message
      const responseData = error?.response?.data ?? {};
      const responseCode = responseData?.code as string | undefined;
      const localizedKey = responseCode
        ? localizedErrorKeys[responseCode]
        : undefined;
      const validationErrors = responseData?.errors as
        | Record<string, string[]>
        | undefined;
      const validationFields = validationErrors
        ? Object.keys(validationErrors)
        : [];
      const validationKey = validationFields
        .map((field) => validationErrorKeys[field])
        .find(Boolean)
        ?? (validationFields.some((field) => field.startsWith('settings.'))
          ? 'system.settingsForm.messages.invalid'
          : undefined);
      const messageKey = localizedKey ?? validationKey;
      const errorMessage = responseData?.error ?? responseData?.message ?? '';
      // 如果没有错误信息，则会根据状态码进行提示
      message.error(messageKey ? $t(messageKey) : errorMessage || msg);
    }),
  );

  return client;
}

export const requestClient = createRequestClient(apiURL, {
  responseReturn: 'body',
});

export const baseRequestClient = new RequestClient({ baseURL: apiURL });
