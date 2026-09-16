<?php

use App\Admin\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin.user'])->group(function (): void {
    Route::get('demo', [DemoController::class, 'index'])
        ->middleware('admin.permission:demo.view')
        ->name('admin.demo.index');
});
