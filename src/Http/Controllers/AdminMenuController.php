<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

    public function reorder(Request $request): JsonResponse
    {
        $menuTable = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists($menuTable, 'id')],
            'items.*.parent_code' => ['nullable', 'string', Rule::exists($menuTable, 'code')],
            'items.*.sort' => ['required', 'integer', 'min:-100000', 'max:100000'],
        ]);

        $menus = AdminMenu::query()->get()->keyBy('id');
        $submittedIds = collect($data['items'])->pluck('id')->sort()->values();
        if ($submittedIds->all() !== $menus->keys()->sort()->values()->all()) {
            return response()->json(['message' => 'The complete menu tree is required.', 'code' => 'MENU_REORDER_INCOMPLETE'], 422);
        }

        $codeById = $menus->mapWithKeys(fn (AdminMenu $menu) => [$menu->getKey() => $menu->code]);
        $parentByCode = collect($data['items'])->mapWithKeys(fn (array $item) => [$codeById[$item['id']] => $item['parent_code']]);
        foreach ($parentByCode as $code => $parentCode) {
            $visited = [];
            while ($parentCode !== null) {
                if ($parentCode === $code || isset($visited[$parentCode])) {
                    return response()->json(['message' => 'The menu hierarchy contains a cycle.', 'code' => 'MENU_CYCLE'], 422);
                }
                $visited[$parentCode] = true;
                $parentCode = $parentByCode[$parentCode] ?? null;
            }
        }

        $before = $menus->map(fn (AdminMenu $menu) => $menu->only(['id', 'parent_code', 'sort']))->values()->all();
        DB::transaction(function () use ($data, $request, $before): void {
            foreach ($data['items'] as $item) {
                AdminMenu::query()->whereKey($item['id'])->update([
                    'parent_code' => $item['parent_code'],
                    'sort' => $item['sort'],
                ]);
            }
            $this->audit->record($request->user(), 'system.menu.reordered', null, [
                'before' => $before,
                'after' => $data['items'],
            ]);
        });

        return $this->index();
    }

    public function update(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        if ($adminMenu->is_system && $request->has('code') && $request->string('code')->toString() !== $adminMenu->code) {
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
