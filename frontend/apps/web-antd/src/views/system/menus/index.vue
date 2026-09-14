<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';
import { $t } from '@vben/locales';

import { Draggable } from '@he-tree/vue';
import {
  Button,
  Card,
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
  TreeSelect,
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
  parent_code?: null | string;
  permission_code?: null | string;
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
}

interface MenuOrderItem {
  id: number;
  parent_code: null | string;
  sort: number;
}

interface MenuTreeController {
  closeAll: () => void;
  getStat: (node: MenuTreeNode) => MenuTreeStat;
  openAll: () => void;
}

interface MenuParentOption {
  children: MenuParentOption[];
  label: string;
  value: string;
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
  parent_code: undefined as string | undefined,
  permission_code: undefined as string | undefined,
  route_name: '',
  route_path: '',
  sort: 0,
  title: '',
  type: 'page',
  view_key: '',
});

function buildMenuTree(items: MenuItem[]) {
  const nodes = new Map<string, MenuTreeNode>();
  items.forEach((item) => nodes.set(item.code, { ...item, children: [], key: item.code }));
  const roots: MenuTreeNode[] = [];
  nodes.forEach((item) => {
    const parent = item.parent_code ? nodes.get(item.parent_code) : undefined;
    if (parent) parent.children.push(item);
    else roots.push(item);
  });
  return roots;
}

const treeMenus = computed<MenuTreeNode[]>(() => buildMenuTree(menus.value));

function descendantCodes(code: string) {
  const descendants = new Set<string>();
  const visit = (parentCode: string) => {
    menus.value.filter((item) => item.parent_code === parentCode).forEach((item) => {
      descendants.add(item.code);
      visit(item.code);
    });
  };
  visit(code);
  return descendants;
}

const parentTree = computed(() => {
  const excluded = editing.value ? descendantCodes(editing.value.code) : new Set<string>();
  if (editing.value) excluded.add(editing.value.code);
  const mapNode = (node: MenuTreeNode): MenuParentOption | undefined => {
    if (excluded.has(node.code)) return undefined;
    return {
      children: node.children.flatMap((child) => {
        const item = mapNode(child);
        return item ? [item] : [];
      }),
      label: `${$t(node.title)}（${node.code}）`,
      value: node.code,
    };
  };
  return treeMenus.value.flatMap((node) => {
    const item = mapNode(node);
    return item ? [item] : [];
  });
});

const formTitle = computed(() => editing.value ? `编辑：${$t(editing.value.title)}` : '新增菜单');

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

function resetForm(parentCode?: string) {
  editing.value = undefined;
  selectedCode.value = undefined;
  Object.assign(form, {
    code: '', icon: '', is_active: true, is_hidden: false,
    parent_code: parentCode, permission_code: undefined, route_name: '',
    route_path: '', sort: 0, title: '', type: 'page', view_key: '',
  });
}

function selectMenu(item: MenuItem) {
  editing.value = item;
  selectedCode.value = item.code;
  Object.assign(form, {
    code: item.code, icon: item.icon ?? '', is_active: item.is_active,
    is_hidden: item.is_hidden, parent_code: item.parent_code ?? undefined,
    permission_code: item.permission_code ?? undefined,
    route_name: item.route_name ?? '', route_path: item.route_path ?? '',
    sort: item.sort, title: item.title, type: item.type, view_key: item.view_key ?? '',
  });
}

function addChild(item: MenuTreeNode) {
  resetForm(item.code);
  const tree = menuTreeRef.value;
  if (tree) tree.getStat(item).open = true;
}

async function save() {
  if (!form.code || !form.title || !form.type) {
    return void message.warning('请填写菜单编码、标题和类型');
  }
  saving.value = true;
  try {
    const payload = {
      ...form,
      icon: form.icon || null,
      parent_code: form.parent_code || null,
      permission_code: form.permission_code || null,
      route_name: form.route_name || null,
      route_path: form.route_path || null,
      view_key: form.view_key || null,
    };
    await (editing.value ? updateResource('/system/menus', editing.value.id, payload) : createResource('/system/menus', payload));
    message.success('菜单保存成功');
    await load(form.code);
  } finally {
    saving.value = false;
  }
}

