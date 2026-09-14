<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue';
import type { TableColumnsType } from 'ant-design-vue';
import { Page } from '@vben/common-ui';
import { Card, Form, FormItem, Input, InputNumber, message, Modal, Select, Space, Switch, Table, Tag } from 'ant-design-vue';
import { createResource, getResource, updateResource } from '#/api/system';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';

interface Role { code: string; id: number; name: string }
interface AdminUser {
  id: number;
  is_active: boolean;
  last_login_at?: null | string;
  last_login_ip?: null | string;
  login_ip_whitelist?: string[];
  name: string;
  roles: Role[];
  two_factor_confirmed_at?: null | string;
  two_factor_enabled: boolean;
  username: string;
}

const loading = ref(false);
const saving = ref(false);
const visible = ref(false);
const editingId = ref<number>();
const users = ref<AdminUser[]>([]);
const roles = ref<Role[]>([]);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const searchId = ref<number>(); const showFilters = ref(true);
const form = reactive({
  is_active: true,
  login_ip_whitelist: '',
  name: '',
  password: '',
  role_ids: [] as number[],
  two_factor_enabled: false,
  username: '',
});
const columns: TableColumnsType = [
  { dataIndex: 'id', title: $t('common.fields.id') }, { dataIndex: 'username', title: '用户名' },
  { dataIndex: 'name', title: '姓名' }, { dataIndex: 'roles', title: '角色' },
  { dataIndex: 'two_factor_enabled', title: 'Google 2FA' },
  { dataIndex: 'login_ip_whitelist', title: '登录白名单' },
  { dataIndex: 'last_login_ip', title: '最近登录 IP' },
  { dataIndex: 'last_login_at', title: '最近登录时间' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', fixed: 'right', title: '操作', width: 58 },
];

async function load() {
  loading.value = true;
  try {
    const [userResult, roleResult] = await Promise.all([
      getResource('/system/users', { id: searchId.value, page: pagination.current, per_page: pagination.pageSize }),
      getResource('/system/roles', { per_page: 100 }),
    ]);
    users.value = userResult.data as AdminUser[];
    roles.value = roleResult.data as Role[];
    pagination.total = userResult.total;
  } finally { loading.value = false; }
}

function open(record?: any) {
  editingId.value = record?.id;
  Object.assign(form, {
    is_active: record?.is_active ?? true, name: record?.name ?? '', password: '',
    login_ip_whitelist: record?.login_ip_whitelist?.join('\n') ?? '',
    role_ids: record?.roles.map((role: Role) => role.id) ?? [], username: record?.username ?? '',
    two_factor_enabled: record?.two_factor_enabled ?? false,
  });
  visible.value = true;
}

async function save() {
  if (!form.username || !form.name || (!editingId.value && !form.password)) {
    message.warning('请完整填写用户名、姓名和密码'); return;
  }
  const whitelist = [...new Set(form.login_ip_whitelist.split(/[,\n]/).map((item) => item.trim()).filter(Boolean))];
  saving.value = true;
  try {
    const payload: Record<string, any> = { ...form, login_ip_whitelist: whitelist };
    if (!payload.password) delete payload.password;
    if (editingId.value) await updateResource('/system/users', editingId.value, payload);
    else await createResource('/system/users', payload);
    visible.value = false; message.success('用户保存成功'); await load();
  } finally { saving.value = false; }
}

function changePage(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load();
}
function search() { pagination.current = 1; void load(); }
function resetSearch() { searchId.value = undefined; search(); }
onMounted(load);
</script>

<template>
  <Page :description="$t('system.usersDescription')" :title="$t('system.administrators')">
    <ListToolbar><template #left><ListRefreshButton :loading="loading" /><PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">{{ $t('common.actions.filter') }}</PermissionButton></template><template #right><PermissionButton icon="lucide:user-plus" permission="system.user.create" type="primary" @click="open()">新增用户</PermissionButton></template></ListToolbar>
    <ListSearchPanel v-if="showFilters"><ListSearchField :label="$t('common.fields.id')"><InputNumber v-model:value="searchId" :min="1" :placeholder="$t('common.fields.id')" @press-enter="search" /></ListSearchField><template #actions><PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton><PermissionButton icon="lucide:rotate-ccw" @click="resetSearch">{{ $t('common.actions.reset') }}</PermissionButton></template></ListSearchPanel>
    <Card :body-style="{ padding: 0 }" class="admin-table-card">
      <Table bordered class="admin-data-table" :columns="columns" :data-source="users" :loading="loading" :pagination="pagination" row-key="id" :scroll="{ x: 1250 }" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Space v-if="column.dataIndex === 'roles'" wrap><Tag v-for="role in record.roles" :key="role.id">{{ role.name }}</Tag></Space>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <Tag v-else-if="column.dataIndex === 'two_factor_enabled'" :color="text ? (record.two_factor_confirmed_at ? 'green' : 'orange') : 'default'">
            {{ text ? (record.two_factor_confirmed_at ? '已绑定' : '待绑定') : '未开启' }}
          </Tag>
          <Tag v-else-if="column.dataIndex === 'login_ip_whitelist'" :color="text?.length ? 'green' : 'red'">{{ text?.length ? `已设置 ${text.length} 条` : '未设置' }}</Tag>
          <span v-else-if="column.dataIndex === 'last_login_ip'">{{ text || '—' }}</span>
          <span v-else-if="column.dataIndex === 'last_login_at'">{{ formatBeijingDateTime(text) }}</span>
          <PermissionButton v-else-if="column.dataIndex === 'action'" icon="lucide:pencil" icon-only permission="system.user.update" tooltip="编辑用户" type="text" @click="open(record)" />
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑用户' : '新增用户'" width="680px" @ok="save">
      <Form layout="vertical">
        <FormItem label="用户名" required><Input v-model:value="form.username" /></FormItem>
        <FormItem label="姓名" required><Input v-model:value="form.name" /></FormItem>
        <FormItem :label="editingId ? '新密码（不修改请留空）' : '密码'" :required="!editingId"><Input.Password v-model:value="form.password" /></FormItem>
        <FormItem label="角色"><Select v-model:value="form.role_ids" :options="roles.map((role) => ({ label: `${role.name} (${role.code})`, value: role.id }))" mode="multiple" /></FormItem>
        <FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem>
        <div class="security-settings">
          <h3>登录安全</h3>
          <FormItem label="Google 2FA">
            <Switch v-model:checked="form.two_factor_enabled" />
            <span class="setting-tip">开启后，白名单校验通过才会在登录页绑定或验证 Google 验证器。</span>
          </FormItem>
          <FormItem label="登录 IP 白名单">
            <Input.TextArea v-model:value="form.login_ip_whitelist" :rows="5" placeholder="填写后自动开启；留空表示未开启。每行一个，例如：&#10;203.0.113.10&#10;10.0.0.0/24&#10;2001:db8::/32" />
            <div class="setting-tip whitelist-tip">非本地环境必须设置白名单并且当前 IP 命中才能登录。</div>
          </FormItem>
        </div>
      </Form>
    </Modal>
  </Page>
</template>

<style scoped>
.security-settings { margin-top: 20px; border-top: 1px solid hsl(var(--border)); padding-top: 16px; }
.security-settings h3 { margin-bottom: 16px; font-weight: 600; }
.setting-tip { margin-left: 12px; color: hsl(var(--muted-foreground)); font-size: 13px; }
.whitelist-tip { margin-top: 8px; margin-left: 0; }
</style>
