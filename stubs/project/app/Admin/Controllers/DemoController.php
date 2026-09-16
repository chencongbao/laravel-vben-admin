<?php

namespace App\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class DemoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }
}
