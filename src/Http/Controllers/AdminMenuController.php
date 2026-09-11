<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

final class AdminMenuController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['menus' => AdminMenu::query()->orderBy('sort')->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $menu = AdminMenu::query()->create($data + ['is_system' => false]);
        $this->audit->record($request->user(), 'system.menu.created', $menu, ['after' => $data]);

        return response()->json(['menu' => $menu], 201);
    }

    public function show(AdminMenu $adminMenu): JsonResponse
    {
        return response()->json(['menu' => $adminMenu]);
    }

    public function update(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        if ($adminMenu->is_system && $request->has('code')) {
            return response()->json(['message' => 'System menu identity cannot be modified.', 'code' => 'SYSTEM_MENU_PROTECTED'], 422);
        }

        $data = $this->validated($request, $adminMenu);
        if (($data['parent_code'] ?? null) === $adminMenu->code || $this->createsCycle($adminMenu->code, $data['parent_code'] ?? $adminMenu->parent_code)) {
            return response()->json(['message' => 'The menu hierarchy contains a cycle.', 'code' => 'MENU_CYCLE'], 422);
        }

        $before = $adminMenu->toArray();
        $adminMenu->update($data);
        $this->audit->record($request->user(), 'system.menu.updated', $adminMenu, ['before' => $before, 'after' => $adminMenu->toArray()]);

        return response()->json(['menu' => $adminMenu]);
    }

    public function destroy(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        if ($adminMenu->is_system) {
            return response()->json(['message' => 'System menus cannot be deleted.', 'code' => 'SYSTEM_MENU_PROTECTED'], 422);
        }
        if (AdminMenu::query()->where('parent_code', $adminMenu->code)->exists()) {
            return response()->json(['message' => 'Delete or move child menus first.', 'code' => 'MENU_HAS_CHILDREN'], 422);
        }

        $this->audit->record($request->user(), 'system.menu.deleted', $adminMenu, ['before' => $adminMenu->toArray()]);
        $adminMenu->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?AdminMenu $menu = null): array
    {
        $menuTable = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $permissionTable = config('laravel-vben-admin.tables.permissions', 'admin_permissions');

        return $request->validate([
            'code' => [$menu ? 'sometimes' : 'required', 'string', 'max:160', Rule::unique($menuTable, 'code')->ignore($menu?->getKey())],
            'parent_code' => ['nullable', 'string', Rule::exists($menuTable, 'code')],
            'title' => [$menu ? 'sometimes' : 'required', 'string', 'max:160'],
            'type' => [$menu ? 'sometimes' : 'required', Rule::in(['directory', 'page', 'external'])],
            'route_name' => ['nullable', 'string', 'max:160', Rule::unique($menuTable, 'route_name')->ignore($menu?->getKey())],
            'route_path' => ['nullable', 'string', 'max:255'],
            'view_key' => ['nullable', 'string', 'max:160', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'permission_code' => ['nullable', 'string', Rule::exists($permissionTable, 'code')->where('is_active', true)],
            'icon' => ['nullable', 'string', 'max:160'],
            'sort' => ['sometimes', 'integer', 'min:-100000', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
        ]);
    }

    private function createsCycle(string $menuCode, ?string $parentCode): bool
    {
        $visited = [];
        while ($parentCode !== null) {
            if ($parentCode === $menuCode || isset($visited[$parentCode])) {
                return true;
            }
            $visited[$parentCode] = true;
            $parentCode = AdminMenu::query()->where('code', $parentCode)->value('parent_code');
        }

        return false;
    }
}
