<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminMenu;
use Chencongbao\LaravelVbenAdmin\Models\AdminPermission;
use Chencongbao\LaravelVbenAdmin\Services\PrivilegeAssignmentGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminMenuController extends Controller
{
    private const DEFAULT_MENU_CODE = 'dashboard.workspace';

    private const DEFAULT_MENU_ICON = 'lucide:list';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PrivilegeAssignmentGuard $privilegeGuard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $menuIds = $this->privilegeGuard->accessibleMenuIds($request->user());
        $permissionIds = $this->privilegeGuard->accessiblePermissionIds($request->user());
        $permissionTable = (new AdminPermission)->getTable();

        return response()->json([
            'menus' => AdminMenu::query()
                ->with(['permissions' => fn ($query) => $query
                    ->select($permissionTable.'.id', 'parent_id', 'code', 'name')
                    ->when($permissionIds !== null, fn ($query) => $query->whereKey($permissionIds))])
                ->where('code', '!=', self::DEFAULT_MENU_CODE)
                ->when($menuIds !== null, fn ($query) => $query->whereKey($menuIds))
                ->orderBy('sort')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);
        $menu = DB::transaction(function () use ($data, $permissionIds, $request): AdminMenu {
            $menu = AdminMenu::query()->create($data + [
                'is_system' => false,
            ]);
            $menu->permissions()->sync($permissionIds);
            $this->audit->record($request->user(), 'system.menu.created', $menu, [
                'after' => $data + ['permission_ids' => $permissionIds],
            ]);

            return $menu;
        });

        return response()->json(['menu' => $menu->load('permissions:id,parent_id,code,name')], 201);
    }

    public function show(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        abort_if($adminMenu->code === self::DEFAULT_MENU_CODE, 404);
        abort_unless($this->privilegeGuard->canAccessMenu($request->user(), $adminMenu), 404);

        $permissionIds = $this->privilegeGuard->accessiblePermissionIds($request->user());
        $permissionTable = (new AdminPermission)->getTable();

        return response()->json(['menu' => $adminMenu->load(['permissions' => fn ($query) => $query
            ->select($permissionTable.'.id', 'parent_id', 'code', 'name')
            ->when($permissionIds !== null, fn ($query) => $query->whereKey($permissionIds))])]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $menuTable = config('laravel-vben-admin.tables.menus', 'admin_menus');
        $workspaceId = AdminMenu::query()->where('code', self::DEFAULT_MENU_CODE)->value('id');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists($menuTable, 'id')],
            'items.*.parent_id' => ['nullable', 'integer', Rule::notIn([$workspaceId]), Rule::exists($menuTable, 'id')],
            'items.*.sort' => ['required', 'integer', 'min:-100000', 'max:100000'],
        ]);

        $menuIds = $this->privilegeGuard->accessibleMenuIds($request->user());
        $menus = AdminMenu::query()
            ->where('code', '!=', self::DEFAULT_MENU_CODE)
            ->when($menuIds !== null, fn ($query) => $query->whereKey($menuIds))
            ->get()
            ->keyBy('id');
        $submittedIds = collect($data['items'])->pluck('id')->sort()->values();
        if ($submittedIds->all() !== $menus->keys()->sort()->values()->all()) {
            return response()->json(['message' => 'The complete menu tree is required.', 'code' => 'MENU_REORDER_INCOMPLETE'], 422);
        }

        $parentById = collect($data['items'])->mapWithKeys(fn (array $item) => [$item['id'] => $item['parent_id']]);
        foreach ($parentById as $id => $parentId) {
            $visited = [];
            while ($parentId !== null) {
                if ($parentId === $id || isset($visited[$parentId])) {
                    return response()->json(['message' => 'The menu hierarchy contains a cycle.', 'code' => 'MENU_CYCLE'], 422);
                }
                $visited[$parentId] = true;
                $parentId = $parentById[$parentId] ?? null;
            }
        }

        $before = $menus->map(fn (AdminMenu $menu) => $menu->only(['id', 'parent_id', 'sort']))->values()->all();
        DB::transaction(function () use ($data, $request, $before): void {
            foreach ($data['items'] as $item) {
                AdminMenu::query()->whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'],
                    'sort' => $item['sort'],
                ]);
            }
            $this->audit->record($request->user(), 'system.menu.reordered', null, [
                'before' => $before,
                'after' => $data['items'],
            ]);
        });

        return $this->index($request);
    }

    public function update(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        abort_unless($this->privilegeGuard->canAccessMenu($request->user(), $adminMenu), 404);

        if ($adminMenu->code === self::DEFAULT_MENU_CODE) {
            return response()->json(['message' => 'The default workspace menu cannot be modified.', 'code' => 'DEFAULT_MENU_PROTECTED'], 422);
        }

        if ($adminMenu->is_system && $request->has('code') && $request->string('code')->toString() !== $adminMenu->code) {
            return response()->json(['message' => 'System menu identity cannot be modified.', 'code' => 'SYSTEM_MENU_PROTECTED'], 422);
        }

        $data = $this->validated($request, $adminMenu);
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $adminMenu->parent_id;
        if ($parentId === $adminMenu->getKey() || $this->createsCycle($adminMenu->getKey(), $parentId)) {
            return response()->json(['message' => 'The menu hierarchy contains a cycle.', 'code' => 'MENU_CYCLE'], 422);
        }

        $permissionIds = $data['permission_ids'] ?? null;
        unset($data['permission_ids']);
        $before = $adminMenu->load('permissions:id')->toArray();
        DB::transaction(function () use ($adminMenu, $before, $data, $permissionIds, $request): void {
            $adminMenu->update($data);
            if ($permissionIds !== null) {
                $adminMenu->permissions()->sync($permissionIds);
            }
            $after = $adminMenu->fresh()->load('permissions:id')->toArray();
            $this->audit->record($request->user(), 'system.menu.updated', $adminMenu, ['before' => $before, 'after' => $after]);
        });

        return response()->json(['menu' => $adminMenu->fresh()->load('permissions:id,parent_id,code,name')]);
    }

    public function destroy(Request $request, AdminMenu $adminMenu): JsonResponse
    {
        abort_unless($this->privilegeGuard->canAccessMenu($request->user(), $adminMenu), 404);

        if ($adminMenu->is_system) {
            return response()->json(['message' => 'System menus cannot be deleted.', 'code' => 'SYSTEM_MENU_PROTECTED'], 422);
        }
        if ($adminMenu->children()->exists()) {
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
        $workspaceId = AdminMenu::query()->where('code', self::DEFAULT_MENU_CODE)->value('id');

        $data = $request->validate([
            'code' => [$menu ? 'sometimes' : 'required', 'string', 'max:160', Rule::unique($menuTable, 'code')->ignore($menu?->getKey())],
            'parent_id' => ['nullable', 'integer', Rule::notIn([$workspaceId]), Rule::exists($menuTable, 'id')],
            'title' => [$menu ? 'sometimes' : 'required', 'string', 'max:160'],
            'type' => [$menu ? 'sometimes' : 'required', Rule::in(['directory', 'page', 'external'])],
            'route_name' => ['nullable', 'string', 'max:160', Rule::unique($menuTable, 'route_name')->ignore($menu?->getKey())],
            'route_path' => ['nullable', 'string', 'max:255'],
            'view_key' => ['nullable', 'string', 'max:160', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'permission_ids' => ['sometimes', 'array', 'max:500'],
            'permission_ids.*' => ['required', 'integer', 'distinct', Rule::exists($permissionTable, 'id')],
            'icon' => ['nullable', 'string', 'max:160'],
            'sort' => ['sometimes', 'integer', 'min:-100000', 'max:100000'],
        ]);

        if ((! $menu || array_key_exists('icon', $data)) && blank($data['icon'] ?? null)) {
            $data['icon'] = self::DEFAULT_MENU_ICON;
        }

        return $data;
    }

    private function createsCycle(int $menuId, ?int $parentId): bool
    {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === $menuId || isset($visited[$parentId])) {
                return true;
            }
            $visited[$parentId] = true;
            $parentId = AdminMenu::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
