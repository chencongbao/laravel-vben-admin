<?php

namespace Chencongbao\LaravelVbenAdmin\Support;

use Chencongbao\LaravelVbenAdmin\Models\AdminSetting;
use Throwable;

final class SystemSettings
{
    public static function definitions(): array
    {
        return [
            'system.name' => [
                'type' => 'string',
                'default' => (string) config('app.name', 'Laravel Vben Admin'),
            ],
            'system.page_size' => [
                'type' => 'integer',
                'default' => 20,
                'min' => 10,
                'max' => 100,
            ],
            'system.login_remember_me' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'system.login_description' => [
                'type' => 'string',
                'default' => '安全、高效、易扩展的后台管理平台',
            ],
            'system.login_theme' => [
                'type' => 'enum',
                'default' => 'default',
                'values' => ['default', 'violet', 'pink', 'yellow', 'sky-blue', 'green', 'zinc', 'deep-green', 'deep-blue', 'orange', 'rose', 'neutral', 'slate', 'gray'],
            ],
            'system.login_layout' => [
                'type' => 'enum',
                'default' => 'panel-right',
                'values' => ['panel-left', 'panel-center', 'panel-right'],
            ],
            'system.admin_theme' => [
                'type' => 'enum',
                'default' => 'default',
                'values' => ['default', 'violet', 'pink', 'yellow', 'sky-blue', 'green', 'zinc', 'deep-green', 'deep-blue', 'orange', 'rose', 'neutral', 'slate', 'gray'],
            ],
            'system.admin_theme_mode' => [
                'type' => 'enum',
                'default' => 'light',
                'values' => ['light', 'dark', 'auto'],
            ],
            'system.admin_layout' => [
                'type' => 'enum',
                'default' => 'sidebar-nav',
                'values' => ['sidebar-nav', 'sidebar-mixed-nav', 'header-nav', 'header-sidebar-nav', 'mixed-nav', 'header-mixed-nav', 'full-content'],
            ],
            'system.tabbar_enable' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_persist' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_visit_history' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_max_count' => ['type' => 'integer', 'default' => 0, 'min' => 0, 'max' => 30],
            'system.tabbar_draggable' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_wheelable' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_middle_click_to_close' => ['type' => 'boolean', 'default' => false],
            'system.tabbar_show_icon' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_show_more' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_show_maximize' => ['type' => 'boolean', 'default' => true],
            'system.tabbar_style_type' => [
                'type' => 'enum',
                'default' => 'chrome',
                'values' => ['chrome', 'plain', 'card', 'brisk'],
            ],
            'system.advanced_preferences' => [
                'type' => 'json',
                'default' => [
                    'app' => ['dynamicTitle' => true, 'contentCompact' => 'wide', 'watermark' => false, 'watermarkContent' => '', 'colorGrayMode' => false, 'colorWeakMode' => false],
                    'theme' => ['radius' => '0.5', 'fontSize' => 16, 'semiDarkHeader' => false, 'semiDarkSidebar' => false, 'semiDarkSidebarSub' => false],
                    'sidebar' => ['enable' => true, 'width' => 224, 'draggable' => true, 'collapsedShowTitle' => false, 'autoActivateChild' => false, 'expandOnHover' => true, 'collapsedButton' => true, 'fixedButton' => true],
                    'header' => ['enable' => true, 'mode' => 'fixed', 'menuAlign' => 'start'],
                    'navigation' => ['accordion' => true, 'split' => true, 'styleType' => 'rounded'],
                    'breadcrumb' => ['enable' => true, 'showIcon' => true, 'showHome' => false, 'hideOnlyOne' => false, 'styleType' => 'normal'],
                    'shortcutKeys' => ['enable' => true, 'globalSearch' => true, 'globalLogout' => true, 'globalLockScreen' => true],
                    'transition' => ['enable' => true, 'loading' => true, 'progress' => true, 'name' => 'fade-slide'],
                    'widget' => ['globalSearch' => true, 'fullscreen' => true, 'languageToggle' => true, 'notification' => true, 'themeToggle' => true, 'sidebarToggle' => true, 'lockScreen' => true],
                    'footer' => ['enable' => false, 'fixed' => false],
                    'copyright' => ['enable' => true, 'companyName' => '', 'companySiteLink' => '', 'date' => '2026', 'icp' => '', 'icpLink' => ''],
                    'custom' => [
                        'enableFormFullscreen' => true,
                        'tenantMode' => 'single',
                        'defaultTableSize' => 20,
                        'reportTitle' => '',
                    ],
                ],
            ],
        ];
    }

    public static function value(string $key): mixed
    {
        $definition = self::definitions()[$key] ?? null;

        if ($definition === null) {
            return null;
        }

        try {
            $setting = AdminSetting::query()->where('key', $key)->first();
        } catch (Throwable) {
            return $definition['default'];
        }

        if ($setting !== null) {
            if ($definition['type'] === 'json' && is_array($setting->value)) {
                $value = array_replace_recursive($definition['default'], $setting->value);

                if ($key === 'system.advanced_preferences') {
                    unset(
                        $value['custom']['boardTitle'],
                        $value['custom']['defaultVisibleCount'],
                        $value['custom']['showQuickActions'],
                        $value['custom']['highlightStyle'],
                    );
                }

                return $value;
            }

            return $setting->value;
        }

        if ($key === 'system.login_description') {
            $legacyValue = AdminSetting::query()
                ->whereIn('key', [
                    'system.login_description_zh_cn',
                    'system.login_description_en_us',
                ])
                ->orderByRaw("CASE WHEN `key` = 'system.login_description_zh_cn' THEN 0 ELSE 1 END")
                ->value('value');

            if (is_string($legacyValue) && $legacyValue !== '') {
                return $legacyValue;
            }
        }

        return $definition['default'];
    }
}
