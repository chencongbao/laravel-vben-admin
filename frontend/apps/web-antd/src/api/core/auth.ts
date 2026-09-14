import { requestClient } from '#/api/request';

export namespace AuthApi {
  /** 登录接口参数 */
  export interface LoginParams {
    captcha?: Array<{ i: number; t: number; x: number; y: number }>;
    captcha_key?: string;
    password?: string;
    username?: string;
  }

  export interface CaptchaResult {
    captcha_hint: string;
    captcha_image: string;
    captcha_key: string;
    expires_in: number;
  }

  /** 登录接口返回值 */
  export interface LoginResult {
    challenge_token?: string;
    expires_in?: number;
    qr_code?: string;
    secret?: string;
    setup_required?: boolean;
    token?: string;
    token_type?: 'Bearer';
    two_factor_required?: boolean;
  }
}

export async function getLoginCaptchaApi(username: string) {
  return requestClient.get<AuthApi.CaptchaResult>('/auth/captcha', {
    params: { username },
  });
}

/**
 * 登录
 */
export async function loginApi(data: AuthApi.LoginParams) {
  return requestClient.post<AuthApi.LoginResult>('/auth/login', data);
}

export async function completeTwoFactorChallengeApi(data: {
  challenge_token: string;
  code: string;
}) {
  return requestClient.post<AuthApi.LoginResult>(
    '/auth/two-factor/challenge',
    data,
  );
}

/**
 * 刷新accessToken
 */
export async function logoutApi() {
  return requestClient.post('/auth/logout');
}

/**
 * 获取用户权限码
 */
export async function getAccessCodesApi() {
  const response = await requestClient.get<{ permissions: string[] }>(
    '/access/permissions',
  );
  return response.permissions;
}
