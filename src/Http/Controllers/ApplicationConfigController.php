<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Support\AdminLocale;
use Chencongbao\LaravelVbenAdmin\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ApplicationConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => SystemSettings::value('system.name'),
            'locale' => AdminLocale::fromLaravel(),
            'supported_locales' => AdminLocale::SUPPORTED,
            'timezone' => (string) config('app.timezone', 'UTC'),
        ]);
    }
}
