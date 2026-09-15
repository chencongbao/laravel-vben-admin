<script lang="ts" setup>
import type { TableColumnsType } from 'ant-design-vue';
import type { Key } from 'ant-design-vue/es/_util/type';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { Card, Checkbox, Form, FormItem, Input, InputNumber, message, Modal, Popconfirm, Space, Switch, Table, Tag, Tree } from 'ant-design-vue';

import { createResource, deleteResource, getCollection, getResource, getResourceDetail, updateResource } from '#/api/system';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { formatBeijingDateTime } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';
import { useAdminTableScrollY } from '#/utils/table';

interface MenuItem { code: string; id: number; parent_id?: null | number; title: string }
interface PermissionItem { code: string; id: number; name: string; parent_id?: null | number }
interface Role { code: string; created_at: string; id: number; is_active: boolean; is_super_admin: boolean; is_system: boolean; menus_count: number; name: string; permissions_count: number; updated_at: string }
interface AccessTreeNode { children?: AccessTreeNode[]; key: Key; title: string }

const loading = ref(false); const visible = ref(false); const saving = ref(false);
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const editingRole = ref<Role>(); const roles = ref<Role[]>([]); const permissions = ref<PermissionItem[]>([]); const menus = ref<MenuItem[]>([]);
const checkedPermissionKeys = ref<Key[]>([]); const expandedPermissionKeys = ref<Key[]>([]); const expandAllPermissions = ref(true);
const checkedMenuKeys = ref<Key[]>([]); const expandedMenuKeys = ref<Key[]>([]); const expandAllMenus = ref(true);
const pagination = reactive(createAdminPagination());
const searchId = ref<number>(); const showFilters = ref(true);
const form = reactive({ code: '', is_active: true, name: '' });
const columns: TableColumnsType = [
  { dataIndex: 'code', title: '角色标识' }, { dataIndex: 'name', title: '角色名称' },
  { dataIndex: 'permissions_count', title: '权限数' }, { dataIndex: 'menus_count', title: '菜单数' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
  { dataIndex: 'updated_at', title: '更新时间' }, { dataIndex: 'action', fixed: 'right', title: '操作', width: 82 },
];
const isProtected = computed(() => editingRole.value?.is_super_admin === true);
const isSystemIdentity = computed(() => editingRole.value?.is_system === true);
const menuTree = computed<AccessTreeNode[]>(() => {
  const childrenByParent = new Map<null | number, MenuItem[]>();
  for (const menu of menus.value) {
    const parent = menu.parent_id ?? null;
    childrenByParent.set(parent, [...(childrenByParent.get(parent) ?? []), menu]);
  }
  const buildMenu = (menu: MenuItem): AccessTreeNode => {
    const childMenus = (childrenByParent.get(menu.id) ?? []).map((item) => buildMenu(item));
    return { children: childMenus, key: menu.id, title: `${$t(menu.title)}（${menu.code}）` };
  };
  return (childrenByParent.get(null) ?? []).map((item) => buildMenu(item));
});
const permissionTree = computed<AccessTreeNode[]>(() => {
  const childrenByParent = new Map<null | number, PermissionItem[]>();
  for (const permission of permissions.value) {
    const parent = permission.parent_id ?? null;
    childrenByParent.set(parent, [...(childrenByParent.get(parent) ?? []), permission]);
  }
  const buildPermission = (permission: PermissionItem): AccessTreeNode => ({
    children: (childrenByParent.get(permission.id) ?? []).map((item) => buildPermission(item)),
    key: permission.id,
    title: `${permission.name}（${permission.code}）`,
  });
  return (childrenByParent.get(null) ?? []).map((item) => buildPermission(item));
});
function treeKeys(nodes: AccessTreeNode[]) {
  const keys: Key[] = [];
  const visit = (nodes: AccessTreeNode[]) => nodes.forEach((node) => { keys.push(node.key); if (node.children) visit(node.children); });
  visit(nodes); return keys;
}
const allPermissionKeys = computed(() => treeKeys(permissionTree.value));
const allMenuKeys = computed(() => treeKeys(menuTree.value));
const checkAllPermissions = computed({
  get: () => allPermissionKeys.value.length > 0 && checkedPermissionKeys.value.length === allPermissionKeys.value.length,
  set: (checked: boolean) => { checkedPermissionKeys.value = checked ? [...allPermissionKeys.value] : []; },
});
const checkAllMenus = computed({
  get: () => allMenuKeys.value.length > 0 && checkedMenuKeys.value.length === allMenuKeys.value.length,
  set: (checked: boolean) => { checkedMenuKeys.value = checked ? [...allMenuKeys.value] : []; },
});
const permissionIndeterminate = computed(() => checkedPermissionKeys.value.length > 0 && checkedPermissionKeys.value.length < allPermissionKeys.value.length);
const menuIndeterminate = computed(() => checkedMenuKeys.value.length > 0 && checkedMenuKeys.value.length < allMenuKeys.value.length);

async function load() {
  loading.value = true;
  try {
    const result = await getResource('/system/roles', { id: searchId.value, page: pagination.current, per_page: pagination.pageSize });
    roles.value = result.data as Role[]; pagination.total = result.total;
  } finally { loading.value = false; }
}
async function loadAccessCatalog() {
  const [permissionResult, menuResult] = await Promise.all([getResource('/system/permissions', { per_page: 100 }), getCollection<{ menus: MenuItem[] }>('/system/menus')]);
  permissions.value = permissionResult.data as PermissionItem[]; menus.value = menuResult.menus;
  expandedPermissionKeys.value = [...allPermissionKeys.value];
  expandedMenuKeys.value = [...allMenuKeys.value];
}
async function openEdit(role?: any) {
  loading.value = true;
  try {
    editingRole.value = role;
    Object.assign(form, { code: role?.code ?? '', is_active: role?.is_active ?? true, name: role?.name ?? '' });
    await loadAccessCatalog();
    if (role) {
      const detail = await getResourceDetail<{ role: Role & { menus: MenuItem[]; permissions: PermissionItem[] } }>('/system/roles', role.id);
      checkedPermissionKeys.value = detail.role.permissions.map((item) => item.id);
      checkedMenuKeys.value = detail.role.menus.map((item) => item.id);
      if (role.is_super_admin) {
        checkedPermissionKeys.value = [...allPermissionKeys.value];
        checkedMenuKeys.value = [...allMenuKeys.value];
      }
    } else {
      checkedPermissionKeys.value = [];
      checkedMenuKeys.value = [];
    }
    visible.value = true;
  } finally { loading.value = false; }
}
function selectedAccess() {
  return {
    menu_ids: checkedMenuKeys.value.filter((key): key is number => typeof key === 'number'),
    permission_ids: checkedPermissionKeys.value.filter((key): key is number => typeof key === 'number'),
  };
}
async function saveRole() {
  if (!form.code || !form.name) return void message.warning('请填写角色标识和名称');
  const access = selectedAccess();
  if (access.permission_ids.length === 0) return void message.warning('请至少选择一项权限');
  if (access.menu_ids.length === 0) return void message.warning('请至少选择一项菜单');
  saving.value = true;
  try {
    const payload = { ...form, ...access };
    await (editingRole.value ? updateResource('/system/roles', editingRole.value.id, payload) : createResource('/system/roles', payload));
    visible.value = false; message.success('角色和权限保存成功'); await load();
  } finally { saving.value = false; }
}
async function remove(role: any) { await deleteResource('/system/roles', role.id); message.success('角色已删除'); await load(); }
function togglePermissionExpand(checked: boolean) { expandAllPermissions.value = checked; expandedPermissionKeys.value = checked ? [...allPermissionKeys.value] : []; }
function toggleMenuExpand(checked: boolean) { expandAllMenus.value = checked; expandedMenuKeys.value = checked ? [...allMenuKeys.value] : []; }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? pagination.pageSize; void load(); }
function search() { pagination.current = 1; void load(); }
function resetSearch() { searchId.value = undefined; search(); }
function handleModalOk() { isProtected.value ? visible.value = false : void saveRole(); }
onMounted(load);
</script>

