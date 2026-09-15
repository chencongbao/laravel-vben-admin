<script lang="ts" setup>
import type { TableColumnsType } from 'ant-design-vue';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';

import { Card, Form, FormItem, Input, InputNumber, message, Modal, Popconfirm, Select, Space, Switch, Table, Tag, Textarea, Tree } from 'ant-design-vue';

import { createResource, deleteResource, getCollection, getResource, updateResource } from '#/api/system';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';
import { createAdminPagination } from '#/utils/pagination';
import { useAdminTableScrollY } from '#/utils/table';

interface MenuItem { code: string; id: number; parent_id?: null | number; title: string }
interface MenuTreeNode { children: MenuTreeNode[]; key: number; title: string }
interface Permission { code: string; description?: null | string; http_methods?: null | string[]; http_paths?: null | string[]; id: number; is_active: boolean; is_sensitive: boolean; is_system: boolean; menus?: MenuItem[]; name: string; parent_id?: null | number; sort?: number }
const loading = ref(false); const saving = ref(false); const visible = ref(false); const editingId = ref<number>();
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const permissions = ref<Permission[]>([]); const allPermissions = ref<Permission[]>([]); const menus = ref<MenuItem[]>([]); const pagination = reactive(createAdminPagination());
const searchId = ref<number>(); const showFilters = ref(true);
const form = reactive({ code: '', description: '', http_methods: [] as string[], http_paths_text: '', is_active: true, is_sensitive: false, menu_ids: [] as number[], name: '', parent_id: undefined as number | undefined, sort: 0 });
const columns: TableColumnsType = [
  { dataIndex: 'code', title: '权限编码' }, { dataIndex: 'name', title: '权限名称' }, { dataIndex: 'http_methods', title: '请求方法', width: 180 },
  { dataIndex: 'is_sensitive', title: '敏感权限' }, { dataIndex: 'is_system', title: '来源' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', fixed: 'right', title: '操作', width: 82 },
];
const menuTree = computed(() => {
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

async function load() {
  loading.value = true;
  try {
    const [result, allResult, menuResult] = await Promise.all([
      getResource('/system/permissions', { id: searchId.value, page: pagination.current, per_page: pagination.pageSize }),
      getResource('/system/permissions', { per_page: 100 }),
      getCollection<{ menus: MenuItem[] }>('/system/menus'),
    ]);
    permissions.value = result.data as Permission[]; pagination.total = result.total;
    allPermissions.value = allResult.data as Permission[]; menus.value = menuResult.menus;
  } finally { loading.value = false; }
}
function open(item?: any) {
  editingId.value = item?.id;
  Object.assign(form, { code: item?.code ?? '', description: item?.description ?? '', http_methods: item?.http_methods ?? [], http_paths_text: (item?.http_paths ?? []).join('\n'), is_active: item?.is_active ?? true, is_sensitive: item?.is_sensitive ?? false, menu_ids: (item?.menus ?? []).map((menu: MenuItem) => menu.id), name: item?.name ?? '', parent_id: item?.parent_id ?? undefined, sort: item?.sort ?? 0 });
  visible.value = true;
}
async function save() {
  if (!form.code || !form.name) return void message.warning('请填写权限编码和名称');
  saving.value = true;
  try {
    const httpPaths = form.http_paths_text.split('\n').map((item) => item.trim()).filter(Boolean);
    const payload = { code: form.code, description: form.description || null, http_methods: form.http_methods.length > 0 ? form.http_methods : null, http_paths: httpPaths.length > 0 ? httpPaths : null, is_active: form.is_active, is_sensitive: form.is_sensitive, menu_ids: form.menu_ids, name: form.name, parent_id: form.parent_id ?? null, sort: form.sort };
    await (editingId.value ? updateResource('/system/permissions', editingId.value, payload) : createResource('/system/permissions', payload));
    visible.value = false; message.success('权限保存成功'); await load();
  } finally { saving.value = false; }
}
async function remove(item: Record<string, any>) { await deleteResource('/system/permissions', Number(item.id)); message.success('权限已删除'); await load(); }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? pagination.pageSize; void load(); }
function search() { pagination.current = 1; void load(); }
function resetSearch() { searchId.value = undefined; search(); }
onMounted(load);
</script>

<template>
  <Page :description="$t('system.permissionsDescription')" :title="$t('system.permissions')">
    <ListToolbar><template #left><ListRefreshButton :loading="loading" /><PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">{{ $t('common.actions.filter') }}</PermissionButton></template><template #right><PermissionButton icon="lucide:key-round" permission="system.permission.create" type="primary" @click="open()">新增权限</PermissionButton></template></ListToolbar>
    <ListSearchPanel v-if="showFilters"><ListSearchField :label="$t('common.fields.id')"><InputNumber v-model:value="searchId" :min="1" :placeholder="$t('common.fields.id')" @press-enter="search" /></ListSearchField><template #actions><PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton><PermissionButton icon="lucide:rotate-ccw" @click="resetSearch">{{ $t('common.actions.reset') }}</PermissionButton></template></ListSearchPanel>
    <Card :body-style="{ padding: 0 }" class="admin-table-card">
      <Table :ref="setTableRef" bordered class="admin-data-table" :columns="columns" :data-source="permissions" :loading="loading" :pagination="pagination" row-key="id" :scroll="{ y: tableScrollY }" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <template v-if="column.dataIndex === 'http_methods'"><Tag v-for="method in (text || [])" :key="method">{{ method }}</Tag><span v-if="!text?.length">—</span></template>
          <Tag v-else-if="column.dataIndex === 'is_sensitive'" :color="text ? 'red' : 'default'">{{ text ? '敏感' : '普通' }}</Tag>
          <Tag v-else-if="column.dataIndex === 'is_system'" :color="text ? 'blue' : 'default'">{{ text ? '系统' : '自定义' }}</Tag>
          <Tag v-else-if="column.dataIndex === 'is_active'" :color="text ? 'green' : 'default'">{{ text ? '启用' : '禁用' }}</Tag>
          <Space v-else-if="column.dataIndex === 'action'">
            <PermissionButton icon="lucide:pencil" icon-only permission="system.permission.update" tooltip="编辑权限" type="text" @click="open(record)" />
            <Popconfirm v-access:code="'system.permission.delete'" title="确定删除该权限？" @confirm="remove(record)"><PermissionButton danger icon="lucide:trash-2" icon-only permission="system.permission.delete" tooltip="删除权限" type="text" /></Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑权限' : '新增权限'" @ok="save">
      <Form layout="vertical">
        <FormItem label="权限编码" required><Input v-model:value="form.code" :disabled="Boolean(editingId && permissions.find((item) => item.id === editingId)?.is_system)" placeholder="例如 match.publish" /></FormItem>
        <FormItem label="权限名称" required><Input v-model:value="form.name" /></FormItem>
        <FormItem label="父级权限"><Select v-model:value="form.parent_id" allow-clear :options="allPermissions.filter((item) => item.id !== editingId).map((item) => ({ label: `${item.name}（${item.code}）`, value: item.id }))" placeholder="不选择则为根权限" show-search /></FormItem>
        <FormItem label="权限说明"><Textarea v-model:value="form.description" :maxlength="500" :rows="3" show-count /></FormItem>
        <FormItem label="请求方法"><Select v-model:value="form.http_methods" mode="multiple" :options="['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'].map((value) => ({ label: value, value }))" placeholder="可选，仅用于接口覆盖审计" /></FormItem>
        <FormItem label="请求路径"><Textarea v-model:value="form.http_paths_text" :rows="4" placeholder="每行一个路径，例如 /api/admin/matches/{match}/publish" /><div class="text-muted-foreground mt-1 text-xs">请求方法和路径不替代服务端权限编码校验。</div></FormItem>
        <FormItem label="关联菜单"><Tree v-model:checked-keys="form.menu_ids" checkable default-expand-all :tree-data="menuTree" /></FormItem>
        <FormItem label="排序"><InputNumber v-model:value="form.sort" /></FormItem>
        <Space><FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem><FormItem label="敏感权限"><Switch v-model:checked="form.is_sensitive" /></FormItem></Space>
      </Form>
    </Modal>
  </Page>
</template>
