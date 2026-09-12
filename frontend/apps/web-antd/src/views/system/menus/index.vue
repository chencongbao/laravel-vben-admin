<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Button, Card, Form, FormItem, Input, InputNumber, message, Modal, Popconfirm, Select, Space, Switch, Table, Tag } from 'ant-design-vue';
import { createResource, deleteResource, getCollection, getResource, updateResource } from '#/api/system';

interface MenuItem {
  code: string; icon?: null | string; id: number; is_active: boolean; is_hidden: boolean; is_system: boolean;
  parent_code?: null | string; permission_code?: null | string; route_name?: null | string;
  route_path?: null | string; sort: number; title: string; type: string; view_key?: null | string;
}
interface Permission { code: string; id: number; name: string }

const loading = ref(false); const saving = ref(false); const visible = ref(false); const editingId = ref<number>();
const menus = ref<MenuItem[]>([]); const permissions = ref<Permission[]>([]);
const form = reactive({ code: '', icon: '', is_active: true, is_hidden: false, parent_code: undefined as string | undefined, permission_code: undefined as string | undefined, route_name: '', route_path: '', sort: 0, title: '', type: 'page', view_key: '' });
const columns = [
  { dataIndex: 'title', title: '菜单标题' }, { dataIndex: 'code', title: '编码' },
  { dataIndex: 'type', title: '类型' }, { dataIndex: 'route_path', title: '路由' },
  { dataIndex: 'permission_code', title: '访问权限' }, { dataIndex: 'sort', title: '排序' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', title: '操作' },
];

const treeMenus = computed(() => {
  const nodes = new Map<string, MenuItem & { children: MenuItem[] }>();
  menus.value.forEach((item) => nodes.set(item.code, { ...item, children: [] }));
  const roots: Array<MenuItem & { children: MenuItem[] }> = [];
  nodes.forEach((item) => {
    const parent = item.parent_code ? nodes.get(item.parent_code) : undefined;
    if (parent) parent.children.push(item); else roots.push(item);
  });
  return roots;
});

async function load() {
  loading.value = true;
  try {
    const [menuResult, permissionResult] = await Promise.all([
      getCollection<{ menus: MenuItem[] }>('/system/menus'),
      getResource('/system/permissions', { per_page: 100 }),
    ]);
    menus.value = menuResult.menus; permissions.value = permissionResult.data as Permission[];
  } finally { loading.value = false; }
}

function open(record?: any) {
  editingId.value = record?.id;
  Object.assign(form, {
    code: record?.code ?? '', icon: record?.icon ?? '', is_active: record?.is_active ?? true,
    is_hidden: record?.is_hidden ?? false, parent_code: record?.parent_code ?? undefined,
    permission_code: record?.permission_code ?? undefined, route_name: record?.route_name ?? '',
    route_path: record?.route_path ?? '', sort: record?.sort ?? 0, title: record?.title ?? '',
    type: record?.type ?? 'page', view_key: record?.view_key ?? '',
  });
  visible.value = true;
}

async function save() {
  if (!form.code || !form.title || !form.type) return void message.warning('请填写菜单编码、标题和类型');
  saving.value = true;
  try {
    const payload = { ...form, parent_code: form.parent_code || null, permission_code: form.permission_code || null };
    if (editingId.value) await updateResource('/system/menus', editingId.value, payload);
    else await createResource('/system/menus', payload);
    visible.value = false; message.success('菜单保存成功'); await load();
  } finally { saving.value = false; }
}

async function remove(item: Record<string, any>) { await deleteResource('/system/menus', Number(item.id)); message.success('菜单已删除'); await load(); }
onMounted(load);
</script>

<template>
  <Page description="维护后端动态菜单。view_key 只能映射前端白名单组件，不能作为任意文件路径加载。" title="菜单管理">
    <Card>
      <div class="mb-4 flex justify-end"><Button v-access:code="'system.menu.create'" type="primary" @click="open()">新增菜单</Button></div>
      <Table :columns="columns" :data-source="treeMenus" :loading="loading" :pagination="false" row-key="id">
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'type'">{{ text }}</Tag>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <Space v-else-if="column.dataIndex === 'action'">
            <Button v-access:code="'system.menu.update'" size="small" type="link" @click="open(record)">编辑</Button>
            <Popconfirm v-access:code="'system.menu.delete'" title="确定删除该菜单？" @confirm="remove(record)"><Button danger size="small" type="link">删除</Button></Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑菜单' : '新增菜单'" width="720px" @ok="save">
      <Form layout="vertical">
        <div class="grid grid-cols-2 gap-x-4"><FormItem label="菜单编码" required><Input v-model:value="form.code" /></FormItem><FormItem label="标题" required><Input v-model:value="form.title" /></FormItem></div>
        <div class="grid grid-cols-2 gap-x-4"><FormItem label="上级菜单"><Select v-model:value="form.parent_code" allow-clear :options="menus.filter((item) => item.code !== form.code).map((item) => ({ label: `${item.title} (${item.code})`, value: item.code }))" /></FormItem><FormItem label="类型" required><Select v-model:value="form.type" :options="[{ label: '目录', value: 'directory' }, { label: '页面', value: 'page' }, { label: '外部链接', value: 'external' }]" /></FormItem></div>
        <div class="grid grid-cols-2 gap-x-4"><FormItem label="路由名称"><Input v-model:value="form.route_name" /></FormItem><FormItem label="路由路径"><Input v-model:value="form.route_path" /></FormItem></div>
        <div class="grid grid-cols-2 gap-x-4"><FormItem label="视图标识 view_key"><Input v-model:value="form.view_key" /></FormItem><FormItem label="访问权限"><Select v-model:value="form.permission_code" allow-clear show-search :options="permissions.map((item) => ({ label: `${item.name} (${item.code})`, value: item.code }))" /></FormItem></div>
        <div class="grid grid-cols-2 gap-x-4"><FormItem label="图标"><Input v-model:value="form.icon" /></FormItem><FormItem label="排序"><InputNumber v-model:value="form.sort" class="w-full" /></FormItem></div>
        <Space><FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem><FormItem label="隐藏"><Switch v-model:checked="form.is_hidden" /></FormItem></Space>
      </Form>
    </Modal>
  </Page>
</template>