async function remove(item: MenuItem) {
  await deleteResource('/system/menus', item.id);
  message.success('菜单已删除');
  resetForm(item.parent_code ?? undefined);
  await load();
}

function expandAll() {
  menuTreeRef.value?.openAll();
}

function collapseAll() {
  menuTreeRef.value?.closeAll();
}

function flattenOrder(nodes: MenuTreeNode[], parentCode: null | string = null): MenuOrderItem[] {
  return nodes.flatMap((node, index) => [
    { id: node.id, parent_code: parentCode, sort: (index + 1) * 10 },
    ...flattenOrder(node.children, node.code),
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
    <div class="grid min-h-[680px] grid-cols-1 gap-4 xl:grid-cols-[minmax(360px,0.9fr)_minmax(560px,1.4fr)]">
      <Card :loading="loading" title="菜单树">
        <ListToolbar>
          <template #left>
            <ListRefreshButton :loading="loading" />
            <PermissionButton icon="lucide:chevrons-down-up" @click="expandAll">展开</PermissionButton>
            <PermissionButton icon="lucide:chevrons-up-down" @click="collapseAll">收起</PermissionButton>
          </template>
          <template #right>
            <PermissionButton icon="lucide:list-plus" permission="system.menu.create" type="primary" @click="resetForm()">新增根菜单</PermissionButton>
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

      <Card :title="formTitle">
        <Form layout="vertical">
          <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
            <FormItem class="md:col-span-2" label="父级菜单">
              <TreeSelect v-model:value="form.parent_code" :tree-data="parentTree" allow-clear placeholder="不选择则为根菜单" tree-default-expand-all />
            </FormItem>
            <FormItem label="菜单编码" required>
              <Input v-model:value="form.code" :disabled="editing?.is_system" placeholder="例如：content.articles" />
            </FormItem>
            <FormItem label="菜单标题" required>
              <Input v-model:value="form.title" placeholder="支持语言键，例如：system.title" />
            </FormItem>
            <FormItem label="菜单类型" required>
              <Select v-model:value="form.type" :options="[{ label: '目录', value: 'directory' }, { label: '页面', value: 'page' }, { label: '外部链接', value: 'external' }]" />
            </FormItem>
            <FormItem label="图标">
              <Input v-model:value="form.icon" placeholder="例如：lucide:menu" />
            </FormItem>
            <FormItem label="路由名称">
              <Input v-model:value="form.route_name" placeholder="前端路由唯一名称" />
            </FormItem>
            <FormItem label="路由路径">
              <Input v-model:value="form.route_path" placeholder="例如：/content/articles" />
            </FormItem>
            <FormItem v-if="form.type === 'page'" label="视图标识 view_key">
              <Input v-model:value="form.view_key" placeholder="必须由前端组件白名单注册" />
            </FormItem>
            <FormItem label="访问权限">
              <Select v-model:value="form.permission_code" :options="permissions.map((item) => ({ label: `${item.name}（${item.code}）`, value: item.code }))" allow-clear show-search />
            </FormItem>
            <FormItem label="排序">
              <InputNumber v-model:value="form.sort" class="w-full" />
            </FormItem>
            <FormItem label="启用状态">
              <Switch v-model:checked="form.is_active" />
            </FormItem>
            <FormItem label="菜单隐藏">
              <Switch v-model:checked="form.is_hidden" />
            </FormItem>
          </div>
          <div class="flex justify-end gap-3 border-t pt-4">
            <Button @click="resetForm(form.parent_code)">重置</Button>
            <Button v-access:code="editing ? 'system.menu.update' : 'system.menu.create'" :loading="saving" type="primary" @click="save">保存</Button>
          </div>
        </Form>
      </Card>
    </div>
  </Page>
</template>
