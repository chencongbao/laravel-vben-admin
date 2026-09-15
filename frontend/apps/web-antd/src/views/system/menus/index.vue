<script lang="ts" setup>
import type { Key } from 'ant-design-vue/es/_util/type';

import { computed, onMounted, reactive, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';
import { $t } from '@vben/locales';

import { Draggable } from '@he-tree/vue';
import {
  Card,
  Checkbox,
  Empty,
  Form,
  FormItem,
  Input,
  InputNumber,
  message,
  Popconfirm,
  Select,
  Space,
  Switch,
  Tree,
} from 'ant-design-vue';

import {
  createResource,
  deleteResource,
  getCollection,
  getResource,
  reorderMenus,
  updateResource,
} from '#/api/system';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';

import '@he-tree/vue/style/default.css';

interface MenuItem {
  code: string;
  icon?: null | string;
  id: number;
  is_active: boolean;
  is_hidden: boolean;
  is_system: boolean;
  parent_id?: null | number;
  permission_code?: null | string;
  permissions?: Permission[];
  route_name?: null | string;
  route_path?: null | string;
  sort: number;
  title: string;
  type: string;
  view_key?: null | string;
}

interface MenuTreeNode extends MenuItem {
  children: MenuTreeNode[];
  key: string;
}

interface Permission {
  code: string;
  id: number;
  name: string;
  parent_id?: null | number;
}

interface PermissionTreeNode {
  children?: PermissionTreeNode[];
  key: Key;
  title: string;
}

interface MenuOrderItem {
  id: number;
  parent_id: null | number;
  sort: number;
}

interface MenuTreeController {
  closeAll: () => void;
  getStat: (node: MenuTreeNode) => MenuTreeStat;
  openAll: () => void;
}

interface MenuParentOption {
  label: string;
  searchText: string;
  value: number;
}

interface MenuTreeStat {
  children: MenuTreeStat[];
  data: MenuTreeNode;
  open: boolean;
}

const loading = ref(false);
const saving = ref(false);
const menus = ref<MenuItem[]>([]);
const draggableMenus = ref<MenuTreeNode[]>([]);
const permissions = ref<Permission[]>([]);
const checkedPermissionKeys = ref<Key[]>([]);
const expandedPermissionKeys = ref<Key[]>([]);
const editing = ref<MenuItem>();
const selectedCode = ref<string>();
const draggedCode = ref<string>();
const menuTreeRef = ref<MenuTreeController>();
const { hasAccessByCodes } = useAccess();
const canReorder = computed(() => hasAccessByCodes(['system.menu.update']));
const form = reactive({
  code: '',
  icon: '',
  is_active: true,
  is_hidden: false,
  parent_id: 0,
  permission_code: undefined as string | undefined,
  route_name: '',
  route_path: '',
  sort: 0,
  title: '',
  type: 'page',
  view_key: '',
});

function buildMenuTree(items: MenuItem[]) {
  const nodes = new Map<number, MenuTreeNode>();
  items.forEach((item) => nodes.set(item.id, { ...item, children: [], key: item.code }));
  const roots: MenuTreeNode[] = [];
  nodes.forEach((item) => {
    const parent = item.parent_id ? nodes.get(item.parent_id) : undefined;
    if (parent) parent.children.push(item);
    else roots.push(item);
  });
  return roots;
}

const treeMenus = computed<MenuTreeNode[]>(() => buildMenuTree(menus.value));

function descendantIds(id: number) {
  const descendants = new Set<number>();
  const visit = (parentId: number) => {
    menus.value.filter((item) => item.parent_id === parentId).forEach((item) => {
      descendants.add(item.id);
      visit(item.id);
    });
  };
  visit(id);
  return descendants;
}

const parentOptions = computed<MenuParentOption[]>(() => {
  const excluded = editing.value ? descendantIds(editing.value.id) : new Set<number>();
  if (editing.value) excluded.add(editing.value.id);
  const options: MenuParentOption[] = [{
    label: '顶级菜单',
    searchText: '顶级菜单',
    value: 0,
  }];
  const appendNodes = (nodes: MenuTreeNode[], ancestorLast: boolean[] = []) => {
    const visibleNodes = nodes.filter((node) => !excluded.has(node.id));
    visibleNodes.forEach((node, index) => {
      const isLast = index === visibleNodes.length - 1;
      const title = $t(node.title);
      const path = node.route_path || '';
      const indentation = ancestorLast
        .map((parentIsLast) => parentIsLast ? '　　' : '│　')
        .join('');
      options.push({
        label: `${indentation}${isLast ? '└─ ' : '├─ '}${title}`,
        searchText: `${title} ${path} ${node.code}`.toLowerCase(),
        value: node.id,
      });
      appendNodes(node.children, [...ancestorLast, isLast]);
    });
  };
  appendNodes(treeMenus.value);
  return options;
});

const formTitle = computed(() => editing.value ? `编辑：${$t(editing.value.title)}` : '新增菜单');

const permissionTree = computed<PermissionTreeNode[]>(() => {
  const groups = new Map<string, Permission[]>();
  permissions.value.forEach((permission) => {
    const segments = permission.code.split('.');
    const groupCode = segments.length > 2 ? segments.slice(0, -1).join('.') : permission.code;
    groups.set(groupCode, [...(groups.get(groupCode) ?? []), permission]);
  });
  return [...groups.entries()].map(([groupCode, items]) => {
    const owner = menus.value.find((menu) => {
      if (!menu.permission_code) return false;
      const segments = menu.permission_code.split('.');
      return segments.slice(0, -1).join('.') === groupCode;
    });
    return {
      children: items.map((permission) => ({
        key: permission.id,
        title: `${permission.name}（${permission.code}）`,
      })),
      key: `permission-group:${groupCode}`,
      title: owner ? $t(owner.title) : groupCode,
    };
  });
});

const allPermissionKeys = computed<Key[]>(() => permissions.value.map((permission) => permission.id));
const allPermissionGroupKeys = computed<Key[]>(() => permissionTree.value.map((group) => group.key));
const permissionsExpanded = computed({
  get: () => allPermissionGroupKeys.value.length > 0 && allPermissionGroupKeys.value.every((key) => expandedPermissionKeys.value.includes(key)),
  set: (expanded: boolean) => {
    expandedPermissionKeys.value = expanded ? [...allPermissionGroupKeys.value] : [];
  },
});
const allPermissionsChecked = computed({
  get: () => allPermissionKeys.value.length > 0 && allPermissionKeys.value.every((key) => checkedPermissionKeys.value.includes(key)),
  set: (checked: boolean) => {
    checkedPermissionKeys.value = checked ? [...allPermissionKeys.value] : [];
  },
});
const permissionIndeterminate = computed(() => {
  const checkedCount = allPermissionKeys.value.filter((key) => checkedPermissionKeys.value.includes(key)).length;
  return checkedCount > 0 && checkedCount < allPermissionKeys.value.length;
});

async function load(selectCode?: string) {
  loading.value = true;
  try {
    const [menuResult, permissionResult] = await Promise.all([
      getCollection<{ menus: MenuItem[] }>('/system/menus'),
      getResource('/system/permissions', { per_page: 100 }),
    ]);
    menus.value = menuResult.menus;
    draggableMenus.value = buildMenuTree(menus.value);
    permissions.value = permissionResult.data as Permission[];
    if (selectCode) {
      const selected = menus.value.find((item) => item.code === selectCode);
      if (selected) selectMenu(selected);
    }
  } finally {
    loading.value = false;
  }
}

function createEmptyForm(parentId = 0) {
  return {
    code: '', icon: '', is_active: true, is_hidden: false,
    parent_id: parentId, permission_code: undefined, route_name: '',
    route_path: '', sort: 0, title: '', type: 'page', view_key: '',
  };
}

function openCreate(parentId?: number) {
  editing.value = undefined;
  selectedCode.value = undefined;
  Object.assign(form, createEmptyForm(parentId));
  checkedPermissionKeys.value = [];
  expandedPermissionKeys.value = [...allPermissionGroupKeys.value];
}

function selectMenu(item: MenuItem) {
  editing.value = item;
  selectedCode.value = item.code;
  Object.assign(form, {
    code: item.code, icon: item.icon ?? '', is_active: item.is_active,
    is_hidden: item.is_hidden, parent_id: item.parent_id ?? 0,
    permission_code: item.permission_code ?? undefined,
    route_name: item.route_name ?? '', route_path: item.route_path ?? '',
    sort: item.sort, title: item.title, type: item.type, view_key: item.view_key ?? '',
  });
  checkedPermissionKeys.value = (item.permissions ?? []).map((permission) => permission.id);
  expandedPermissionKeys.value = [...allPermissionGroupKeys.value];
}

function resetEditor() {
  if (editing.value) selectMenu(editing.value);
  else Object.assign(form, createEmptyForm(form.parent_id));
}

function closeEditor() {
  openCreate();
}

function addChild(item: MenuTreeNode) {
  const tree = menuTreeRef.value;
  if (tree) tree.getStat(item).open = true;
  openCreate(item.id);
}

function codeFromRoutePath(path: string) {
  return path
    .split(/[?#]/, 1)[0]
    ?.replaceAll(/^\/+|\/+$/g, '')
    .replaceAll(/\/+/g, '.')
    .toLowerCase() ?? '';
}

function routeNameFromCode(code: string) {
  return code
    .split(/[^a-zA-Z0-9]+/)
    .filter(Boolean)
    .map((segment) => `${segment.charAt(0).toUpperCase()}${segment.slice(1)}`)
    .join('');
}

function syncGeneratedRouteFields() {
  if (editing.value?.is_system) return;
  const code = codeFromRoutePath(form.route_path);
  form.code = code;
  form.route_name = routeNameFromCode(code);
  form.view_key = form.type === 'page' ? code : '';
}

function handleRoutePathInput(value: string) {
  form.route_path = value;
  syncGeneratedRouteFields();
}

async function save() {
  syncGeneratedRouteFields();
  if (!form.code || !form.title || !form.type) {
    return void message.warning('请填写菜单标题、路由路径和类型');
  }
  saving.value = true;
  try {
    const permissionIds = checkedPermissionKeys.value
      .filter((key): key is number => typeof key === 'number');
    const selectedPermissions = permissions.value.filter((permission) => permissionIds.includes(permission.id));
    let permissionCode = form.permission_code;
    if (!selectedPermissions.some((permission) => permission.code === permissionCode)) {
      permissionCode = selectedPermissions.find((permission) => permission.code.endsWith('.view'))?.code ?? selectedPermissions[0]?.code;
    }
    const payload = {
      ...form,
      icon: form.icon || null,
      parent_id: form.parent_id || null,
      permission_code: permissionCode || null,
      permission_ids: permissionIds,
      route_name: form.route_name || null,
      route_path: form.route_path || null,
      view_key: form.view_key || null,
    };
    await (editing.value ? updateResource('/system/menus', editing.value.id, payload) : createResource('/system/menus', payload));
    message.success('菜单保存成功');
    await load();
    closeEditor();
  } finally {
    saving.value = false;
  }
}

async function remove(item: MenuItem) {
  await deleteResource('/system/menus', item.id);
  message.success('菜单已删除');
  closeEditor();
  await load();
}

function expandAll() {
  menuTreeRef.value?.openAll();
}

function collapseAll() {
  menuTreeRef.value?.closeAll();
}

function flattenOrder(nodes: MenuTreeNode[], parentId: null | number = null): MenuOrderItem[] {
  return nodes.flatMap((node, index) => [
    { id: node.id, parent_id: parentId, sort: (index + 1) * 10 },
    ...flattenOrder(node.children, node.id),
  ]);
}

function handleDragStart(stat: MenuTreeStat) {
  draggedCode.value = stat.data.code;
}

function menuNodeKey(stat: MenuTreeStat) {
  return stat.data.code;
}

async function handleTreeChange() {
  if (!canReorder.value || loading.value) return;
  loading.value = true;
  try {
    await reorderMenus(flattenOrder(draggableMenus.value));
    message.success('菜单层级和排序已保存');
  } catch {
    message.error('菜单拖动保存失败，已恢复原顺序');
  } finally {
    await load(draggedCode.value);
    draggedCode.value = undefined;
  }
}

onMounted(() => load());
</script>

<template>
  <Page :description="$t('system.menusDescription')" :title="$t('system.menus')">
    <div class="admin-menu-workspace min-h-[680px]">
      <Card :loading="loading" class="admin-menu-workspace__tree" title="菜单列表">
        <ListToolbar>
          <template #left>
            <ListRefreshButton :loading="loading" />
            <PermissionButton icon="lucide:chevrons-down-up" @click="expandAll">展开</PermissionButton>
            <PermissionButton icon="lucide:chevrons-up-down" @click="collapseAll">收起</PermissionButton>
          </template>
          <template #right>
            <PermissionButton icon="lucide:list-plus" permission="system.menu.create" type="primary" @click="openCreate()">新增菜单</PermissionButton>
          </template>
        </ListToolbar>

        <Draggable
          v-if="draggableMenus.length > 0"
          ref="menuTreeRef"
          v-model="draggableMenus"
          aria-label="菜单层级与排序"
          class="admin-menu-tree"
          :disable-drag="!canReorder || loading"
          :disable-drop="!canReorder || loading"
          drag-open
          :drag-open-delay="600"
          :indent="24"
          keep-placeholder
          :node-key="menuNodeKey"
          tree-line
          :trigger-class="['admin-menu-tree__drag-handle', 'admin-menu-tree__label']"
          @before-drag-start="handleDragStart"
          @change="handleTreeChange"
        >
          <template #default="{ node, stat }">
            <div class="admin-menu-tree__node" :class="[{ 'is-selected': selectedCode === node.code }]">
              <button
                v-if="stat.children.length > 0"
                :aria-label="stat.open ? '收起子菜单' : '展开子菜单'"
                class="admin-menu-tree__toggle"
                type="button"
                @click.stop="stat.open = !stat.open"
              >
                <IconifyIcon :class="{ 'is-open': stat.open }" icon="lucide:chevron-right" />
              </button>
              <span v-else class="admin-menu-tree__toggle-placeholder"></span>
              <span v-if="canReorder" aria-hidden="true" class="admin-menu-tree__drag-handle" title="按住拖动菜单">
                <IconifyIcon icon="lucide:grip-vertical" />
              </span>
              <button class="admin-menu-tree__label" type="button" @click.stop="selectMenu(node)">
                <span class="truncate">{{ $t(node.title) }}</span>
                <span class="admin-menu-tree__route">{{ node.route_path || node.code }}</span>
              </button>
              <Space size="small">
                <PermissionButton icon="lucide:pencil" icon-only permission="system.menu.update" tooltip="编辑菜单" type="text" @click.stop="selectMenu(node)" />
                <PermissionButton icon="lucide:plus" icon-only permission="system.menu.create" tooltip="新增子级" type="text" @click.stop="addChild(node)" />
                <Popconfirm v-if="!node.is_system" v-access:code="'system.menu.delete'" title="确定删除该菜单？" @confirm="remove(node)">
                  <PermissionButton danger icon="lucide:trash-2" icon-only permission="system.menu.delete" tooltip="删除菜单" type="text" @click.stop />
                </Popconfirm>
                <PermissionButton v-else danger disabled icon="lucide:lock-keyhole" icon-only tooltip="系统菜单不可删除" type="text" @click.stop />
              </Space>
            </div>
          </template>
          <template #placeholder>
            <div class="admin-menu-tree__drop-placeholder"></div>
          </template>
        </Draggable>
        <Empty v-else description="暂无菜单" />
      </Card>

      <Card class="admin-menu-editor admin-menu-workspace__editor" :title="formTitle">
        <Form :label-col="{ span: 4 }" :wrapper-col="{ span: 18 }">
          <FormItem label="父级菜单">
            <Select
              v-model:value="form.parent_id"
              :filter-option="true"
              option-filter-prop="searchText"
              :options="parentOptions"
              placeholder="请选择父级菜单"
              show-search
            />
          </FormItem>
          <FormItem label="菜单标题" required>
            <Input v-model:value="form.title" placeholder="请输入菜单标题或语言键">
              <template #prefix><IconifyIcon icon="lucide:pencil" /></template>
            </Input>
          </FormItem>
          <FormItem label="图标">
            <Input v-model:value="form.icon" placeholder="例如：lucide:menu">
              <template #prefix><IconifyIcon icon="lucide:shapes" /></template>
            </Input>
            <div class="admin-menu-editor__help">使用 Iconify 图标编码，例如 lucide:menu。</div>
          </FormItem>
          <FormItem label="路由路径">
            <Input :value="form.route_path" placeholder="例如：/content/articles" @update:value="handleRoutePathInput">
              <template #prefix><IconifyIcon icon="lucide:link" /></template>
            </Input>
          </FormItem>
          <FormItem label="菜单类型" required>
            <Select v-model:value="form.type" :options="[{ label: '目录', value: 'directory' }, { label: '页面', value: 'page' }, { label: '外部链接', value: 'external' }]" />
          </FormItem>
          <FormItem label="权限">
            <div class="admin-menu-permissions">
              <div class="admin-menu-permissions__actions">
                <Checkbox v-model:checked="allPermissionsChecked" :indeterminate="permissionIndeterminate">全选</Checkbox>
                <Checkbox v-model:checked="permissionsExpanded">展开</Checkbox>
              </div>
              <Tree v-model:checked-keys="checkedPermissionKeys" v-model:expanded-keys="expandedPermissionKeys" :tree-data="permissionTree" checkable />
            </div>
          </FormItem>
          <FormItem label="显示设置">
            <Space size="large">
              <span class="admin-menu-editor__switch"><Switch v-model:checked="form.is_active" /> 启用菜单</span>
              <span class="admin-menu-editor__switch"><Switch v-model:checked="form.is_hidden" /> 隐藏菜单</span>
            </Space>
          </FormItem>
          <FormItem label="排序">
            <InputNumber v-model:value="form.sort" class="admin-menu-editor__sort" />
          </FormItem>
          <div class="admin-menu-editor__footer">
            <PermissionButton icon="lucide:rotate-ccw" @click="resetEditor">重置</PermissionButton>
            <PermissionButton icon="lucide:save" :loading="saving" :permission="editing ? 'system.menu.update' : 'system.menu.create'" type="primary" @click="save">
              {{ $t('common.submit') }}
            </PermissionButton>
          </div>
        </Form>
      </Card>
    </div>
  </Page>
</template>
