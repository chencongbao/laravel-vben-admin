<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Support\AdminLocale;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Chencongbao\LaravelVbenAdmin\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ApplicationConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => SystemSettings::value('system.name'),
            'logo' => SystemSettings::logoUrl(),
            'locale' => AdminLocale::fromLaravel(),
            'supported_locales' => AdminLocale::SUPPORTED,
            'timezone' => 'Asia/Shanghai',
            'page_size' => AdminPagination::defaultPageSize(),
            'login_remember_me' => SystemSettings::value('system.login_remember_me'),
            'login_description' => SystemSettings::value('system.login_description'),
            'login_theme' => SystemSettings::value('system.login_theme'),
            'login_layout' => SystemSettings::value('system.login_layout'),
            'admin_theme' => SystemSettings::value('system.admin_theme'),
            'admin_theme_mode' => SystemSettings::value('system.admin_theme_mode'),
            'admin_layout' => SystemSettings::value('system.admin_layout'),
            'advanced_preferences' => SystemSettings::value('system.advanced_preferences'),
            'tabbar' => [
                'enable' => SystemSettings::value('system.tabbar_enable'),
                'persist' => SystemSettings::value('system.tabbar_persist'),
                'visit_history' => SystemSettings::value('system.tabbar_visit_history'),
                'max_count' => SystemSettings::value('system.tabbar_max_count'),
                'draggable' => SystemSettings::value('system.tabbar_draggable'),
                'wheelable' => SystemSettings::value('system.tabbar_wheelable'),
                'middle_click_to_close' => SystemSettings::value('system.tabbar_middle_click_to_close'),
                'show_icon' => SystemSettings::value('system.tabbar_show_icon'),
                'show_more' => SystemSettings::value('system.tabbar_show_more'),
                'show_maximize' => SystemSettings::value('system.tabbar_show_maximize'),
                'style_type' => SystemSettings::value('system.tabbar_style_type'),
            ],
        ]);
    }
}