<template>
  <Page :description="$t('system.rolesDescription')" :title="$t('system.roles')">
    <ListToolbar><template #left><ListRefreshButton :loading="loading" /><PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">{{ $t('common.actions.filter') }}</PermissionButton></template><template #right><PermissionButton icon="lucide:shield-plus" permission="system.role.create" type="primary" @click="openEdit()">新增角色</PermissionButton></template></ListToolbar>
    <ListSearchPanel v-if="showFilters"><ListSearchField :label="$t('common.fields.id')"><InputNumber v-model:value="searchId" :min="1" :placeholder="$t('common.fields.id')" @press-enter="search" /></ListSearchField><template #actions><PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton><PermissionButton icon="lucide:rotate-ccw" @click="resetSearch">{{ $t('common.actions.reset') }}</PermissionButton></template></ListSearchPanel>
    <Card :body-style="{ padding: 0 }" class="admin-table-card">
      <Table :ref="setTableRef" bordered class="admin-data-table" :columns="columns" :data-source="roles" :loading="loading" :pagination="pagination" :scroll="{ x: 1100, y: tableScrollY }" row-key="id" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'code'" color="blue">{{ text }}</Tag>
          <span
            v-else-if="
              record.is_super_admin &&
              (column.dataIndex === 'permissions_count' ||
                column.dataIndex === 'menus_count')
            "
          >全部</span>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <span v-else-if="column.dataIndex === 'created_at' || column.dataIndex === 'updated_at'">{{ formatBeijingDateTime(text) }}</span>
          <Space v-else-if="column.dataIndex === 'action'">
            <PermissionButton :icon="record.is_super_admin ? 'lucide:eye' : 'lucide:pencil'" icon-only permission="system.role.update" :tooltip="record.is_super_admin ? '查看角色' : '编辑与授权'" type="text" @click="openEdit(record)" />
            <Popconfirm v-if="!record.is_system" v-access:code="'system.role.delete'" title="确定删除该角色？" @confirm="remove(record)"><PermissionButton danger icon="lucide:trash-2" icon-only permission="system.role.delete" tooltip="删除角色" type="text" /></Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :ok-text="isProtected ? '关闭' : '确定'" :title="editingRole ? (isProtected ? '查看超级管理员' : '编辑角色') : '新增角色'" width="900px" @ok="handleModalOk">
      <Form class="pt-2" layout="vertical">
        <div class="grid grid-cols-2 gap-4">
          <FormItem label="角色标识" required><Input v-model:value="form.code" :disabled="isSystemIdentity" placeholder="例如：operator" /></FormItem>
          <FormItem label="角色名称" required><Input v-model:value="form.name" :disabled="isSystemIdentity" placeholder="请输入角色名称" /></FormItem>
        </div>
        <FormItem label="启用状态"><Switch v-model:checked="form.is_active" :disabled="isSystemIdentity" /></FormItem>
        <FormItem label="权限" required>
          <div class="mb-3 flex items-center gap-5">
            <Checkbox v-model:checked="checkAllPermissions" :disabled="isProtected" :indeterminate="permissionIndeterminate">全选</Checkbox>
            <Checkbox :checked="expandAllPermissions" @update:checked="togglePermissionExpand">展开</Checkbox>
          </div>
          <Tree v-model:checked-keys="checkedPermissionKeys" v-model:expanded-keys="expandedPermissionKeys" :disabled="isProtected" :tree-data="permissionTree" checkable />
        </FormItem>
        <FormItem label="菜单" required>
          <div class="mb-3 flex items-center gap-5">
            <Checkbox v-model:checked="checkAllMenus" :disabled="isProtected" :indeterminate="menuIndeterminate">全选</Checkbox>
            <Checkbox :checked="expandAllMenus" @update:checked="toggleMenuExpand">展开</Checkbox>
          </div>
          <Tree v-model:checked-keys="checkedMenuKeys" v-model:expanded-keys="expandedMenuKeys" :disabled="isProtected" :tree-data="menuTree" checkable />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
