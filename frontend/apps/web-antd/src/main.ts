import type { SupportedLanguagesType } from '@vben/locales';

import { initPreferences } from '@vben/preferences';
import { unmountGlobalLoading } from '@vben/utils';

import {
  createOverridesPreferences,
  preferencesExtension,
} from './preferences';

interface LaravelApplicationConfig {
  locale: SupportedLanguagesType;
  name: string;
  timezone: string;
}

const fallbackApplicationConfig: LaravelApplicationConfig = {
  locale: 'zh-CN',
  name: import.meta.env.VITE_APP_TITLE,
  timezone: 'UTC',
};

async function resolveLaravelApplicationConfig(): Promise<LaravelApplicationConfig> {
  try {
    const response = await fetch('/api/admin/application', {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      return fallbackApplicationConfig;
    }

    const data = (await response.json()) as {
      locale?: string;
      name?: string;
      timezone?: string;
    };
    return {
      locale: data.locale === 'en-US' ? 'en-US' : 'zh-CN',
      name: data.name || fallbackApplicationConfig.name,
      timezone: data.timezone || 'UTC',
    };
  } catch {
    return fallbackApplicationConfig;
  }
}

/**
 * 应用初始化完成之后再进行页面加载渲染
 */
async function initApplication() {
  // name用于指定项目唯一标识
  // 用于区分不同项目的偏好设置以及存储数据的key前缀以及其他一些需要隔离的数据
  const env = import.meta.env.PROD ? 'prod' : 'dev';
  const appVersion = import.meta.env.VITE_APP_VERSION;
  const namespace = `${import.meta.env.VITE_APP_NAMESPACE}-${appVersion}-${env}`;
  const { locale, name, timezone } = await resolveLaravelApplicationConfig();

  // app偏好设置初始化
  await initPreferences({
    extension: preferencesExtension,
    namespace,
    overrides: createOverridesPreferences(locale, name, timezone),
  });

  // 启动应用并挂载
  // vue应用主要逻辑及视图
  const { bootstrap } = await import('./bootstrap');
  await bootstrap(namespace);

  // 移除并销毁loading
  unmountGlobalLoading();
}

initApplication();
