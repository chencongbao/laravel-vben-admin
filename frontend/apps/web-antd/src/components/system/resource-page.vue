<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';

import type { TableColumnsType } from 'ant-design-vue';

import { Page } from '@vben/common-ui';

import {
  Card,
  Form,
  FormItem,
  Input,
  InputNumber,
  message,
  Modal,
  Popconfirm,
  Space,
  Switch,
  Table,
  Tag,
} from 'ant-design-vue';

import {
  createResource,
  deleteResource,
  getCollection,
  getResource,
  updateResource,
} from '#/api/system';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { formatBeijingDateTime, isDateTimeField } from '#/utils/datetime';

export interface ResourceField {
  key: string;
  label: string;
  required?: boolean;
  table?: boolean;
  type?: 'boolean' | 'number' | 'password' | 'text';
}

const props = defineProps<{
  collectionKey?: string;
  description?: string;
  fields: ResourceField[];
  path: string;
  permissionPrefix?: string;
  readOnly?: boolean;
  title: string;
}>();

const loading = ref(false);
const saving = ref(false);
const visible = ref(false);
const editingId = ref<number>();
const rows = ref<Record<string, any>[]>([]);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const form = reactive<Record<string, any>>({});

const columns = computed<TableColumnsType>(() => {
  const items: TableColumnsType = props.fields
    .filter((field) => field.table !== false)
    .map((field) => ({ dataIndex: field.key, key: field.key, title: field.label }));
  if (!props.readOnly) items.push({ dataIndex: 'action', fixed: 'right', key: 'action', title: '操作', width: 82 });
  return items;
});

function resetForm(record?: Record<string, any>) {
  Object.keys(form).forEach((key) => delete form[key]);
  props.fields.forEach((field) => {
    form[field.key] = record?.[field.key] ?? (field.type === 'boolean' ? true : undefined);
  });
}

async function load() {
  loading.value = true;
  try {
    if (props.collectionKey) {
      const result = await getCollection<Record<string, any>>(props.path);
      rows.value = result[props.collectionKey] ?? [];
      pagination.total = rows.value.length;
    } else {
      const result = await getResource(props.path, {
        page: pagination.current,
        per_page: pagination.pageSize,
      });
      rows.value = result.data;
      pagination.total = result.total;
    }
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = undefined;
  resetForm();
  visible.value = true;
}

function openEdit(record: Record<string, any>) {
  editingId.value = record.id;
  resetForm(record);
  visible.value = true;
}

async function save() {
  for (const field of props.fields) {
    if (field.required && (form[field.key] === undefined || form[field.key] === '')) {
      message.warning(`请填写${field.label}`);
      return;
    }
  }
  saving.value = true;
  try {
    const payload = Object.fromEntries(
      Object.entries(form).filter(([, value]) => value !== undefined && value !== ''),
    );
    if (editingId.value) await updateResource(props.path, editingId.value, payload);
    else await createResource(props.path, payload);
    message.success('保存成功');
    visible.value = false;
    await load();
  } finally {
    saving.value = false;
  }
}

async function remove(id: number) {
  await deleteResource(props.path, id);
  message.success('删除成功');
  await load();
}

function onTableChange(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1;
  pagination.pageSize = page.pageSize ?? 20;
  void load();
}

onMounted(load);
</script>

<template>
  <Page :description="description" :title="title">
    <ListToolbar>
      <template #left><PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">刷新</PermissionButton></template>
      <template v-if="!readOnly" #right><PermissionButton icon="lucide:plus" :permission="`${permissionPrefix}.create`" type="primary" @click="openCreate">新增</PermissionButton></template>
    </ListToolbar>
    <Card :body-style="{ padding: 0 }">
      <Table
        bordered
        class="admin-data-table"
        :columns="columns"
        :data-source="rows"
        :loading="loading"
        :pagination="pagination"
        row-key="id"
        @change="onTableChange"
      >
        <template #bodyCell="{ column, record, text }">
          <template v-if="column.key === 'action'">
            <Space>
              <PermissionButton icon="lucide:pencil" icon-only :permission="`${permissionPrefix}.update`" tooltip="编辑" type="text" @click="openEdit(record)" />
              <Popconfirm v-access:code="`${permissionPrefix}.delete`" title="确定删除此记录？" @confirm="remove(record.id)">
                <PermissionButton danger icon="lucide:trash-2" icon-only :permission="`${permissionPrefix}.delete`" tooltip="删除" type="text" />
              </Popconfirm>
            </Space>
          </template>
          <Tag v-else-if="typeof text === 'boolean'" :color="text ? 'green' : 'default'">
            {{ text ? '启用' : '禁用' }}
          </Tag>
          <span v-else-if="isDateTimeField(String(column.key))">{{ formatBeijingDateTime(text) }}</span>
          <span v-else-if="typeof text === 'object'">{{ JSON.stringify(text) }}</span>
        </template>
      </Table>
    </Card>

    <Modal v-model:open="visible" :confirm-loading="saving" :title="editingId ? '编辑' : '新增'" @ok="save">
      <Form layout="vertical">
        <FormItem v-for="field in fields" :key="field.key" :label="field.label" :required="field.required">
          <Switch v-if="field.type === 'boolean'" v-model:checked="form[field.key]" />
          <InputNumber v-else-if="field.type === 'number'" v-model:value="form[field.key]" class="w-full" />
          <Input.Password v-else-if="field.type === 'password'" v-model:value="form[field.key]" />
          <Input v-else v-model:value="form[field.key]" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
