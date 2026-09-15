<script lang="ts" setup>
import type { Key } from 'ant-design-vue/es/_util/type';

import { computed, onMounted, reactive, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';

import { Draggable } from '@he-tree/vue';
import { Card, Empty, Form, FormItem, Input, InputNumber, message, Popconfirm, Select, Space, Switch, Textarea, Tree } from 'ant-design-vue';

import { createResource, deleteResource, getCollection, getResource, reorderPermissions, updateResource } from '#/api/system';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';

import '@he-tree/vue/style/default.css';

interface MenuItem { code: string; id: number; parent_id?: null | number; title: string }
interface MenuTreeNode { children: MenuTreeNode[]; key: number; title: string }
interface Permission { code: string; description?: null | string; http_methods?: null | string[]; http_paths?: null | string[]; id: number; is_active: boolean; is_sensitive: boolean; is_system: boolean; menus?: MenuItem[]; name: string; parent_id?: null | number; sort: number }
interface PermissionTreeNode extends Permission { children: PermissionTreeNode[]; key: string }
interface PermissionTreeController { closeAll: () => void; getStat: (node: PermissionTreeNode) => PermissionTreeStat; openAll: () => void }
interface PermissionTreeStat { children: PermissionTreeStat[]; data: PermissionTreeNode; open: boolean }
interface ParentOption { depth: number; isLast: boolean; label: string; searchText: string; value: number }

const loading = ref(false); const saving = ref(false);
const permissions = ref<Permission[]>([]); const draggablePermissions = ref<PermissionTreeNode[]>([]); const menus = ref<MenuItem[]>([]); const httpPathOptions = ref<Array<{ label: string; value: string }>>([]);
const editing = ref<Permission>(); const selectedCode = ref<string>(); const draggedCode = ref<string>();
const permissionTreeRef = ref<PermissionTreeController>();
const { hasAccessByCodes } = useAccess();
const canReorder = computed(() => hasAccessByCodes(['system.permission.update']));
const form = reactive({ code: '', description: '', http_methods: [] as string[], http_paths: [] as string[], is_active: true, is_sensitive: false, menu_ids: [] as Key[], name: '', parent_id: 0, sort: 0 });
function buildPermissionTree(items: Permission[]) {
  const nodes = new Map<number, PermissionTreeNode>();
  items.forEach((item) => nodes.set(item.id, { ...item, children: [], key: item.code }));
  const roots: PermissionTreeNode[] = [];
  nodes.forEach((item) => {
    const parent = item.parent_id ? nodes.get(item.parent_id) : undefined;
    if (parent) parent.children.push(item);
    else roots.push(item);
  });
  return roots;
}

const permissionTree = computed(() => buildPermissionTree(permissions.value));

function permissionName(item: Pick<Permission, 'code' | 'name'>) {
  const systemNameKey = `system.permissionNames.${item.code}`;
  const systemName = $t(systemNameKey);
  return systemName === systemNameKey ? $t(item.name) : systemName;
}

function descendantIds(id: number) {
  const descendants = new Set<number>();
  const visit = (parentId: number) => {
    permissions.value.filter((item) => item.parent_id === parentId).forEach((item) => {
      descendants.add(item.id);
      visit(item.id);
    });
  };
  visit(id);
  return descendants;
}

const parentOptions = computed<ParentOption[]>(() => {
  const excluded = editing.value ? descendantIds(editing.value.id) : new Set<number>();
  if (editing.value) excluded.add(editing.value.id);
  const options: ParentOption[] = [{ depth: -1, isLast: true, label: '顶级权限', searchText: '顶级权限', value: 0 }];
  const appendNodes = (nodes: PermissionTreeNode[], depth = 0) => {
    const visibleNodes = nodes.filter((node) => !excluded.has(node.id));
    visibleNodes.forEach((node, index) => {
      const label = permissionName(node);
      options.push({ depth, isLast: index === visibleNodes.length - 1, label, searchText: `${label} ${node.name} ${node.code}`.toLowerCase(), value: node.id });
      appendNodes(node.children, depth + 1);
    });
  };
  appendNodes(permissionTree.value);
  return options;
});

const menuTree = computed<MenuTreeNode[]>(() => {
  const nodes = new Map<number, MenuTreeNode>();
  menus.value.forEach((item) => nodes.set(item.id, { children: [], key: item.id, title: `${$t(item.title)}（${item.code}）` }));
  const roots: MenuTreeNode[] = [];
  menus.value.forEach((item) => {
    const node = nodes.get(item.id);
    if (!node) return;
    const parent = item.parent_id ? nodes.get(item.parent_id) : undefined;
    if (parent) parent.children.push(node);
    else roots.push(node);
  });
  return roots;
});

const formTitle = computed(() => editing.value ? `编辑：${permissionName(editing.value)}` : '新增权限');

function createEmptyForm(parentId = 0) {
  return { code: '', description: '', http_methods: [] as string[], http_paths: [] as string[], is_active: true, is_sensitive: false, menu_ids: [] as Key[], name: '', parent_id: parentId, sort: 0 };
}

async function load(selectCode?: string) {
  loading.value = true;
  try {
    const [result, menuResult, httpPathResult] = await Promise.all([
      getResource('/system/permissions', { per_page: 100 }),
      getCollection<{ menus: MenuItem[] }>('/system/menus'),
      getCollection<{ paths: string[] }>('/system/permissions/http-paths'),
    ]);
    permissions.value = result.data as Permission[];
    draggablePermissions.value = buildPermissionTree(permissions.value);
    menus.value = menuResult.menus;
    httpPathOptions.value = httpPathResult.paths.map((path) => ({ label: path, value: path }));
    if (selectCode) {
      const selected = permissions.value.find((item) => item.code === selectCode);
      if (selected) selectPermission(selected);
    }
  } finally { loading.value = false; }
}

function openCreate(parentId = 0) {
  editing.value = undefined;
  selectedCode.value = undefined;
  Object.assign(form, createEmptyForm(parentId));
}

function selectPermission(item: Permission) {
  editing.value = item;
  selectedCode.value = item.code;
  Object.assign(form, { code: item.code, description: item.description ?? '', http_methods: item.http_methods ?? [], http_paths: item.http_paths ?? [], is_active: item.is_active, is_sensitive: item.is_sensitive, menu_ids: (item.menus ?? []).map((menu) => menu.id), name: item.name, parent_id: item.parent_id ?? 0, sort: item.sort ?? 0 });
}

function resetEditor() {
  if (editing.value) selectPermission(editing.value);
  else Object.assign(form, createEmptyForm(form.parent_id));
}

function addChild(item: PermissionTreeNode) {
  const tree = permissionTreeRef.value;
  if (tree) tree.getStat(item).open = true;
  openCreate(item.id);
}

async function save() {
  if (!form.code || !form.name) return void message.warning('请填写权限编码和名称');
  saving.value = true;
  try {
    const payload = { code: form.code, description: form.description || null, http_methods: form.http_methods.length > 0 ? form.http_methods : null, http_paths: form.http_paths.length > 0 ? form.http_paths : null, is_active: form.is_active, is_sensitive: form.is_sensitive, menu_ids: form.menu_ids.filter((key): key is number => typeof key === 'number'), name: form.name, parent_id: form.parent_id || null, sort: form.sort };
    await (editing.value ? updateResource('/system/permissions', editing.value.id, payload) : createResource('/system/permissions', payload));
    message.success('权限保存成功'); await load(); openCreate();
  } finally { saving.value = false; }
}
async function remove(item: Permission) { await deleteResource('/system/permissions', item.id); message.success('权限已删除'); await load(); openCreate(); }
function expandAll() { permissionTreeRef.value?.openAll(); }
function collapseAll() { permissionTreeRef.value?.closeAll(); }
function flattenOrder(nodes: PermissionTreeNode[], parentId: null | number = null): Array<{ id: number; parent_id: null | number; sort: number }> {
  return nodes.flatMap((node, index) => [{ id: node.id, parent_id: parentId, sort: (index + 1) * 10 }, ...flattenOrder(node.children, node.id)]);
}
function permissionNodeKey(stat: PermissionTreeStat) { return stat.data.code; }
function handleDragStart(stat: PermissionTreeStat) { draggedCode.value = stat.data.code; }
async function handleTreeChange() {
  if (!canReorder.value || loading.value) return;
  loading.value = true;
  try {
    await reorderPermissions(flattenOrder(draggablePermissions.value));
    message.success('权限层级和排序已保存');
  } catch {
    message.error('权限拖动保存失败，已恢复原顺序');
  } finally {
    await load(draggedCode.value);
    draggedCode.value = undefined;
  }
}
onMounted(() => load());
</script>

<template>
  <Page :description="$t('system.permissionsDescription')" :title="$t('system.permissions')">
    <div class="admin-menu-workspace min-h-[680px]">
      <Card :loading="loading" class="admin-menu-workspace__tree" title="权限列表">
        <ListToolbar>
          <template #left><ListRefreshButton :loading="loading" /><PermissionButton icon="lucide:chevrons-down-up" @click="expandAll">展开</PermissionButton><PermissionButton icon="lucide:chevrons-up-down" @click="collapseAll">收起</PermissionButton></template>
          <template #right><PermissionButton icon="lucide:key-round" permission="system.permission.create" type="primary" @click="openCreate()">新增权限</PermissionButton></template>
        </ListToolbar>
        <Draggable
          v-if="draggablePermissions.length > 0"
          ref="permissionTreeRef"
          v-model="draggablePermissions"
          aria-label="权限层级与排序"
          class="admin-menu-tree"
          :disable-drag="!canReorder || loading"
          :disable-drop="!canReorder || loading"
          drag-open
          :drag-open-delay="600"
          :indent="24"
          keep-placeholder
          :node-key="permissionNodeKey"
          tree-line
          :tree-line-offset="18"
          :trigger-class="['admin-menu-tree__drag-handle', 'admin-menu-tree__label']"
          @before-drag-start="handleDragStart"
          @change="handleTreeChange"
        >
          <template #default="{ node, stat }">
            <div class="admin-menu-tree__node" :class="[{ 'is-selected': selectedCode === node.code }]">
              <button v-if="stat.children.length > 0" :aria-label="stat.open ? '收起子权限' : '展开子权限'" class="admin-menu-tree__toggle" type="button" @click.stop="stat.open = !stat.open"><IconifyIcon :class="{ 'is-open': stat.open }" icon="lucide:chevron-right" /></button>
              <span v-else class="admin-menu-tree__toggle-placeholder"></span>
              <span v-if="canReorder" aria-hidden="true" class="admin-menu-tree__drag-handle" title="按住拖动权限"><IconifyIcon icon="lucide:grip-vertical" /></span>
              <button class="admin-menu-tree__label" type="button" @click.stop="selectPermission(node)"><span class="truncate">{{ permissionName(node) }}</span><span class="admin-menu-tree__route">{{ node.code }}</span></button>
              <Space size="small">
                <PermissionButton icon="lucide:pencil" icon-only permission="system.permission.update" tooltip="编辑权限" type="text" @click.stop="selectPermission(node)" />
                <PermissionButton icon="lucide:plus" icon-only permission="system.permission.create" tooltip="新增子级" type="text" @click.stop="addChild(node)" />
                <Popconfirm v-if="!node.is_system" v-access:code="'system.permission.delete'" title="确定删除该权限？" @confirm="remove(node)"><PermissionButton danger icon="lucide:trash-2" icon-only permission="system.permission.delete" tooltip="删除权限" type="text" @click.stop /></Popconfirm>
                <PermissionButton v-else danger disabled icon="lucide:trash-2" icon-only tooltip="系统权限不可删除" type="text" @click.stop />
              </Space>
            </div>
          </template>
          <template #placeholder><div class="admin-menu-tree__drop-placeholder"></div></template>
        </Draggable>
        <Empty v-else description="暂无权限" />
      </Card>

      <Card class="admin-menu-editor admin-menu-workspace__editor" :title="formTitle">
        <Form :label-col="{ span: 5 }" :wrapper-col="{ span: 17 }">
          <FormItem label="父级权限">
            <Select v-model:value="form.parent_id" :filter-option="true" option-filter-prop="searchText" :options="parentOptions" placeholder="请选择父级权限" popup-class-name="admin-menu-parent-dropdown" show-search>
              <template #option="{ depth, isLast, label }"><div class="admin-menu-parent-option"><span v-if="depth >= 0" aria-hidden="true" class="admin-menu-parent-option__branch" :class="{ 'is-last': isLast }" :style="{ marginInlineStart: `${depth * 28}px` }"></span><span>{{ label }}</span></div></template>
            </Select>
          </FormItem>
          <FormItem label="权限编码" required><Input v-model:value="form.code" :disabled="Boolean(editing?.is_system)" placeholder="例如 match.publish" /></FormItem>
          <FormItem label="权限名称" required><Input v-model:value="form.name" placeholder="请输入权限名称或语言键" /></FormItem>
          <FormItem label="权限说明"><Textarea v-model:value="form.description" :maxlength="500" :rows="3" show-count /></FormItem>
          <FormItem label="请求方法"><Select v-model:value="form.http_methods" mode="multiple" :options="['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'].map((value) => ({ label: value, value }))" placeholder="可选，仅用于接口覆盖审计" /></FormItem>
          <FormItem label="HTTP路径"><Select v-model:value="form.http_paths" :options="httpPathOptions" mode="multiple" option-filter-prop="label" placeholder="请选择或搜索 HTTP 路径" show-search /><div class="admin-menu-editor__help">请求方法和路径不替代服务端权限编码校验。</div></FormItem>
          <FormItem label="关联菜单">
            <Tree v-model:checked-keys="form.menu_ids" checkable default-expand-all :show-line="{ showLeafIcon: false }" :tree-data="menuTree"><template #switcherIcon="{ expanded }"><span class="admin-menu-permissions__switcher" aria-hidden="true">{{ expanded ? '−' : '+' }}</span></template></Tree>
          </FormItem>
          <FormItem label="排序"><InputNumber v-model:value="form.sort" /></FormItem>
          <FormItem label="权限设置"><Space size="large"><Switch v-model:checked="form.is_active" />启用权限 <Switch v-model:checked="form.is_sensitive" />敏感权限</Space></FormItem>
          <div class="admin-menu-editor__footer"><PermissionButton icon="lucide:rotate-ccw" @click="resetEditor">重置</PermissionButton><PermissionButton icon="lucide:save" :loading="saving" :permission="editing ? 'system.permission.update' : 'system.permission.create'" type="primary" @click="save">{{ $t('common.submit') }}</PermissionButton></div>
        </Form>
      </Card>
    </div>
  </Page>
</template>
