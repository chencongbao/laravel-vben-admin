<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AccessController extends Controller
{
    private const DEFAULT_MENU_CODE = 'dashboard.workspace';

    private const DEFAULT_MENU_ORDER = -100001;

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
        $query = AdminMenu::query()
            ->with('permissions:id,code')
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [self::DEFAULT_MENU_CODE])
            ->orderBy('sort')
            ->orderBy('id');

        if (! $roles->contains('is_super_admin', true)) {
            $permissionCodes = $roles->flatMap(fn ($role) => $role->permissions()->where('is_active', true)->pluck('code'))->unique()->all();
            $query->where(function ($menuQuery) use ($roles, $permissionCodes): void {
                $menuQuery->where('code', self::DEFAULT_MENU_CODE)
                    ->orWhere(function ($roleMenuQuery) use ($roles, $permissionCodes): void {
                        $roleMenuQuery->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($roles->modelKeys()))
                            ->where(fn ($permissionQuery) => $permissionQuery->whereDoesntHave('permissions')->orWhereHas('permissions', fn ($query) => $query->whereIn('code', $permissionCodes)));
                    });
            });
        }

        $menus = $query->get()->map(fn (AdminMenu $menu) => [
            'id' => $menu->getKey(),
            'code' => $menu->code,
            'parent_id' => $menu->parent_id,
            'type' => $menu->type,
            'name' => $menu->route_name,
            'path' => $menu->route_path,
            'view_key' => $menu->view_key,
            'meta' => [
                'title' => $menu->title,
                'icon' => $menu->icon,
                'order' => $menu->code === self::DEFAULT_MENU_CODE ? self::DEFAULT_MENU_ORDER : $menu->sort,
                'affixTab' => $menu->code === self::DEFAULT_MENU_CODE,
                'tabClosable' => $menu->code !== self::DEFAULT_MENU_CODE,
                'authority' => $menu->permissions->pluck('code')->values()->all(),
            ],
            'children' => [],
        ])->keyBy('id')->all();

        $tree = [];
        foreach ($menus as $id => &$menu) {
            $parentId = $menu['parent_id'];
            unset($menu['id'], $menu['parent_id']);

            if ($parentId && isset($menus[$parentId])) {
                $menus[$parentId]['children'][] = &$menu;
            } else {
                $tree[] = &$menu;
            }
        }
        unset($menu);

        return response()->json(['menus' => array_values($tree)]);
    }
}
