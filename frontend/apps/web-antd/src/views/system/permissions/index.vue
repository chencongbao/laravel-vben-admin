<script lang="ts" setup>
import type { Key } from 'ant-design-vue/es/_util/type';

import { computed, onMounted, reactive, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';

import { Draggable } from '@he-tree/vue';
import {
  Card,
  Empty,
  Form,
  FormItem,
  Input,
  message,
  Popconfirm,
  Select,
} from 'ant-design-vue';

import {
  createResource,
  deleteResource,
  getCollection,
  getResource,
  reorderPermissions,
  updateResource,
} from '#/api/system';
import AccessTreeSelector from '#/components/system/access-tree-selector.vue';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';

import '@he-tree/vue/style/default.css';

interface MenuItem {
  code: string;
  id: number;
  parent_id?: null | number;
  title: string;
}
interface MenuTreeNode {
  children: MenuTreeNode[];
  key: number;
  title: string;
}
interface Permission {
  code: string;
  id: number;
  is_system: boolean;
  menus?: MenuItem[];
  name: string;
  parent_id?: null | number;
  sort: number;
}
interface PermissionTreeNode extends Permission {
  children: PermissionTreeNode[];
  key: string;
}
interface PermissionTreeController {
  closeAll: () => void;
  getStat: (node: PermissionTreeNode) => PermissionTreeStat;
  openAll: () => void;
}
interface PermissionTreeStat {
  children: PermissionTreeStat[];
  data: PermissionTreeNode;
  open: boolean;
}
interface ParentOption {
  depth: number;
  isLast: boolean;
  label: string;
  searchText: string;
  value: number;
}

const loading = ref(false);
const saving = ref(false);
const permissions = ref<Permission[]>([]);
const draggablePermissions = ref<PermissionTreeNode[]>([]);
const menus = ref<MenuItem[]>([]);
const expandedMenuKeys = ref<Key[]>([]);
const editing = ref<Permission>();
const selectedCode = ref<string>();
const draggedCode = ref<string>();
const permissionTreeRef = ref<PermissionTreeController>();
const { hasAccessByCodes } = useAccess();
const canReorder = computed(() =>
  hasAccessByCodes(['system.permission.update']),
);
const form = reactive({
  code: '',
  menu_ids: [] as Key[],
  name: '',
  parent_id: 0,
  sort: 0,
});
const permissionCodePattern = /^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/;
function buildPermissionTree(items: Permission[]) {
  const nodes = new Map<number, PermissionTreeNode>();
  items.forEach((item) =>
    nodes.set(item.id, { ...item, children: [], key: item.code }),
  );
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
    permissions.value
      .filter((item) => item.parent_id === parentId)
      .forEach((item) => {
        descendants.add(item.id);
        visit(item.id);
      });
  };
  visit(id);
  return descendants;
}

const parentOptions = computed<ParentOption[]>(() => {
  const excluded = editing.value
    ? descendantIds(editing.value.id)
    : new Set<number>();
  if (editing.value) excluded.add(editing.value.id);
  const topLevel = $t('system.permissionList.topLevel');
  const options: ParentOption[] = [
    {
      depth: -1,
      isLast: true,
      label: topLevel,
      searchText: topLevel,
      value: 0,
    },
  ];
  const appendNodes = (nodes: PermissionTreeNode[], depth = 0) => {
    const visibleNodes = nodes.filter((node) => !excluded.has(node.id));
    visibleNodes.forEach((node, index) => {
      const label = permissionName(node);
      options.push({
        depth,
        isLast: index === visibleNodes.length - 1,
        label,
        searchText: `${label} ${node.name} ${node.code}`.toLowerCase(),
        value: node.id,
      });
      appendNodes(node.children, depth + 1);
    });
  };
  appendNodes(permissionTree.value);
  return options;
});

