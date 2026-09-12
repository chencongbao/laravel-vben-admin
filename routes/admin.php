<?php

use Chencongbao\LaravelVbenAdmin\Http\Controllers\AccessController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminLogController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminMenuController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminPermissionController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminRoleController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminSettingController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AdminUserController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\AuthController;
use Chencongbao\LaravelVbenAdmin\Http\Controllers\ApplicationConfigController;
use Illuminate\Support\Facades\Route;

Route::get('application', ApplicationConfigController::class);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'admin.user'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::get('auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('auth/sessions', [AuthController::class, 'destroyOtherSessions']);
    Route::delete('auth/sessions/{tokenId}', [AuthController::class, 'destroySession'])->whereNumber('tokenId');
    Route::get('access/permissions', [AccessController::class, 'permissions']);
    Route::get('access/menus', [AccessController::class, 'menus']);

    Route::get('system/users', [AdminUserController::class, 'index'])->middleware('admin.permission:system.user.view');
    Route::post('system/users', [AdminUserController::class, 'store'])->middleware('admin.permission:system.user.create');
    Route::get('system/users/{adminUser}', [AdminUserController::class, 'show'])->middleware('admin.permission:system.user.view');
    Route::patch('system/users/{adminUser}', [AdminUserController::class, 'update'])->middleware('admin.permission:system.user.update');

    Route::get('system/roles', [AdminRoleController::class, 'index'])->middleware('admin.permission:system.role.view');
    Route::post('system/roles', [AdminRoleController::class, 'store'])->middleware('admin.permission:system.role.create');
    Route::get('system/roles/{adminRole}', [AdminRoleController::class, 'show'])->middleware('admin.permission:system.role.view');
    Route::patch('system/roles/{adminRole}', [AdminRoleController::class, 'update'])->middleware('admin.permission:system.role.update');
    Route::delete('system/roles/{adminRole}', [AdminRoleController::class, 'destroy'])->middleware('admin.permission:system.role.delete');
    Route::put('system/roles/{adminRole}/access', [AdminRoleController::class, 'access'])->middleware('admin.permission:system.role.assign-access');

    Route::get('system/permissions', [AdminPermissionController::class, 'index'])->middleware('admin.permission:system.permission.view');
    Route::post('system/permissions', [AdminPermissionController::class, 'store'])->middleware('admin.permission:system.permission.create');
    Route::get('system/permissions/{adminPermission}', [AdminPermissionController::class, 'show'])->middleware('admin.permission:system.permission.view');
    Route::patch('system/permissions/{adminPermission}', [AdminPermissionController::class, 'update'])->middleware('admin.permission:system.permission.update');
    Route::delete('system/permissions/{adminPermission}', [AdminPermissionController::class, 'destroy'])->middleware('admin.permission:system.permission.delete');

    Route::get('system/menus', [AdminMenuController::class, 'index'])->middleware('admin.permission:system.menu.view');
    Route::post('system/menus', [AdminMenuController::class, 'store'])->middleware('admin.permission:system.menu.create');
    Route::get('system/menus/{adminMenu}', [AdminMenuController::class, 'show'])->middleware('admin.permission:system.menu.view');
    Route::patch('system/menus/{adminMenu}', [AdminMenuController::class, 'update'])->middleware('admin.permission:system.menu.update');
    Route::delete('system/menus/{adminMenu}', [AdminMenuController::class, 'destroy'])->middleware('admin.permission:system.menu.delete');

    Route::get('system/audit-logs', [AdminLogController::class, 'audit'])->middleware('admin.permission:system.audit.view');
    Route::get('system/login-logs', [AdminLogController::class, 'login'])->middleware('admin.permission:system.login-log.view');
    Route::get('system/settings', [AdminSettingController::class, 'index'])->middleware('admin.permission:system.setting.view');
    Route::put('system/settings', [AdminSettingController::class, 'update'])->middleware('admin.permission:system.setting.update');
});
