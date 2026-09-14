<script lang="ts" setup>
import type { Key } from 'ant-design-vue/es/_util/type';
import type { TreeProps } from 'ant-design-vue';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';
import { $t } from '@vben/locales';

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
  Tree,
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
import ListToolbar from '#/components/system/list-toolbar.vue';

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

const loading = ref(false);
const saving = ref(false);
const menus = ref<MenuItem[]>([]);
const permissions = ref<Permission[]>([]);
const editing = ref<MenuItem>();
const selectedKeys = ref<Key[]>([]);
const expandedKeys = ref<Key[]>([]);
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

const treeMenus = computed<MenuTreeNode[]>(() => {
  const nodes = new Map<string, MenuTreeNode>();
  menus.value.forEach((item) => nodes.set(item.code, { ...item, children: [], key: item.code }));
  const roots: MenuTreeNode[] = [];
  nodes.forEach((item) => {
    const parent = item.parent_code ? nodes.get(item.parent_code) : undefined;
    if (parent) parent.children.push(item);
    else roots.push(item);
  });
  return roots;
});

const allMenuKeys = computed(() => menus.value.map((item) => item.code));

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
  const mapNode = (node: MenuTreeNode): Record<string, any> | undefined => {
    if (excluded.has(node.code)) return undefined;
    return {
      children: node.children.map(mapNode).filter((item): item is Record<string, any> => Boolean(item)),
      label: `${$t(node.title)}（${node.code}）`,
      value: node.code,
    };
  };
  return treeMenus.value.map(mapNode).filter((item): item is Record<string, any> => Boolean(item));
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
    permissions.value = permissionResult.data as Permission[];
    if (expandedKeys.value.length === 0) expandedKeys.value = [...allMenuKeys.value];
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
  selectedKeys.value = [];
  Object.assign(form, {
    code: '', icon: '', is_active: true, is_hidden: false,
    parent_code: parentCode, permission_code: undefined, route_name: '',
    route_path: '', sort: 0, title: '', type: 'page', view_key: '',
  });
}

function selectMenu(item: MenuItem) {
  editing.value = item;
  selectedKeys.value = [item.code];
  Object.assign(form, {
    code: item.code, icon: item.icon ?? '', is_active: item.is_active,
    is_hidden: item.is_hidden, parent_code: item.parent_code ?? undefined,
    permission_code: item.permission_code ?? undefined,
    route_name: item.route_name ?? '', route_path: item.route_path ?? '',
    sort: item.sort, title: item.title, type: item.type, view_key: item.view_key ?? '',
  });
}

function selectByKeys(keys: Key[]) {
  const item = menus.value.find((menu) => menu.code === String(keys[0] ?? ''));
  if (item) selectMenu(item);
}

function addChild(item: MenuItem) {
  resetForm(item.code);
  if (!expandedKeys.value.includes(item.code)) expandedKeys.value.push(item.code);
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
    if (editing.value) await updateResource('/system/menus', editing.value.id, payload);
    else await createResource('/system/menus', payload);
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
  expandedKeys.value = [...allMenuKeys.value];
}

function collapseAll() {
  expandedKeys.value = [];
}

function removeTreeNode(nodes: MenuTreeNode[], key: Key): MenuTreeNode | undefined {
  for (let index = 0; index < nodes.length; index += 1) {
    if (nodes[index]?.key === key) return nodes.splice(index, 1)[0];
    const removed = removeTreeNode(nodes[index]?.children ?? [], key);
    if (removed) return removed;
  }
  return undefined;
}

function findTreeNode(nodes: MenuTreeNode[], key: Key): MenuTreeNode | undefined {
  for (const node of nodes) {
    if (node.key === key) return node;
    const found = findTreeNode(node.children, key);
    if (found) return found;
  }
  return undefined;
}

function findSiblings(nodes: MenuTreeNode[], key: Key): MenuTreeNode[] | undefined {
  if (nodes.some((node) => node.key === key)) return nodes;
  for (const node of nodes) {
    const found = findSiblings(node.children, key);
    if (found) return found;
  }
  return undefined;
}

function flattenOrder(nodes: MenuTreeNode[], parentCode: null | string = null): MenuOrderItem[] {
  return nodes.flatMap((node, index) => [
    { id: node.id, parent_code: parentCode, sort: (index + 1) * 10 },
    ...flattenOrder(node.children, node.code),
  ]);
}

const handleDrop: TreeProps['onDrop'] = async (info) => {
  const roots = structuredClone(treeMenus.value);
  const dragged = removeTreeNode(roots, info.dragNode.key);
  if (!dragged) return;

  if (!info.dropToGap) {
    const target = findTreeNode(roots, info.node.key);
    if (!target) return;
    target.children.push(dragged);
  } else {
    const siblings = findSiblings(roots, info.node.key);
    if (!siblings) return;
    const targetIndex = siblings.findIndex((node) => node.key === info.node.key);
    const relativePosition = info.dropPosition - Number((info.node.pos ?? '0').split('-').at(-1));
    siblings.splice(relativePosition < 0 ? targetIndex : targetIndex + 1, 0, dragged);
  }

  loading.value = true;
  try {
    await reorderMenus(flattenOrder(roots));
    message.success('菜单层级和排序已保存');
  } catch {
    message.error('菜单拖动保存失败，已恢复原顺序');
  } finally {
    await load(String(dragged.key));
  }
};

onMounted(() => load());
</script>

<template>
  <Page description="左侧维护菜单层级，右侧新增或编辑当前菜单。" title="菜单管理">
    <div class="grid min-h-[680px] grid-cols-1 gap-4 xl:grid-cols-[minmax(360px,0.9fr)_minmax(560px,1.4fr)]">
      <Card :loading="loading" title="菜单树">
        <ListToolbar>
          <template #left>
            <Button size="small" @click="load()">刷新</Button>
            <Button size="small" @click="expandAll">展开</Button>
            <Button size="small" @click="collapseAll">收起</Button>
          </template>
          <template #right>
            <Button v-access:code="'system.menu.create'" size="small" type="primary" @click="resetForm()">新增根菜单</Button>
          </template>
        </ListToolbar>

        <Tree
          v-if="treeMenus.length"
          v-model:expanded-keys="expandedKeys"
          v-model:selected-keys="selectedKeys"
          :tree-data="treeMenus"
          block-node
          draggable
          show-line
          @drop="handleDrop"
          @select="selectByKeys"
        >
          <template #title="item">
            <div class="group flex min-w-0 flex-1 items-center justify-between gap-3 pr-1">
              <button class="min-w-0 flex-1 truncate text-left" type="button" @click.stop="selectMenu(item)">
                <span>{{ $t(item.title) }}</span>
                <span class="ml-2 text-xs text-gray-400">{{ item.route_path || item.code }}</span>
              </button>
              <Space size="small">
                <Button v-access:code="'system.menu.create'" size="small" type="link" @click.stop="addChild(item)">新增子级</Button>
                <Popconfirm v-if="!item.is_system" v-access:code="'system.menu.delete'" title="确定删除该菜单？" @confirm="remove(item)">
                  <Button danger size="small" type="link" @click.stop>删除</Button>
                </Popconfirm>
                <Button v-else danger disabled size="small" title="系统菜单不可删除" type="link" @click.stop>删除</Button>
              </Space>
            </div>
          </template>
        </Tree>
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
              <Input v-model:value="form.title" placeholder="支持语言键，例如：page.system.title" />
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
