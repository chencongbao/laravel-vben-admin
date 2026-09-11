<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Button, Card, Checkbox, Divider, Form, FormItem, Input, message, Modal, Popconfirm, Space, Switch, Table, Tag } from 'ant-design-vue';
import { createResource, deleteResource, getCollection, getResource, getResourceDetail, updateResource, updateRoleAccess } from '#/api/system';

interface Item { code: string; id: number; name?: string; title?: string }
interface Role { code: string; id: number; is_active: boolean; is_system: boolean; menus_count: number; name: string; permissions_count: number }

const loading = ref(false); const visible = ref(false); const accessVisible = ref(false); const saving = ref(false);
const editingId = ref<number>(); const accessRole = ref<Role>();
const roles = ref<Role[]>([]); const permissions = ref<Item[]>([]); const menus = ref<Item[]>([]);
const selectedPermissions = ref<number[]>([]); const selectedMenus = ref<number[]>([]);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const form = reactive({ code: '', is_active: true, name: '' });
const columns = [
  { dataIndex: 'code', title: '角色编码' }, { dataIndex: 'name', title: '角色名称' },
  { dataIndex: 'permissions_count', title: '权限数' }, { dataIndex: 'menus_count', title: '菜单数' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', title: '操作' },
];

async function load() {
  loading.value = true;
  try {
    const result = await getResource('/system/roles', { page: pagination.current, per_page: pagination.pageSize });
    roles.value = result.data as Role[]; pagination.total = result.total;
  } finally { loading.value = false; }
}

function openEdit(role?: Role) {
  editingId.value = role?.id;
  Object.assign(form, { code: role?.code ?? '', is_active: role?.is_active ?? true, name: role?.name ?? '' });
  visible.value = true;
}

async function saveRole() {
  if (!form.code || !form.name) return void message.warning('请填写角色编码和名称');
  saving.value = true;
  try {
    if (editingId.value) await updateResource('/system/roles', editingId.value, form);
    else await createResource('/system/roles', form);
    visible.value = false; message.success('角色保存成功'); await load();
  } finally { saving.value = false; }
}

async function openAccess(role: Role) {
  loading.value = true;
  try {
    const [detail, permissionResult, menuResult] = await Promise.all([
      getResourceDetail<{ role: Role & { menus: Item[]; permissions: Item[] } }>('/system/roles', role.id),
      getResource('/system/permissions', { per_page: 100 }),
      getCollection<{ menus: Item[] }>('/system/menus'),
    ]);
    accessRole.value = role; permissions.value = permissionResult.data as Item[]; menus.value = menuResult.menus;
    selectedPermissions.value = detail.role.permissions.map((item) => item.id);
    selectedMenus.value = detail.role.menus.map((item) => item.id); accessVisible.value = true;
  } finally { loading.value = false; }
}

async function saveAccess() {
  if (!accessRole.value) return;
  saving.value = true;
  try {
    await updateRoleAccess(accessRole.value.id, { menu_ids: selectedMenus.value, permission_ids: selectedPermissions.value });
    accessVisible.value = false; message.success('角色权限已更新'); await load();
  } finally { saving.value = false; }
}

async function remove(role: Role) { await deleteResource('/system/roles', role.id); message.success('角色已删除'); await load(); }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load(); }
onMounted(load);
</script>

<template>
  <Page description="为角色分配权限码与可见菜单；所有系统角色均由后端保护。" title="角色管理">
    <Card>
      <div class="mb-4 flex justify-end"><Button type="primary" @click="openEdit()">新增角色</Button></div>
      <Table :columns="columns" :data-source="roles" :loading="loading" :pagination="pagination" row-key="id" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <Space v-else-if="column.dataIndex === 'action'">
            <Button size="small" type="link" @click="openAccess(record)">授权</Button>
            <Button size="small" type="link" @click="openEdit(record)">编辑</Button>
            <Popconfirm title="确定删除该角色？" @confirm="remove(record)"><Button danger size="small" type="link">删除</Button></Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑角色' : '新增角色'" @ok="saveRole">
      <Form layout="vertical"><FormItem label="角色编码" required><Input v-model:value="form.code" /></FormItem><FormItem label="角色名称" required><Input v-model:value="form.name" /></FormItem><FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem></Form>
    </Modal>
    <Modal v-model:open="accessVisible" :confirm-loading="saving" title="角色授权" width="760px" @ok="saveAccess">
      <Divider orientation="left">权限</Divider>
      <Checkbox.Group v-model:value="selectedPermissions" class="grid grid-cols-2 gap-3"><Checkbox v-for="item in permissions" :key="item.id" :value="item.id">{{ item.name }}（{{ item.code }}）</Checkbox></Checkbox.Group>
      <Divider orientation="left">菜单</Divider>
      <Checkbox.Group v-model:value="selectedMenus" class="grid grid-cols-2 gap-3"><Checkbox v-for="item in menus" :key="item.id" :value="item.id">{{ item.title }}（{{ item.code }}）</Checkbox></Checkbox.Group>
    </Modal>
  </Page>
</template>
