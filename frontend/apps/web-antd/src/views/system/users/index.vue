<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Button, Card, Form, FormItem, Input, message, Modal, Select, Space, Switch, Table, Tag } from 'ant-design-vue';
import { createResource, getResource, updateResource } from '#/api/system';

interface Role { code: string; id: number; name: string }
interface AdminUser { id: number; is_active: boolean; name: string; roles: Role[]; username: string }

const loading = ref(false);
const saving = ref(false);
const visible = ref(false);
const editingId = ref<number>();
const users = ref<AdminUser[]>([]);
const roles = ref<Role[]>([]);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const form = reactive({ is_active: true, name: '', password: '', role_ids: [] as number[], username: '' });
const columns = [
  { dataIndex: 'id', title: 'ID' }, { dataIndex: 'username', title: '用户名' },
  { dataIndex: 'name', title: '姓名' }, { dataIndex: 'roles', title: '角色' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', title: '操作' },
];

async function load() {
  loading.value = true;
  try {
    const [userResult, roleResult] = await Promise.all([
      getResource('/system/users', { page: pagination.current, per_page: pagination.pageSize }),
      getResource('/system/roles', { per_page: 100 }),
    ]);
    users.value = userResult.data as AdminUser[];
    roles.value = roleResult.data as Role[];
    pagination.total = userResult.total;
  } finally { loading.value = false; }
}

function open(record?: AdminUser) {
  editingId.value = record?.id;
  Object.assign(form, {
    is_active: record?.is_active ?? true, name: record?.name ?? '', password: '',
    role_ids: record?.roles.map((role) => role.id) ?? [], username: record?.username ?? '',
  });
  visible.value = true;
}

async function save() {
  if (!form.username || !form.name || (!editingId.value && !form.password)) {
    message.warning('请完整填写用户名、姓名和密码'); return;
  }
  saving.value = true;
  try {
    const payload: Record<string, any> = { ...form };
    if (!payload.password) delete payload.password;
    if (editingId.value) await updateResource('/system/users', editingId.value, payload);
    else await createResource('/system/users', payload);
    visible.value = false; message.success('用户保存成功'); await load();
  } finally { saving.value = false; }
}

function changePage(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load();
}
onMounted(load);
</script>

<template>
  <Page description="创建管理员、维护账号状态并分配角色。密码至少 12 位，需包含大小写字母和数字。" title="用户管理">
    <Card>
      <div class="mb-4 flex justify-end"><Button v-access:code="'system.user.create'" type="primary" @click="open()">新增用户</Button></div>
      <Table :columns="columns" :data-source="users" :loading="loading" :pagination="pagination" row-key="id" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Space v-if="column.dataIndex === 'roles'" wrap><Tag v-for="role in record.roles" :key="role.id">{{ role.name }}</Tag></Space>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <Button v-else-if="column.dataIndex === 'action'" v-access:code="'system.user.update'" type="link" @click="open(record)">编辑</Button>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑用户' : '新增用户'" @ok="save">
      <Form layout="vertical">
        <FormItem label="用户名" required><Input v-model:value="form.username" /></FormItem>
        <FormItem label="姓名" required><Input v-model:value="form.name" /></FormItem>
        <FormItem :label="editingId ? '新密码（不修改请留空）' : '密码'" :required="!editingId"><Input.Password v-model:value="form.password" /></FormItem>
        <FormItem label="角色"><Select v-model:value="form.role_ids" :options="roles.map((role) => ({ label: `${role.name} (${role.code})`, value: role.id }))" mode="multiple" /></FormItem>
        <FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem>
      </Form>
    </Modal>
  </Page>
</template>
