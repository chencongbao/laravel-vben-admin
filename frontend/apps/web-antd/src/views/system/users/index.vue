<script lang="ts" setup>
import type { TableColumnsType } from 'ant-design-vue';
import type { AdminPasswordPolicy } from '#/api/core/user';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { Card, Form, FormItem, Input, InputNumber, message, Modal, Popconfirm, Select, Space, Switch, Table, Tag } from 'ant-design-vue';

import { createResource, deleteResource, getCollection, getResource, updateResource } from '#/api/system';
import { getPasswordPolicyApi } from '#/api/core/user';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';
import { useAdminTableScrollY } from '#/utils/table';

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
const userStore = useUserStore();
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const saving = ref(false);
const visible = ref(false);
const editingId = ref<number>();
const editingRole = ref<Role>();
const users = ref<AdminUser[]>([]);
const roles = ref<Role[]>([]);
const passwordPolicy = ref<AdminPasswordPolicy>({ min_length: 12, requires_mixed_case: true, requires_numbers: true, type: 'strong' });
const pagination = reactive(createAdminPagination());
const searchId = ref<number>(); const showFilters = ref(true);
const form = reactive({
  is_active: true,
  login_ip_whitelist: '',
  name: '',
  password: '',
  role_id: undefined as number | undefined,
  two_factor_enabled: false,
  username: '',
});
const columns = computed<TableColumnsType>(() => [
  { dataIndex: 'id', title: $t('common.fields.id') }, { dataIndex: 'username', title: $t('system.userList.fields.username') },
  { dataIndex: 'name', title: $t('system.userList.fields.name') }, { dataIndex: 'roles', title: $t('system.userList.fields.role') },
  { dataIndex: 'two_factor_enabled', title: $t('system.userList.fields.twoFactor') },
  { dataIndex: 'login_ip_whitelist', title: $t('system.userList.fields.loginAllowlist') },
  { dataIndex: 'last_login_ip', title: $t('system.userList.fields.lastLoginIp') },
  { dataIndex: 'last_login_at', title: $t('system.userList.fields.lastLoginAt') },
  { dataIndex: 'is_active', title: $t('system.userList.fields.status') }, { dataIndex: 'action', fixed: 'right', title: $t('system.userList.fields.action'), width: 82 },
]);
const isFixedRoleAccount = computed(() => editingId.value !== undefined && ['admin', 'cmsadmin'].includes(form.username));
const isEditingManagerAccount = computed(() => editingId.value !== undefined && form.username === 'admin');
const isEditingSelf = computed(() => editingId.value !== undefined && String(editingId.value) === userStore.userInfo?.userId);
const isSuperAdministrator = computed(() => userStore.userInfo?.roles?.includes('administrator') ?? false);
const showRoleField = computed(() => editingId.value === undefined || (!isFixedRoleAccount.value && !isEditingSelf.value));
const showStatusField = computed(() => editingId.value === undefined || (!isEditingSelf.value && (!isEditingManagerAccount.value || isSuperAdministrator.value)));
const roleOptions = computed(() => {
  const availableRoles = [...roles.value];
  if (editingRole.value && !availableRoles.some((role) => role.id === editingRole.value?.id)) {
    availableRoles.push(editingRole.value);
  }
  return availableRoles.map((role) => ({ label: `${roleName(role)} (${role.code})`, value: role.id }));
});
const passwordHelp = computed(() => $t(`system.userForm.tips.password.${passwordPolicy.value.type}`));
const passwordPlaceholder = computed(() => $t(`system.userForm.placeholders.password.${passwordPolicy.value.type}`));
function roleName(role: Role) {
  const roleNameKey = `system.roleNames.${role.code}`;
  const localizedName = $t(roleNameKey);
  return localizedName === roleNameKey ? $t(role.name) : localizedName;
}
function isProtectedAccount(user: Record<string, any>) {
  return (user.roles ?? []).some((role: Role) => ['administrator', 'manager'].includes(role.code));
}

async function load() {
  loading.value = true;
  try {
    const [userResult, roleResult] = await Promise.all([
      getResource('/system/users', { id: searchId.value, page: pagination.current, per_page: pagination.pageSize }),
      getCollection<{ roles: Role[] }>('/system/users/role-options'),
    ]);
    users.value = userResult.data as AdminUser[];
    roles.value = roleResult.roles;
    pagination.total = userResult.total;
  } finally { loading.value = false; }
}

function open(record?: any) {
  editingId.value = record?.id;
  editingRole.value = record?.roles[0];
  Object.assign(form, {
    is_active: record?.is_active ?? true, name: record?.name ?? '', password: '',
    login_ip_whitelist: record?.login_ip_whitelist?.join('\n') ?? '',
    role_id: record?.roles[0]?.id, username: record?.username ?? '',
    two_factor_enabled: record?.two_factor_enabled ?? false,
  });
  visible.value = true;
}

async function save() {
  if (!form.username || !form.name || (!editingId.value && !form.password)) {
    message.warning($t('system.userForm.messages.required')); return;
  }
  if (form.password) {
    const validPassword = passwordPolicy.value.type === 'weak'
      ? form.password.length >= 6
      : /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12,}$/.test(form.password);
    if (!validPassword) {
      message.warning(passwordHelp.value); return;
    }
  }
  const whitelist = [...new Set(form.login_ip_whitelist.split(/[,\n]/).map((item) => item.trim()).filter(Boolean))];
  saving.value = true;
  try {
    const payload: Record<string, any> = {
      ...form,
      login_ip_whitelist: whitelist,
      role_ids: form.role_id ? [form.role_id] : [],
    };
    delete payload.role_id;
    if (!showStatusField.value) delete payload.is_active;
    if (!showRoleField.value) delete payload.role_ids;
    if (!payload.password) delete payload.password;
    await (editingId.value ? updateResource('/system/users', editingId.value, payload) : createResource('/system/users', payload));
    visible.value = false; message.success($t('system.userForm.messages.saved')); await load();
  } finally { saving.value = false; }
}

