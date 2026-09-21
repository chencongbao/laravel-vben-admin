<script lang="ts" setup>
import type {
  AuthPageLayoutType,
  BuiltinThemeType,
  LayoutType,
  ThemeModeType,
} from '@vben/types';
import type { SettingItem } from '#/api/system';

import { computed, onMounted, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import {
  IconifyIcon,
  InspectionPanel,
  MoonStar,
  PanelLeft,
  PanelRight,
  Sun,
  SunMoon,
} from '@vben/icons';
import { PreferencesLayout } from '@vben/layouts';
import {
  BUILT_IN_THEME_PRESETS,
  preferences,
  updatePreferences,
} from '@vben/preferences';

import {
  Button,
  Card,
  Form,
  FormItem,
  Input,
  InputNumber,
  message,
  Select,
  Switch,
  TabPane,
  Tabs,
} from 'ant-design-vue';

import { getCollection, updateThemeSettings } from '#/api/system';
import { $t } from '#/locales';
import { LOGIN_THEME_COLORS } from '#/preferences';

const loading = ref(false);
const saving = ref(false);
const activeGroup = ref('login');
const activeAdvancedSection = ref('appearance');
const settings = ref<SettingItem[]>([]);
const { hasAccessByCodes } = useAccess();
const canSaveTheme = computed(
  () =>
    hasAccessByCodes(['*']) || hasAccessByCodes(['system.theme-setting.view']),
);
const themePresets = BUILT_IN_THEME_PRESETS.filter(
  ({ type }) => type !== 'custom',
);

type AdvancedField = {
  key: string;
  label: [string, string];
  max?: number;
  min?: number;
  options?: Array<{ label: [string, string]; value: string }>;
  type: 'boolean' | 'number' | 'select' | 'string';
};

const advancedSections: Array<{
  fields: AdvancedField[];
  key: string;
  title: [string, string];
}> = [
  {
    key: 'appearance',
    title: ['内容与外观', 'Content & Appearance'],
    fields: [
      {
        key: 'app.dynamicTitle',
        label: ['动态页面标题', 'Dynamic page title'],
        type: 'boolean',
      },
      {
        key: 'app.contentCompact',
        label: ['内容宽度', 'Content width'],
        type: 'select',
        options: [
          { label: ['流式', 'Wide'], value: 'wide' },
          { label: ['定宽', 'Compact'], value: 'compact' },
        ],
      },
      {
        key: 'theme.radius',
        label: ['界面圆角', 'Interface radius'],
        type: 'select',
        options: [
          { label: ['无圆角', 'None'], value: '0' },
          { label: ['小', 'Small'], value: '0.25' },
          { label: ['默认', 'Default'], value: '0.5' },
          { label: ['大', 'Large'], value: '0.75' },
        ],
      },
      {
        key: 'theme.fontSize',
        label: ['基础字号', 'Base font size'],
        type: 'number',
        min: 12,
        max: 20,
      },
      {
        key: 'app.colorGrayMode',
        label: ['灰色模式', 'Gray mode'],
        type: 'boolean',
      },
      {
        key: 'app.colorWeakMode',
        label: ['色弱模式', 'Color-weak mode'],
        type: 'boolean',
      },
      {
        key: 'theme.semiDarkHeader',
        label: ['深色顶栏', 'Dark header'],
        type: 'boolean',
      },
      {
        key: 'theme.semiDarkSidebar',
        label: ['深色侧边栏', 'Dark sidebar'],
        type: 'boolean',
      },
      {
        key: 'theme.semiDarkSidebarSub',
        label: ['深色子菜单', 'Dark submenu'],
        type: 'boolean',
      },
    ],
  },
  {
    key: 'navigation',
    title: ['侧边栏与导航', 'Sidebar & Navigation'],
    fields: [
      {
        key: 'sidebar.enable',
        label: ['显示侧边栏', 'Show sidebar'],
        type: 'boolean',
      },
      {
        key: 'sidebar.width',
        label: ['侧边栏宽度', 'Sidebar width'],
        type: 'number',
        min: 180,
        max: 320,
      },
      {
        key: 'sidebar.draggable',
        label: ['允许拖动宽度', 'Resizable sidebar'],
        type: 'boolean',
      },
      {
        key: 'sidebar.collapsedShowTitle',
        label: ['折叠后显示标题', 'Show collapsed titles'],
        type: 'boolean',
      },
      {
        key: 'sidebar.autoActivateChild',
        label: ['自动激活子菜单', 'Auto-activate child'],
        type: 'boolean',
      },
      {
        key: 'sidebar.expandOnHover',
        label: ['固定展开侧边栏', 'Keep sidebar expanded'],
        type: 'boolean',
      },
      {
        key: 'sidebar.collapsedButton',
        label: ['显示折叠按钮', 'Show collapse button'],
        type: 'boolean',
      },
      {
        key: 'sidebar.fixedButton',
        label: ['显示固定按钮', 'Show pin button'],
        type: 'boolean',
      },
      {
        key: 'navigation.accordion',
        label: ['菜单手风琴模式', 'Menu accordion'],
        type: 'boolean',
      },
      {
        key: 'navigation.split',
        label: ['拆分导航菜单', 'Split navigation'],
        type: 'boolean',
      },
      {
        key: 'navigation.styleType',
        label: ['导航样式', 'Navigation style'],
        type: 'select',
        options: [
          { label: ['圆角', 'Rounded'], value: 'rounded' },
          { label: ['朴素', 'Plain'], value: 'plain' },
        ],
      },
    ],
  },
  {
    key: 'header',
    title: ['顶栏与面包屑', 'Header & Breadcrumb'],
    fields: [
      {
        key: 'header.enable',
        label: ['显示顶栏', 'Show header'],
        type: 'boolean',
      },
      {
        key: 'header.mode',
        label: ['顶栏模式', 'Header mode'],
        type: 'select',
        options: [
          { label: ['固定', 'Fixed'], value: 'fixed' },
          { label: ['静态', 'Static'], value: 'static' },
        ],
      },
      {
        key: 'header.menuAlign',
        label: ['顶栏菜单对齐', 'Header menu alignment'],
        type: 'select',
        options: [
          { label: ['左侧', 'Left'], value: 'start' },
          { label: ['居中', 'Center'], value: 'center' },
          { label: ['右侧', 'Right'], value: 'end' },
        ],
      },
      {
        key: 'breadcrumb.enable',
        label: ['显示面包屑', 'Show breadcrumb'],
        type: 'boolean',
      },
      {
        key: 'breadcrumb.showIcon',
        label: ['显示面包屑图标', 'Show breadcrumb icons'],
        type: 'boolean',
      },
      {
        key: 'breadcrumb.showHome',
        label: ['显示首页入口', 'Show home entry'],
        type: 'boolean',
      },
      {
        key: 'breadcrumb.hideOnlyOne',
        label: ['仅一项时隐藏', 'Hide when single'],
        type: 'boolean',
      },
      {
        key: 'breadcrumb.styleType',
        label: ['面包屑样式', 'Breadcrumb style'],
        type: 'select',
        options: [
          { label: ['普通', 'Normal'], value: 'normal' },
          { label: ['背景', 'Background'], value: 'background' },
        ],
      },
    ],
  },
  {
    key: 'shortcuts',
    title: ['快捷键、动画与工具栏', 'Shortcuts, Animation & Toolbar'],
    fields: [
      {
        key: 'shortcutKeys.enable',
        label: ['启用快捷键', 'Enable shortcuts'],
        type: 'boolean',
      },
      {
        key: 'shortcutKeys.globalSearch',
        label: ['搜索快捷键', 'Search shortcut'],
        type: 'boolean',
      },
      {
        key: 'shortcutKeys.globalLogout',
        label: ['退出快捷键', 'Logout shortcut'],
        type: 'boolean',
      },
      {
        key: 'shortcutKeys.globalLockScreen',
        label: ['锁屏快捷键', 'Lock-screen shortcut'],
        type: 'boolean',
      },
      {
        key: 'transition.enable',
        label: ['页面切换动画', 'Page transitions'],
        type: 'boolean',
      },
      {
        key: 'transition.loading',
        label: ['页面加载动画', 'Page loading animation'],
        type: 'boolean',
      },
      {
        key: 'transition.progress',
        label: ['顶部进度条', 'Top progress bar'],
        type: 'boolean',
      },
      {
        key: 'transition.name',
        label: ['切换动画样式', 'Transition style'],
        type: 'select',
        options: [
          { label: ['淡入滑动', 'Fade slide'], value: 'fade-slide' },
          { label: ['淡入', 'Fade'], value: 'fade' },
          { label: ['向上淡入', 'Fade up'], value: 'fade-up' },
          { label: ['向下淡入', 'Fade down'], value: 'fade-down' },
        ],
      },
      {
        key: 'widget.globalSearch',
        label: ['顶部搜索', 'Header search'],
        type: 'boolean',
      },
      {
        key: 'widget.fullscreen',
        label: ['全屏按钮', 'Fullscreen button'],
        type: 'boolean',
      },
      {
        key: 'widget.languageToggle',
        label: ['语言切换', 'Language switch'],
        type: 'boolean',
      },
      {
        key: 'widget.notification',
        label: ['通知按钮', 'Notification button'],
        type: 'boolean',
      },
      {
        key: 'widget.themeToggle',
        label: ['明暗切换', 'Theme switch'],
        type: 'boolean',
      },
      {
        key: 'widget.sidebarToggle',
        label: ['侧边栏切换', 'Sidebar switch'],
        type: 'boolean',
      },
      {
        key: 'widget.lockScreen',
        label: ['锁屏功能', 'Lock screen'],
        type: 'boolean',
      },
    ],
  },
];

const groups = computed(() => [
  {
    description: $t('configuration.themeForm.login.description'),
    key: 'login',
    keys: ['system.login_theme', 'system.login_layout'],
    title: $t('configuration.themeForm.login.title'),
  },
  {
    description: $t('configuration.themeForm.admin.description'),
    key: 'admin',
    keys: [
      'system.admin_theme',
      'system.admin_theme_mode',
      'system.admin_layout',
    ],
    title: $t('configuration.themeForm.admin.title'),
  },
  {
    description: $t('configuration.themeForm.tabbar.description'),
    key: 'tabbar',
    keys: [
      'system.tabbar_enable',
      'system.tabbar_persist',
      'system.tabbar_visit_history',
      'system.tabbar_max_count',
      'system.tabbar_draggable',
      'system.tabbar_wheelable',
      'system.tabbar_middle_click_to_close',
      'system.tabbar_show_icon',
      'system.tabbar_show_more',
      'system.tabbar_show_maximize',
      'system.tabbar_style_type',
    ],
    title: $t('configuration.themeForm.tabbar.title'),
  },
  {
    description:
      preferences.app.locale === 'en-US'
        ? 'Configure the remaining shared interface preferences.'
        : '集中配置其余未重复的后台界面偏好。',
    key: 'advanced',
    keys: ['system.advanced_preferences'],
    title: preferences.app.locale === 'en-US' ? 'Other settings' : '其他设置',
  },
]);

function localizedText(value: [string, string]) {
  return value[preferences.app.locale === 'en-US' ? 1 : 0];
}

function advancedSetting() {
  return settings.value.find(
    ({ key }) => key === 'system.advanced_preferences',
  );
}

function advancedValue(path: string) {
  const [group, key] = path.split('.');
  if (!group || !key) return undefined;
  return advancedSetting()?.value?.[group]?.[key];
}

function setAdvancedValue(path: string, value: unknown) {
  const item = advancedSetting();
  if (!item) return;
  const [group, key] = path.split('.');
  if (!group || !key) return;
  item.value ||= {};
  item.value[group] ||= {};
  item.value[group][key] = value;
}

const settingOptions: Record<string, string[]> = {
  'system.admin_theme': [
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
  ],
  'system.admin_theme_mode': ['light', 'dark', 'auto'],
  'system.login_theme': [
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
  ],
};

const fieldNames: Record<string, string> = {
  'system.admin_theme': 'adminTheme',
  'system.admin_theme_mode': 'adminThemeMode',
  'system.admin_layout': 'adminLayout',
  'system.login_layout': 'loginLayout',
  'system.login_theme': 'loginTheme',
  'system.tabbar_draggable': 'tabbarDraggable',
  'system.tabbar_enable': 'tabbarEnable',
  'system.tabbar_max_count': 'tabbarMaxCount',
  'system.tabbar_middle_click_to_close': 'tabbarMiddleClickToClose',
  'system.tabbar_persist': 'tabbarPersist',
  'system.tabbar_show_icon': 'tabbarShowIcon',
  'system.tabbar_show_maximize': 'tabbarShowMaximize',
  'system.tabbar_show_more': 'tabbarShowMore',
  'system.tabbar_style_type': 'tabbarStyleType',
  'system.tabbar_visit_history': 'tabbarVisitHistory',
  'system.tabbar_wheelable': 'tabbarWheelable',
};

function groupSettings(keys: string[]) {
  return settings.value.filter(({ key }) => keys.includes(key));
}

function isThemeSetting(key: string) {
  return key === 'system.login_theme' || key === 'system.admin_theme';
}

const loginLayouts: Array<{
  icon: typeof PanelLeft;
  label: string;
  value: AuthPageLayoutType;
}> = [
  {
    icon: PanelLeft,
    label: 'configuration.themeForm.options.loginLayout.panel-left',
    value: 'panel-left',
  },
  {
    icon: InspectionPanel,
    label: 'configuration.themeForm.options.loginLayout.panel-center',
    value: 'panel-center',
  },
  {
    icon: PanelRight,
    label: 'configuration.themeForm.options.loginLayout.panel-right',
    value: 'panel-right',
  },
];

const themeModes: Array<{
  icon: typeof Sun;
  label: string;
  value: ThemeModeType;
}> = [
  {
    icon: Sun,
    label: 'configuration.themeForm.options.themeMode.light',
    value: 'light',
  },
  {
    icon: MoonStar,
    label: 'configuration.themeForm.options.themeMode.dark',
    value: 'dark',
  },
  {
    icon: SunMoon,
    label: 'configuration.themeForm.options.themeMode.auto',
    value: 'auto',
  },
];

function isLoginLayoutSetting(key: string) {
  return key === 'system.login_layout';
}

function isAdminLayoutSetting(key: string) {
  return key === 'system.admin_layout';
}

function isThemeModeSetting(key: string) {
  return key === 'system.admin_theme_mode';
}

function isTabbarSetting(key: string) {
  return key.startsWith('system.tabbar_');
}

function tabbarStyleOptions() {
  return ['chrome', 'plain', 'card', 'brisk'].map((value) => ({
    label: $t(`configuration.themeForm.options.tabbarStyle.${value}`),
    value,
  }));
}

function selectTheme(item: SettingItem, theme: BuiltinThemeType) {
  item.value = theme;
}

function fieldText(key: string, field: 'description' | 'label') {
  return $t(`configuration.themeForm.fields.${fieldNames[key]}.${field}`);
}

function selectOptions(key: string) {
  const optionGroup = key.endsWith('_layout')
    ? 'loginLayout'
    : key.endsWith('_mode')
      ? 'themeMode'
      : 'theme';

  return (settingOptions[key] || []).map((value) => ({
    label: $t(`configuration.themeForm.options.${optionGroup}.${value}`),
    value,
  }));
}

async function load() {
  loading.value = true;
  try {
    const result = await getCollection<{ settings: SettingItem[] }>(
      '/system/theme-settings',
    );
    settings.value = result.settings;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const result = await updateThemeSettings(
      settings.value.map(({ key, value }) => ({ key, value })),
    );
    settings.value = result.settings;

    const adminTheme = settings.value.find(
      ({ key }) => key === 'system.admin_theme',
    )?.value as BuiltinThemeType;
    const adminThemeMode = settings.value.find(
      ({ key }) => key === 'system.admin_theme_mode',
    )?.value as ThemeModeType;
    const adminLayout = settings.value.find(
      ({ key }) => key === 'system.admin_layout',
    )?.value as LayoutType;

    const settingValue = (key: string) =>
      settings.value.find((item) => item.key === key)?.value;
    const advancedPreferences =
      settingValue('system.advanced_preferences') || {};
    updatePreferences({
      ...advancedPreferences,
      app: { ...advancedPreferences.app, layout: adminLayout },
      theme: {
        ...advancedPreferences.theme,
        builtinType: adminTheme,
        colorPrimary:
          LOGIN_THEME_COLORS[adminTheme] || LOGIN_THEME_COLORS.default,
        mode: adminThemeMode,
      },
      tabbar: {
        draggable: settingValue('system.tabbar_draggable'),
        enable: settingValue('system.tabbar_enable'),
        maxCount: settingValue('system.tabbar_max_count'),
        middleClickToClose: settingValue('system.tabbar_middle_click_to_close'),
        persist: settingValue('system.tabbar_persist'),
        showIcon: settingValue('system.tabbar_show_icon'),
        showMaximize: settingValue('system.tabbar_show_maximize'),
        showMore: settingValue('system.tabbar_show_more'),
        styleType: settingValue('system.tabbar_style_type'),
        visitHistory: settingValue('system.tabbar_visit_history'),
        wheelable: settingValue('system.tabbar_wheelable'),
      },
      widget: {
        ...advancedPreferences.widget,
        refresh: false,
        timezone: false,
      },
    });
    message.success($t('configuration.themeForm.saved'));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <Page
    :description="$t('configuration.themeDescription')"
    :title="$t('configuration.themeSettings')"
  >
    <Card :bordered="false" :loading="loading" class="theme-card">
      <Form layout="vertical">
        <Tabs v-model:active-key="activeGroup" class="theme-tabs">
          <TabPane v-for="group in groups" :key="group.key" :tab="group.title">
            <section class="theme-group">
              <header class="theme-group-header">
                <h2>{{ group.title }}</h2>
                <p>{{ group.description }}</p>
              </header>

              <Tabs
                v-if="group.key === 'advanced'"
                v-model:active-key="activeAdvancedSection"
                class="advanced-tabs"
                type="card"
              >
                <TabPane
                  v-for="section in advancedSections"
                  :key="section.key"
                  :tab="localizedText(section.title)"
                >
                  <section class="advanced-section">
                    <div class="advanced-grid">
                      <div
                        v-for="field in section.fields"
                        :key="field.key"
                        class="advanced-item"
                      >
                        <label :for="`advanced-${field.key}`">
                          {{ localizedText(field.label) }}
                        </label>
                        <Switch
                          v-if="field.type === 'boolean'"
                          :id="`advanced-${field.key}`"
                          :checked="Boolean(advancedValue(field.key))"
                          @update:checked="setAdvancedValue(field.key, $event)"
                        />
                        <InputNumber
                          v-else-if="field.type === 'number'"
                          :id="`advanced-${field.key}`"
                          :max="field.max"
                          :min="field.min"
                          :value="Number(advancedValue(field.key))"
                          class="advanced-input"
                          @update:value="setAdvancedValue(field.key, $event)"
                        />
                        <Select
                          v-else-if="field.type === 'select'"
                          :id="`advanced-${field.key}`"
                          :options="
                            field.options?.map((option) => ({
                              label: localizedText(option.label),
                              value: option.value,
                            }))
                          "
                          :value="advancedValue(field.key)"
                          class="advanced-input"
                          @update:value="setAdvancedValue(field.key, $event)"
                        />
                        <Input
                          v-else
                          :id="`advanced-${field.key}`"
                          :value="String(advancedValue(field.key) ?? '')"
                          class="advanced-input"
                          @update:value="setAdvancedValue(field.key, $event)"
                        />
                      </div>
                    </div>
                  </section>
                </TabPane>
              </Tabs>

              <div v-else class="theme-list">
                <div
                  v-for="item in groupSettings(group.keys)"
                  :key="item.key"
                  class="theme-row"
                >
                  <div>
                    <label :for="`setting-${item.key}`" class="theme-label">
                      {{ fieldText(item.key, 'label') }}
                    </label>
                    <p class="theme-description">
                      {{ fieldText(item.key, 'description') }}
                    </p>
                  </div>
                  <FormItem class="theme-control">
                    <div
                      v-if="isThemeSetting(item.key)"
                      class="color-presets"
                      role="radiogroup"
                    >
                      <button
                        v-for="preset in themePresets"
                        :key="preset.type"
                        :aria-checked="item.value === preset.type"
                        :aria-label="
                          $t(
                            `configuration.themeForm.options.theme.${preset.type}`,
                          )
                        "
                        :class="{
                          'color-preset-active': item.value === preset.type,
                        }"
                        :style="{ backgroundColor: preset.color }"
                        :title="
                          $t(
                            `configuration.themeForm.options.theme.${preset.type}`,
                          )
                        "
                        class="color-preset"
                        role="radio"
                        type="button"
                        @click="selectTheme(item, preset.type)"
                      >
                        <svg
                          v-if="item.value === preset.type"
                          aria-hidden="true"
                          height="16"
                          viewBox="0 0 15 15"
                          width="16"
                        >
                          <path
                            clip-rule="evenodd"
                            d="M11.467 3.727c.289.189.37.576.181.865l-4.25 6.5a.625.625 0 0 1-.944.12l-2.75-2.5a.625.625 0 0 1 .841-.925l2.208 2.007l3.849-5.886a.625.625 0 0 1 .865-.181"
                            fill="currentColor"
                            fill-rule="evenodd"
                          />
                        </svg>
                      </button>
                    </div>
                    <div
                      v-else-if="isLoginLayoutSetting(item.key)"
                      class="layout-presets"
                      role="radiogroup"
                    >
                      <button
                        v-for="layout in loginLayouts"
                        :key="layout.value"
                        :aria-checked="item.value === layout.value"
                        :class="{
                          'layout-preset-active': item.value === layout.value,
                        }"
                        class="layout-preset"
                        role="radio"
                        type="button"
                        @click="item.value = layout.value"
                      >
                        <span class="login-layout-preview">
                          <component :is="layout.icon" />
                        </span>
                        <span>{{ $t(layout.label) }}</span>
                      </button>
                    </div>
                    <Switch
                      v-else-if="
                        isTabbarSetting(item.key) && item.type === 'boolean'
                      "
                      :id="`setting-${item.key}`"
                      v-model:checked="item.value"
                    />
                    <InputNumber
                      v-else-if="
                        isTabbarSetting(item.key) && item.type === 'integer'
                      "
                      :id="`setting-${item.key}`"
                      v-model:value="item.value"
                      :max="30"
                      :min="0"
                      :step="5"
                      class="tabbar-number"
                    />
                    <Select
                      v-else-if="item.key === 'system.tabbar_style_type'"
                      :id="`setting-${item.key}`"
                      v-model:value="item.value"
                      :options="tabbarStyleOptions()"
                      class="w-full"
                    />
                    <PreferencesLayout
                      v-else-if="isAdminLayoutSetting(item.key)"
                      v-model="item.value"
                    />
                    <div
                      v-else-if="isThemeModeSetting(item.key)"
                      class="mode-presets"
                      role="radiogroup"
                    >
                      <button
                        v-for="mode in themeModes"
                        :key="mode.value"
                        :aria-checked="item.value === mode.value"
                        :class="{
                          'mode-preset-active': item.value === mode.value,
                        }"
                        class="mode-preset"
                        role="radio"
                        type="button"
                        @click="item.value = mode.value"
                      >
                        <span class="mode-preview">
                          <component :is="mode.icon" />
                        </span>
                        <span>{{ $t(mode.label) }}</span>
                      </button>
                    </div>
                    <Select
                      v-else
                      :id="`setting-${item.key}`"
                      v-model:value="item.value"
                      :options="selectOptions(item.key)"
                      class="w-full"
                    />
                  </FormItem>
                </div>
              </div>
            </section>
          </TabPane>
        </Tabs>

        <div class="theme-actions">
          <Button
            v-if="canSaveTheme"
            :loading="saving"
            size="large"
            type="primary"
            @click="save"
          >
            <IconifyIcon icon="lucide:save" />
            {{ $t('configuration.themeForm.save') }}
          </Button>
        </div>
      </Form>
    </Card>
  </Page>