const menuTree = computed<MenuTreeNode[]>(() => {
  const nodes = new Map<number, MenuTreeNode>();
  menus.value.forEach((item) =>
    nodes.set(item.id, { children: [], key: item.id, title: $t(item.title) }),
  );
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

function expandAllMenus() {
  expandedMenuKeys.value = menus.value
    .filter((menu) => menus.value.some((item) => item.parent_id === menu.id))
    .map((menu) => menu.id);
}

const formTitle = computed(() =>
  editing.value
    ? $t('system.permissionForm.actions.edit', {
        name: permissionName(editing.value),
      })
    : $t('system.permissionForm.actions.create'),
);

function createEmptyForm(parentId = 0) {
  return {
    code: '',
    menu_ids: [] as Key[],
    name: '',
    parent_id: parentId,
    sort: 0,
  };
}

async function load(selectCode?: string) {
  loading.value = true;
  try {
    const [result, menuResult] = await Promise.all([
      getResource('/system/permissions', { per_page: 100 }),
      getCollection<{ menus: MenuItem[] }>('/system/menus'),
    ]);
    permissions.value = result.data as Permission[];
    draggablePermissions.value = buildPermissionTree(permissions.value);
    menus.value = menuResult.menus;
    expandAllMenus();
    if (selectCode) {
      const selected = permissions.value.find(
        (item) => item.code === selectCode,
      );
      if (selected) selectPermission(selected);
    }
  } finally {
    loading.value = false;
  }
}

function openCreate(parentId = 0) {
  editing.value = undefined;
  selectedCode.value = undefined;
  Object.assign(form, createEmptyForm(parentId));
  expandAllMenus();
}

function selectPermission(item: Permission) {
  editing.value = item;
  selectedCode.value = item.code;
  Object.assign(form, {
    code: item.code,
    menu_ids: (item.menus ?? []).map((menu) => menu.id),
    name: item.name,
    parent_id: item.parent_id ?? 0,
    sort: item.sort ?? 0,
  });
  expandAllMenus();
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
  if (!form.code || !form.name)
    return void message.warning($t('system.permissionForm.messages.required'));
  if (!permissionCodePattern.test(form.code))
    return void message.warning(
      $t('system.permissionForm.messages.codeInvalid'),
    );
  saving.value = true;
  try {
    const payload = {
      code: form.code,
      menu_ids: form.menu_ids.filter(
        (key): key is number => typeof key === 'number',
      ),
      name: form.name,
      parent_id: form.parent_id || null,
      sort: form.sort,
    };
    await (editing.value
      ? updateResource('/system/permissions', editing.value.id, payload)
      : createResource('/system/permissions', payload));
    message.success($t('system.permissionForm.messages.saved'));
    await load();
    openCreate();
  } finally {
    saving.value = false;
  }
}
async function remove(item: Permission) {
  await deleteResource('/system/permissions', item.id);
  message.success($t('system.permissionForm.messages.deleted'));
  await load();
  openCreate();
}
function expandAll() {
  permissionTreeRef.value?.openAll();
}
function collapseAll() {
  permissionTreeRef.value?.closeAll();
}
function flattenOrder(
  nodes: PermissionTreeNode[],
  parentId: null | number = null,
): Array<{ id: number; parent_id: null | number; sort: number }> {
  return nodes.flatMap((node, index) => [
    { id: node.id, parent_id: parentId, sort: (index + 1) * 10 },
    ...flattenOrder(node.children, node.id),
  ]);
}
function permissionNodeKey(stat: PermissionTreeStat) {
  return stat.data.code;
}
function handleDragStart(stat: PermissionTreeStat) {
  draggedCode.value = stat.data.code;
}
async function handleTreeChange() {
  if (!canReorder.value || loading.value) return;
  loading.value = true;
  try {
    await reorderPermissions(flattenOrder(draggablePermissions.value));
    message.success($t('system.permissionForm.messages.sortSaved'));
  } catch {
    message.error($t('system.permissionForm.messages.sortFailed'));
  } finally {
    await load(draggedCode.value);
    draggedCode.value = undefined;
  }
}
onMounted(() => load());
</script>

<template>
  <Page
    :description="$t('system.permissionsDescription')"
    :title="$t('system.permissions')"
  >
    <div class="admin-menu-workspace min-h-[680px]">
      <Card
        :loading="loading"
        class="admin-menu-workspace__tree"
        :title="$t('system.permissionList.title')"
      >
        <ListToolbar>
          <template #left
            ><ListRefreshButton :loading="loading" /><PermissionButton
              icon="lucide:chevrons-down-up"
              @click="expandAll"
              >{{ $t('common.actions.expand') }}</PermissionButton
            ><PermissionButton
              icon="lucide:chevrons-up-down"
              @click="collapseAll"
              >{{ $t('common.actions.collapse') }}</PermissionButton
            ></template
          >
          <template #right
            ><PermissionButton
              icon="lucide:key-round"
              permission="system.permission.create"
              type="primary"
              @click="openCreate()"
              >{{
                $t('system.permissionForm.actions.create')
              }}</PermissionButton
            ></template
          >
        </ListToolbar>
        <Draggable
          v-if="draggablePermissions.length > 0"
          ref="permissionTreeRef"
          v-model="draggablePermissions"
          :aria-label="$t('system.permissionList.treeLabel')"
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
          :trigger-class="[
            'admin-menu-tree__drag-handle',
            'admin-menu-tree__label',
          ]"
          @before-drag-start="handleDragStart"
          @change="handleTreeChange"
        >
          <template #default="{ node, stat }">
            <div
              class="admin-menu-tree__node"
              :class="[{ 'is-selected': selectedCode === node.code }]"
            >
              <button
                v-if="stat.children.length > 0"
                :aria-label="
                  stat.open
                    ? $t('system.permissionList.actions.collapseChild')
                    : $t('system.permissionList.actions.expandChild')
                "
                class="admin-menu-tree__toggle"
                type="button"
                @click.stop="stat.open = !stat.open"
              >
                <IconifyIcon
                  :class="{ 'is-open': stat.open }"
                  icon="lucide:chevron-right"
                />
              </button>
              <span v-else class="admin-menu-tree__toggle-placeholder"></span>
              <span
                v-if="canReorder"
                aria-hidden="true"
                class="admin-menu-tree__drag-handle"
                :title="$t('system.permissionList.actions.drag')"
                ><IconifyIcon icon="lucide:grip-vertical"
              /></span>
              <button
                class="admin-menu-tree__label"
                type="button"
                @click.stop="selectPermission(node)"
              >
                <span class="truncate">{{ permissionName(node) }}</span
                ><span class="admin-menu-tree__route">{{ node.code }}</span>
              </button>
              <div class="admin-menu-tree__actions">
                <PermissionButton
                  icon="lucide:pencil"
                  icon-only
                  permission="system.permission.update"
                  :tooltip="$t('system.permissionList.actions.edit')"
                  type="text"
                  @click.stop="selectPermission(node)"
                />
                <PermissionButton
                  icon="lucide:plus"
                  icon-only
                  permission="system.permission.create"
                  :tooltip="$t('system.permissionList.actions.addChild')"
                  type="text"
                  @click.stop="addChild(node)"
                />
                <Popconfirm
                  v-if="!node.is_system"
                  v-access:code="'system.permission.delete'"
                  :title="$t('system.permissionForm.prompts.delete')"
                  @confirm="remove(node)"
                  ><PermissionButton
                    danger
                    icon="lucide:trash-2"
                    icon-only
                    permission="system.permission.delete"
                    :tooltip="$t('system.common.actions.delete')"
                    type="text"
                    @click.stop
                /></Popconfirm>
                <PermissionButton
                  v-else
                  danger
                  disabled
                  icon="lucide:trash-2"
                  icon-only
                  :tooltip="$t('system.permissionList.systemDeleteDisabled')"
                  type="text"
                  @click.stop
                />
              </div>
            </div>
          </template>
          <template #placeholder
            ><div class="admin-menu-tree__drop-placeholder"></div
          ></template>
        </Draggable>
        <Empty v-else :description="$t('system.permissionList.empty')" />
      </Card>

      <Card
        class="admin-menu-editor admin-menu-workspace__editor"
        :title="formTitle"
      >
        <Form :label-col="{ span: 5 }" :wrapper-col="{ span: 17 }">
          <FormItem :label="$t('system.permissionForm.fields.parent')">
            <Select
              v-model:value="form.parent_id"
              :filter-option="true"
              option-filter-prop="searchText"
              :options="parentOptions"
              :placeholder="$t('system.permissionForm.placeholders.parent')"
              popup-class-name="admin-menu-parent-dropdown"
              show-search
            >
              <template #option="{ depth, isLast, label }"
                ><div class="admin-menu-parent-option">
                  <span
                    v-if="depth >= 0"
                    aria-hidden="true"
                    class="admin-menu-parent-option__branch"
                    :class="{ 'is-last': isLast }"
                    :style="{ marginInlineStart: `${depth * 28}px` }"
                  ></span
                  ><span>{{ label }}</span>
                </div></template
              >
            </Select>
          </FormItem>
          <FormItem :label="$t('system.permissionForm.fields.code')" required
            ><Input
              v-model:value="form.code"
              :disabled="Boolean(editing?.is_system)"
              :placeholder="$t('system.permissionForm.placeholders.code')"
          /></FormItem>
          <FormItem :label="$t('system.permissionForm.fields.name')" required
            ><Input
              v-model:value="form.name"
              :placeholder="$t('system.permissionForm.placeholders.name')"
          /></FormItem>
          <FormItem :label="$t('system.permissionForm.fields.menus')">
            <AccessTreeSelector
              v-model:checked-keys="form.menu_ids"
              v-model:expanded-keys="expandedMenuKeys"
              :tree-data="menuTree"
            />
          </FormItem>
          <div class="admin-menu-editor__footer">
            <PermissionButton icon="lucide:rotate-ccw" @click="resetEditor">{{
              $t('common.actions.reset')
            }}</PermissionButton
            ><PermissionButton
              icon="lucide:save"
              :loading="saving"
              :permission="
                editing
                  ? 'system.permission.update'
                  : 'system.permission.create'
              "
              type="primary"
              @click="save"
              >{{ $t('common.submit') }}</PermissionButton
            >
          </div>
        </Form>
      </Card>
    </div>
  </Page>
</template>
