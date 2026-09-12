import type { SupportedLanguagesType } from '@vben/locales';

import { initPreferences } from '@vben/preferences';
import { unmountGlobalLoading } from '@vben/utils';

import {
  createOverridesPreferences,
  preferencesExtension,
} from './preferences';

async function resolveLaravelLocale(): Promise<SupportedLanguagesType> {
  try {
    const response = await fetch('/api/admin/application', {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      return 'zh-CN';
    }

    const data = (await response.json()) as { locale?: string };
    return data.locale === 'en-US' ? 'en-US' : 'zh-CN';
  } catch {
    return 'zh-CN';
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
  const locale = await resolveLaravelLocale();

  // app偏好设置初始化
  await initPreferences({
    extension: preferencesExtension,
    namespace,
    overrides: createOverridesPreferences(locale),
  });

  // 启动应用并挂载
  // vue应用主要逻辑及视图
  const { bootstrap } = await import('./bootstrap');
  await bootstrap(namespace);

  // 移除并销毁loading
  unmountGlobalLoading();
}

initApplication();