async function remove(user: Record<string, any>) {
  await deleteResource('/system/users', user.id);
  message.success($t('system.userForm.messages.deleted'));
  await load();
}

function changePage(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? pagination.pageSize; void load();
}
function search() { pagination.current = 1; void load(); }
function resetSearch() { searchId.value = undefined; search(); }
onMounted(async () => {
  const result = await getPasswordPolicyApi();
  passwordPolicy.value = result.password_policy;
  await load();
});
</script>

<template>
  <Page :description="$t('system.usersDescription')" :title="$t('system.administrators')">
    <ListToolbar><template #left><ListRefreshButton :loading="loading" /><PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">{{ $t('common.actions.filter') }}</PermissionButton></template><template #right><PermissionButton icon="lucide:user-plus" permission="system.user.create" type="primary" @click="open()">{{ $t('system.userForm.actions.create') }}</PermissionButton></template></ListToolbar>
    <ListSearchPanel v-if="showFilters"><ListSearchField :label="$t('common.fields.id')"><InputNumber v-model:value="searchId" :min="1" :placeholder="$t('common.fields.id')" @press-enter="search" /></ListSearchField><template #actions><PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton><PermissionButton icon="lucide:rotate-ccw" @click="resetSearch">{{ $t('common.actions.reset') }}</PermissionButton></template></ListSearchPanel>
    <Card :body-style="{ padding: 0 }" class="admin-table-card">
      <Table :ref="setTableRef" bordered class="admin-data-table" :columns="columns" :data-source="users" :loading="loading" :pagination="pagination" row-key="id" :scroll="{ x: 1250, y: tableScrollY }" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Space v-if="column.dataIndex === 'roles'" wrap><Tag v-for="role in record.roles" :key="role.id">{{ roleName(role) }}</Tag></Space>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? $t('system.common.states.enabled') : $t('system.common.states.disabled') }}</Tag>
          <Tag v-else-if="column.dataIndex === 'two_factor_enabled'" :color="text ? (record.two_factor_confirmed_at ? 'green' : 'orange') : 'default'">
            {{ text ? (record.two_factor_confirmed_at ? $t('system.userList.states.twoFactorBound') : $t('system.userList.states.twoFactorPending')) : $t('system.userList.states.twoFactorDisabled') }}
          </Tag>
          <Tag v-else-if="column.dataIndex === 'login_ip_whitelist'" :color="text?.length ? 'green' : 'red'">{{ text?.length ? $t('system.userList.states.allowlistConfigured', { count: text.length }) : $t('system.userList.states.allowlistUnset') }}</Tag>
          <span v-else-if="column.dataIndex === 'last_login_ip'">{{ text || '—' }}</span>
          <span v-else-if="column.dataIndex === 'last_login_at'">{{ formatBeijingDateTime(text) }}</span>
          <Space v-else-if="column.dataIndex === 'action'">
            <PermissionButton icon="lucide:pencil" icon-only permission="system.user.update" :tooltip="$t('system.userForm.actions.edit')" type="text" @click="open(record)" />
            <Popconfirm v-if="!isProtectedAccount(record)" v-access:code="'system.user.delete'" :title="$t('system.userForm.prompts.delete')" @confirm="remove(record)">
              <PermissionButton danger icon="lucide:trash-2" icon-only permission="system.user.delete" :tooltip="$t('system.common.actions.delete')" type="text" />
            </Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? $t('system.userForm.actions.edit') : $t('system.userForm.actions.create')" width="680px" @ok="save">
      <Form layout="vertical">
        <FormItem :label="$t('system.userForm.fields.username')" required><Input v-model:value="form.username" :disabled="editingId !== undefined" /></FormItem>
        <FormItem :label="$t('system.userForm.fields.name')" required><Input v-model:value="form.name" /></FormItem>
        <FormItem :label="editingId ? $t('system.userForm.fields.newPassword') : $t('system.userForm.fields.password')" :required="!editingId">
          <Input.Password v-model:value="form.password" :placeholder="passwordPlaceholder" />
          <div class="setting-tip">{{ passwordHelp }}</div>
        </FormItem>
        <FormItem v-if="showRoleField" :label="$t('system.userForm.fields.role')"><Select v-model:value="form.role_id" allow-clear :options="roleOptions" /></FormItem>
        <FormItem v-if="showStatusField" :label="$t('system.userForm.fields.active')"><Switch v-model:checked="form.is_active" /></FormItem>
        <div class="security-settings">
          <h3>{{ $t('system.userForm.fields.security') }}</h3>
          <FormItem :label="$t('system.userForm.fields.twoFactor')">
            <Switch v-model:checked="form.two_factor_enabled" />
            <span class="setting-tip">{{ $t('system.userForm.tips.twoFactor') }}</span>
          </FormItem>
          <FormItem :label="$t('system.userForm.fields.loginAllowlist')">
            <Input.TextArea v-model:value="form.login_ip_whitelist" :rows="5" :placeholder="$t('system.userForm.placeholders.allowlist')" />
            <div class="setting-tip whitelist-tip">{{ $t('system.userForm.tips.allowlist') }}</div>
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
