import type { SupportedLanguagesType } from '@vben/locales';
import type {
  AuthPageLayoutType,
  BuiltinThemeType,
  LayoutType,
  TabsStyleType,
  ThemeModeType,
} from '@vben/types';

import { initPreferences } from '@vben/preferences';
import { unmountGlobalLoading } from '@vben/utils';

import {
  type AdminTabbarConfig,
  type AdvancedPreferencesConfig,
  createOverridesPreferences,
} from './preferences';
import { setAdminDefaultPageSize } from './utils/pagination';

interface LaravelApplicationConfig {
  locale: SupportedLanguagesType;
  name: string;
  pageSize: number;
  timezone: string;
  loginLayout: AuthPageLayoutType;
  loginTheme: BuiltinThemeType;
  adminTheme: BuiltinThemeType;
  adminThemeMode: ThemeModeType;
  adminLayout: LayoutType;
  tabbar: AdminTabbarConfig;
  advancedPreferences: AdvancedPreferencesConfig;
}

const fallbackApplicationConfig: LaravelApplicationConfig = {
  locale: 'zh-CN',
  name: import.meta.env.VITE_APP_TITLE,
  pageSize: 20,
  timezone: 'Asia/Shanghai',
  loginLayout: 'panel-right',
  loginTheme: 'default',
  adminTheme: 'default',
  adminThemeMode: 'light',
  adminLayout: 'sidebar-nav',
  tabbar: {
    draggable: true,
    enable: true,
    maxCount: 0,
    middleClickToClose: false,
    persist: true,
    showIcon: true,
    showMaximize: true,
    showMore: true,
    styleType: 'chrome',
    visitHistory: true,
    wheelable: true,
  },
  advancedPreferences: {},
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
      page_size?: number;
      timezone?: string;
      login_layout?: string;
      login_theme?: string;
      admin_theme?: string;
      admin_theme_mode?: string;
      admin_layout?: string;
      tabbar?: {
        draggable?: boolean;
        enable?: boolean;
        max_count?: number;
        middle_click_to_close?: boolean;
        persist?: boolean;
        show_icon?: boolean;
        show_maximize?: boolean;
        show_more?: boolean;
        style_type?: string;
        visit_history?: boolean;
        wheelable?: boolean;
      };
      advanced_preferences?: AdvancedPreferencesConfig;
    };
    return {
      locale: data.locale === 'en-US' ? 'en-US' : 'zh-CN',
      name: data.name || fallbackApplicationConfig.name,
      pageSize:
        typeof data.page_size === 'number' &&
        data.page_size >= 10 &&
        data.page_size <= 100
          ? data.page_size
          : fallbackApplicationConfig.pageSize,
      timezone: data.timezone || 'Asia/Shanghai',
      loginLayout: ['panel-left', 'panel-center', 'panel-right'].includes(
        data.login_layout || '',
      )
        ? (data.login_layout as AuthPageLayoutType)
        : 'panel-right',
      loginTheme: [
        'default',
        'violet',
        'pink',
        'yellow',
        'sky-blue',
        'green',
        'zinc',
        'deep-green',
        'deep-blue',
        'orange',
        'rose',
        'neutral',
        'slate',
        'gray',
      ].includes(data.login_theme || '')
        ? (data.login_theme as BuiltinThemeType)
        : 'default',
      adminTheme: [
        'default',
        'violet',
        'pink',
        'yellow',
        'sky-blue',
        'green',
        'zinc',
        'deep-green',
        'deep-blue',
        'orange',
        'rose',
        'neutral',
        'slate',
        'gray',
      ].includes(data.admin_theme || '')
        ? (data.admin_theme as BuiltinThemeType)
        : 'default',
      adminThemeMode: ['light', 'dark', 'auto'].includes(
        data.admin_theme_mode || '',
      )
        ? (data.admin_theme_mode as ThemeModeType)
        : 'light',
      adminLayout: [
        'sidebar-nav',
        'sidebar-mixed-nav',
        'header-nav',
        'header-sidebar-nav',
        'mixed-nav',
        'header-mixed-nav',
        'full-content',
      ].includes(data.admin_layout || '')
        ? (data.admin_layout as LayoutType)
        : 'sidebar-nav',
      tabbar: {
        draggable:
          data.tabbar?.draggable ?? fallbackApplicationConfig.tabbar.draggable,
        enable: data.tabbar?.enable ?? fallbackApplicationConfig.tabbar.enable,
        maxCount:
          typeof data.tabbar?.max_count === 'number' &&
          data.tabbar.max_count >= 0 &&
          data.tabbar.max_count <= 30
            ? data.tabbar.max_count
            : fallbackApplicationConfig.tabbar.maxCount,
        middleClickToClose:
          data.tabbar?.middle_click_to_close ??
          fallbackApplicationConfig.tabbar.middleClickToClose,
        persist:
          data.tabbar?.persist ?? fallbackApplicationConfig.tabbar.persist,
        showIcon:
          data.tabbar?.show_icon ?? fallbackApplicationConfig.tabbar.showIcon,
        showMaximize:
          data.tabbar?.show_maximize ??
          fallbackApplicationConfig.tabbar.showMaximize,
        showMore:
          data.tabbar?.show_more ?? fallbackApplicationConfig.tabbar.showMore,
        styleType: ['brisk', 'card', 'chrome', 'plain'].includes(
          data.tabbar?.style_type || '',
        )
          ? (data.tabbar?.style_type as TabsStyleType)
          : fallbackApplicationConfig.tabbar.styleType,
        visitHistory:
          data.tabbar?.visit_history ??
          fallbackApplicationConfig.tabbar.visitHistory,
        wheelable:
          data.tabbar?.wheelable ?? fallbackApplicationConfig.tabbar.wheelable,
      },
      advancedPreferences:
        data.advanced_preferences ?? fallbackApplicationConfig.advancedPreferences,
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
  const {
    adminTheme,
    adminThemeMode,
    adminLayout,
    locale,
    loginLayout,
    loginTheme,
    name,
    pageSize,
    timezone,
    tabbar,
    advancedPreferences,
  } = await resolveLaravelApplicationConfig();

  setAdminDefaultPageSize(pageSize);

  // app偏好设置初始化
  await initPreferences({
    namespace,
    overrides: createOverridesPreferences(
      locale,
      name,
      timezone,
      loginTheme,
      loginLayout,
      adminTheme,
      adminThemeMode,
      adminLayout,
      tabbar,
      advancedPreferences,
    ),
  });

  // 启动应用并挂载
  // vue应用主要逻辑及视图
  const { bootstrap } = await import('./bootstrap');
  await bootstrap(namespace);

  // 移除并销毁loading
  unmountGlobalLoading();
}

initApplication();