</template>

<style scoped>
.theme-card {
  max-width: 960px;
  margin: 0 auto;
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  box-shadow: 0 8px 28px rgb(15 23 42 / 5%);
}

.theme-tabs :deep(.ant-tabs-nav) {
  margin-bottom: 24px;
}

.theme-tabs :deep(.ant-tabs-tab) {
  padding: 10px 4px;
  font-size: 15px;
  font-weight: 500;
}

.theme-group-header {
  margin-bottom: 14px;
}

.theme-group-header h2 {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 600;
}

.theme-group-header p,
.theme-description {
  margin: 0;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  line-height: 20px;
}

.theme-list {
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
}

.advanced-tabs :deep(.ant-tabs-nav) {
  margin-bottom: 16px;
}

.advanced-tabs :deep(.ant-tabs-nav-list) {
  flex-wrap: wrap;
  gap: 6px;
}

.advanced-tabs :deep(.ant-tabs-tab) {
  padding-right: 14px;
  padding-left: 14px;
  margin: 0 !important;
  border-radius: var(--radius) var(--radius) 0 0 !important;
}

.advanced-section {
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  background: hsl(var(--background));
}

.advanced-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.advanced-item {
  display: flex;
  min-height: 64px;
  gap: 20px;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid hsl(var(--border));
}

