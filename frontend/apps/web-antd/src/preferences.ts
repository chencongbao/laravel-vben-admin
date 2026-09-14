import type { SupportedLanguagesType } from '@vben/locales';
import type {
  AuthPageLayoutType,
  BuiltinThemeType,
  LayoutType,
  TabsStyleType,
  ThemeModeType,
} from '@vben/types';

import { defineOverridesPreferences } from '@vben/preferences';

import defaultAvatar from './assets/default-avatar.svg?url';

export interface AdminTabbarConfig {
  draggable: boolean;
  enable: boolean;
  maxCount: number;
  middleClickToClose: boolean;
  persist: boolean;
  showIcon: boolean;
  showMaximize: boolean;
  showMore: boolean;
  styleType: TabsStyleType;
  visitHistory: boolean;
  wheelable: boolean;
}

export type AdvancedPreferencesConfig = Record<
  string,
  Record<string, boolean | number | string>
>;

/**
 * @description 项目配置文件
 * 只需要覆盖项目中的一部分配置，不需要的配置不用覆盖，会自动使用默认配置
 * !!! 更改配置后请清空缓存，否则可能不生效
 */
export function createOverridesPreferences(
  locale: SupportedLanguagesType,
  name: string,
  timezone: string,
  loginTheme: BuiltinThemeType,
  loginLayout: AuthPageLayoutType,
  adminTheme: BuiltinThemeType,
  adminThemeMode: ThemeModeType,
  adminLayout: LayoutType,
  tabbar: AdminTabbarConfig,
  advancedPreferences: AdvancedPreferencesConfig,
) {
  configureAdminAppearance(adminTheme, adminThemeMode, adminLayout);

  return defineOverridesPreferences({
    app: {
      ...advancedPreferences.app,
      accessMode: 'backend',
      defaultAvatar,
      defaultHomePath: '/workspace',
      enablePreferences: false,
      enableRefreshToken: false,
      locale,
      name,
      timezone,
      authPageLayout: loginLayout,
    },
    breadcrumb: advancedPreferences.breadcrumb,
    header: advancedPreferences.header,
    navigation: advancedPreferences.navigation,
    shortcutKeys: advancedPreferences.shortcutKeys,
    sidebar: advancedPreferences.sidebar,
    theme: {
      ...advancedPreferences.theme,
      builtinType: loginTheme,
      colorPrimary:
        LOGIN_THEME_COLORS[loginTheme] || LOGIN_THEME_COLORS.default,
      mode: 'light',
    },
    tabbar,
    transition: advancedPreferences.transition,
    widget: {
      ...advancedPreferences.widget,
      refresh: false,
      timezone: false,
    },
  });
}

let adminAppearance: {
  layout: LayoutType;
  mode: ThemeModeType;
  theme: BuiltinThemeType;
} = { layout: 'sidebar-nav', mode: 'light', theme: 'default' };

export function configureAdminAppearance(
  theme: BuiltinThemeType,
  mode: ThemeModeType,
  layout: LayoutType,
) {
  adminAppearance = { layout, mode, theme };
}

export function getAdminAppearance() {
  return adminAppearance;
}

export const LOGIN_THEME_COLORS: Record<string, string> = {
  default: 'hsl(212 100% 45%)',
  'deep-blue': 'hsl(211 91% 39%)',
  'deep-green': 'hsl(181 84% 32%)',
  gray: 'hsl(217 19% 27%)',
  green: 'hsl(161 90% 43%)',
  neutral: 'hsl(0 0% 25%)',
  orange: 'hsl(18 89% 40%)',
  pink: 'hsl(347 77% 60%)',
  rose: 'hsl(0 75% 42%)',
  'sky-blue': 'hsl(231 98% 65%)',
  slate: 'hsl(215 25% 27%)',
  violet: 'hsl(245 82% 67%)',
  yellow: 'hsl(42 84% 61%)',
  zinc: 'hsl(240 5% 26%)',
};
