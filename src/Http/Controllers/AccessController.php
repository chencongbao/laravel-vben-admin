<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AccessController extends Controller
{
    public function permissions(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $roles = $user->roles()->where('is_active', true)->get();

        if ($roles->contains('is_super_admin', true)) {
            return response()->json(['permissions' => ['*']]);
        }

        $permissions = $roles->flatMap(fn ($role) => $role->permissions()->where('is_active', true)->pluck('code'))->unique()->values();

        return response()->json(['permissions' => $permissions]);
    }

    public function menus(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->user();
        $roles = $user->roles()->where('is_active', true)->get();
        $query = AdminMenu::query()->where('is_active', true)->orderBy('sort')->orderBy('id');

        if (! $roles->contains('is_super_admin', true)) {
            $permissionCodes = $roles->flatMap(fn ($role) => $role->permissions()->where('is_active', true)->pluck('code'))->unique()->all();
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($roles->modelKeys()))
                ->where(fn ($menuQuery) => $menuQuery->whereNull('permission_code')->orWhereIn('permission_code', $permissionCodes));
        }

        $menus = $query->get()->map(fn (AdminMenu $menu) => [
            'code' => $menu->code,
            'parent_code' => $menu->parent_code,
            'type' => $menu->type,
            'name' => $menu->route_name,
            'path' => $menu->route_path,
            'view_key' => $menu->view_key,
            'meta' => ['title' => $menu->title, 'icon' => $menu->icon, 'order' => $menu->sort, 'hidden' => $menu->is_hidden, 'authority' => array_values(array_filter([$menu->permission_code]))],
            'children' => [],
        ])->keyBy('code');

        $tree = [];
        foreach ($menus as $code => &$menu) {
            $parentCode = $menu['parent_code'];
            unset($menu['parent_code']);

            if ($parentCode && isset($menus[$parentCode])) {
                $menus[$parentCode]['children'][] = &$menu;
            } else {
                $tree[] = &$menu;
            }
        }
        unset($menu);

        return response()->json(['menus' => array_values($tree)]);
    }
}