.advanced-item:nth-child(odd) {
  border-right: 1px solid hsl(var(--border));
}

.advanced-item label {
  color: hsl(var(--foreground));
  font-size: 13px;
  font-weight: 500;
}

.advanced-input {
  width: 150px;
  flex: 0 0 150px;
}

.theme-row {
  display: grid;
  grid-template-columns: minmax(220px, 0.85fr) minmax(320px, 1.15fr);
  gap: 40px;
  align-items: center;
  padding: 22px 24px;
  background: hsl(var(--background));
}

.theme-row + .theme-row {
  border-top: 1px solid hsl(var(--border));
}

.theme-label {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  font-weight: 600;
}

.theme-control {
  width: 100%;
  margin-bottom: 0;
}

.color-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: center;
  min-height: 32px;
}

.color-preset {
  position: relative;
  display: inline-flex;
  width: 28px;
  height: 28px;
  cursor: pointer;
  color: white;
  appearance: none;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 3px solid hsl(var(--background));
  border-radius: 999px;
  outline: 1px solid transparent;
  box-shadow: 0 1px 3px rgb(15 23 42 / 15%);
  transition:
    transform 150ms ease,
    outline-color 150ms ease;
}

.color-preset:hover {
  transform: scale(1.1);
}

.color-preset:focus-visible,
.color-preset-active {
  outline-color: hsl(var(--foreground));
}

