<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Card, Input, InputNumber, Modal, Select, Table, Tag } from 'ant-design-vue';
import { batchDeleteResources, getResource } from '#/api/system';
import AutoRefresh from '#/components/system/auto-refresh.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import TableExportButton from '#/components/system/table-export-button.vue';
import TableBatchActions from '#/components/system/table-batch-actions.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime, isDateTimeField } from '#/utils/datetime';

interface LogFilter {
  key: string;
  label: string;
  options?: Array<{ label: string; value: number | string }>;
  type?: 'number' | 'text';
}

const props = defineProps<{
  columns: Array<{ dataIndex: string; title: string }>;
  batchDeletePermission?: string;
  description?: string;
  filters: LogFilter[];
  path: string;
  permission?: string;
  title: string;
}>();
const loading = ref(false); const rows = ref<Record<string, any>[]>([]);
const showFilters = ref(true);
const values = reactive<Record<string, any>>({});
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const selectedRowKeys = ref<number[]>([]);
const effectiveFilters = computed<LogFilter[]>(() => props.filters.some((filter) => filter.key === 'id')
  ? props.filters
  : [{ key: 'id', label: $t('common.fields.id'), type: 'number' }, ...props.filters]);

async function load() {
  loading.value = true;
  try {
    const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== undefined));
    const result = await getResource(props.path, { ...params, page: pagination.current, per_page: pagination.pageSize });
    rows.value = result.data; pagination.total = result.total;
  } finally { loading.value = false; }
}
function search() { pagination.current = 1; void load(); }
function reset() { Object.keys(values).forEach((key) => delete values[key]); search(); }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load(); }
function batchAction(key: string) {
  if (key !== 'delete' || selectedRowKeys.value.length === 0) return;
  Modal.confirm({
    content: $t('common.batch.deleteConfirm', { count: selectedRowKeys.value.length }),
    okButtonProps: { danger: true },
    okText: $t('common.batch.confirmDelete'),
    title: $t('common.batch.deleteTitle'),
    async onOk() {
      await batchDeleteResources(props.path, selectedRowKeys.value);
      selectedRowKeys.value = [];
      await load();
    },
  });
}
onMounted(load);
</script>

<template>
  <Page :description="description" :title="title">
    <ListToolbar>
      <template #left>
        <ListRefreshButton :loading="loading" />
        <PermissionButton icon="lucide:filter" @click="showFilters = !showFilters">{{ $t('common.actions.filter') }}</PermissionButton>
        <TableBatchActions
          v-if="batchDeletePermission"
          :actions="[{ danger: true, icon: 'lucide:trash-2', key: 'delete', label: $t('common.batch.deleteSelected'), permission: batchDeletePermission }]"
          :selected-count="selectedRowKeys.length"
          @action="batchAction"
        />
      </template>
      <template #right>
        <AutoRefresh :loading="loading" :storage-key="path" />
        <TableExportButton :columns="columns" :filename="title" :permission="permission" :rows="rows" />
        <slot name="actions" />
      </template>
    </ListToolbar>
    <ListSearchPanel v-if="showFilters">
      <ListSearchField v-for="filter in effectiveFilters" :key="filter.key" :label="filter.label">
        <Select v-if="filter.options" v-model:value="values[filter.key]" allow-clear :options="filter.options" :placeholder="filter.label" class="w-full" />
        <InputNumber v-else-if="filter.type === 'number'" v-model:value="values[filter.key]" :min="1" :placeholder="filter.label" />
        <Input v-else v-model:value="values[filter.key]" allow-clear :placeholder="filter.label" @press-enter="search" />
      </ListSearchField>
      <template #actions>
        <PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton>
        <PermissionButton icon="lucide:rotate-ccw" @click="reset">{{ $t('common.actions.reset') }}</PermissionButton>
      </template>
    </ListSearchPanel>
    <Card :body-style="{ padding: 0 }" :bordered="false" class="admin-table-card">
      <Table bordered class="admin-data-table" :columns="columns" :data-source="rows" :loading="loading" :pagination="pagination" :row-selection="batchDeletePermission ? { selectedRowKeys, onChange: (keys: (number | string)[]) => selectedRowKeys = keys.map(Number) } : undefined" row-key="id" @change="changePage">
        <template #bodyCell="{ column, text }">
          <Tag v-if="column.dataIndex === 'succeeded'" :color="text ? 'green' : 'red'">{{ text ? '成功' : '失败' }}</Tag>
          <span v-else-if="isDateTimeField(String(column.dataIndex ?? ''))">{{ formatBeijingDateTime(text) }}</span>
          <code v-else-if="column.dataIndex === 'changes' || column.dataIndex === 'context'">{{ JSON.stringify(text) }}</code>
        </template>
      </Table>
    </Card>
  </Page>
</template>
