<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Support\AdminLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ApplicationConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'locale' => AdminLocale::fromLaravel(),
            'supported_locales' => AdminLocale::SUPPORTED,
        ]);
    }
}