.layout-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.mode-presets {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.mode-preset {
  display: flex;
  min-width: 0;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
  appearance: none;
  flex-direction: column;
  gap: 8px;
  align-items: center;
  padding: 0;
  font-size: 13px;
  background: transparent;
  border: 0;
}

.mode-preview {
  display: flex;
  width: 100%;
  height: 56px;
  color: hsl(var(--foreground));
  align-items: center;
  justify-content: center;
  background: hsl(var(--background));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  outline: 2px solid transparent;
  outline-offset: 1px;
  transition:
    border-color 150ms ease,
    outline-color 150ms ease,
    background-color 150ms ease;
}

.mode-preview :deep(svg) {
  width: 21px;
  height: 21px;
}

.mode-preset:hover,
.mode-preset-active {
  color: hsl(var(--foreground));
}

.mode-preset:hover .mode-preview {
  background: hsl(var(--muted) / 45%);
}

.mode-preset:focus-visible .mode-preview,
.mode-preset-active .mode-preview {
  border-color: hsl(var(--primary));
  outline-color: hsl(var(--primary));
}

.layout-preset {
  display: flex;
  width: 100px;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
  appearance: none;
  flex-direction: column;
  gap: 8px;
  align-items: center;
  padding: 0;
  font-size: 12px;
  background: transparent;
  border: 0;
}

.login-layout-preview {
  display: flex;
  width: 100px;
  height: 62px;
  color: hsl(var(--primary));
  align-items: center;
  justify-content: center;
  background: hsl(var(--muted) / 55%);
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  outline: 2px solid transparent;
  outline-offset: 1px;
}

.login-layout-preview :deep(svg) {
  width: 34px;
  height: 34px;
}

.layout-preset:hover,
.layout-preset-active {
  color: hsl(var(--foreground));
}

.layout-preset:focus-visible .login-layout-preview,
.layout-preset-active .login-layout-preview {
  border-color: hsl(var(--primary));
  outline-color: hsl(var(--primary));
}

.theme-actions {
  display: flex;
  justify-content: center;
  padding-top: 20px;
}

.theme-actions :deep(.ant-btn) {
  min-width: 112px;
  border-radius: var(--radius);
}

@media (max-width: 767px) {
  .theme-row {
    grid-template-columns: 1fr;
    gap: 14px;
    padding: 20px;
  }

  .mode-presets {
    gap: 10px;
  }

  .advanced-grid {
    grid-template-columns: 1fr;
  }

  .advanced-item:nth-child(odd) {
    border-right: 0;
  }
}
</style>
