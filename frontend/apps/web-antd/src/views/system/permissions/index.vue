<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue';
import type { TableColumnsType } from 'ant-design-vue';
import { Page } from '@vben/common-ui';
import { Card, Form, FormItem, Input, message, Modal, Popconfirm, Space, Switch, Table, Tag } from 'ant-design-vue';
import { createResource, deleteResource, getResource, updateResource } from '#/api/system';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';

interface Permission { code: string; id: number; is_active: boolean; is_sensitive: boolean; is_system: boolean; name: string }
const loading = ref(false); const saving = ref(false); const visible = ref(false); const editingId = ref<number>();
const permissions = ref<Permission[]>([]); const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const form = reactive({ code: '', is_active: true, is_sensitive: false, name: '' });
const columns: TableColumnsType = [
  { dataIndex: 'code', title: '权限编码' }, { dataIndex: 'name', title: '权限名称' },
  { dataIndex: 'is_sensitive', title: '敏感权限' }, { dataIndex: 'is_system', title: '来源' },
  { dataIndex: 'is_active', title: '状态' }, { dataIndex: 'action', fixed: 'right', title: '操作', width: 82 },
];

async function load() {
  loading.value = true;
  try {
    const result = await getResource('/system/permissions', { page: pagination.current, per_page: pagination.pageSize });
    permissions.value = result.data as Permission[]; pagination.total = result.total;
  } finally { loading.value = false; }
}
function open(item?: any) {
  editingId.value = item?.id;
  Object.assign(form, { code: item?.code ?? '', is_active: item?.is_active ?? true, is_sensitive: item?.is_sensitive ?? false, name: item?.name ?? '' });
  visible.value = true;
}
async function save() {
  if (!form.code || !form.name) return void message.warning('请填写权限编码和名称');
  saving.value = true;
  try {
    if (editingId.value) await updateResource('/system/permissions', editingId.value, form);
    else await createResource('/system/permissions', form);
    visible.value = false; message.success('权限保存成功'); await load();
  } finally { saving.value = false; }
}
async function remove(item: Record<string, any>) { await deleteResource('/system/permissions', Number(item.id)); message.success('权限已删除'); await load(); }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load(); }
onMounted(load);
</script>

<template>
  <Page description="权限编码是稳定的服务端授权标识；标记为敏感的权限应谨慎分配。" title="权限管理">
    <ListToolbar><template #left><PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">刷新</PermissionButton></template><template #right><PermissionButton icon="lucide:key-round" permission="system.permission.create" type="primary" @click="open()">新增权限</PermissionButton></template></ListToolbar>
    <Card :body-style="{ padding: 0 }">
      <Table bordered class="admin-data-table" :columns="columns" :data-source="permissions" :loading="loading" :pagination="pagination" row-key="id" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'is_sensitive'" :color="text ? 'red' : 'default'">{{ text ? '敏感' : '普通' }}</Tag>
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
        <FormItem label="权限编码" required><Input v-model:value="form.code" placeholder="例如 match.publish" /></FormItem>
        <FormItem label="权限名称" required><Input v-model:value="form.name" /></FormItem>
        <Space><FormItem label="启用"><Switch v-model:checked="form.is_active" /></FormItem><FormItem label="敏感权限"><Switch v-model:checked="form.is_sensitive" /></FormItem></Space>
      </Form>
    </Modal>
  </Page>
</template>
