<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';

import {
  Card,
  Input,
  InputNumber,
  Select,
  Table,
  Tag,
  Tooltip,
} from 'ant-design-vue';

import { getResource } from '#/api/system';
import AutoRefresh from '#/components/system/auto-refresh.vue';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import TableExportButton from '#/components/system/table-export-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime, isDateTimeField } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';
import { useAdminTableScrollY } from '#/utils/table';

interface LogFilter {
  key: string;
  label: string;
  options?: Array<{ label: string; value: number | string }>;
  type?: 'number' | 'text';
}

const props = defineProps<{
  columns: Array<{ dataIndex: string; title: string }>;
  description?: string;
  filters: LogFilter[];
  path: string;
  permission?: string;
  title: string;
}>();
const loading = ref(false);
const rows = ref<Record<string, any>[]>([]);
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const showFilters = ref(true);
const values = reactive<Record<string, any>>({});
const pagination = reactive(createAdminPagination());
const effectiveFilters = computed<LogFilter[]>(() =>
  props.filters.some((filter) => filter.key === 'id')
    ? props.filters
    : [
        { key: 'id', label: $t('common.fields.id'), type: 'number' },
        ...props.filters,
      ],
);

async function load() {
  loading.value = true;
  try {
    const params = Object.fromEntries(
      Object.entries(values).filter(
        ([, value]) => value !== '' && value !== undefined,
      ),
    );
    const result = await getResource(props.path, {
      ...params,
      page: pagination.current,
      per_page: pagination.pageSize,
    });
    rows.value = result.data;
    pagination.total = result.total;
  } finally {
    loading.value = false;
  }
}
function search() {
  pagination.current = 1;
  void load();
}
function reset() {
  Object.keys(values).forEach((key) => delete values[key]);
  search();
}
function changePage(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1;
  pagination.pageSize = page.pageSize ?? pagination.pageSize;
  void load();
}
onMounted(load);
</script>

<template>
  <Page :description="description" :title="title">
    <ListToolbar>
      <template #left>
        <ListRefreshButton :loading="loading" />
        <PermissionButton
          icon="lucide:filter"
          @click="showFilters = !showFilters"
          >{{ $t('common.actions.filter') }}</PermissionButton
        >
      </template>
      <template #right>
        <AutoRefresh :loading="loading" :storage-key="path" />
        <TableExportButton
          :columns="columns"
          :filename="title"
          :permission="permission"
          :rows="rows"
        />
        <slot name="actions"></slot>
      </template>
    </ListToolbar>
    <ListSearchPanel v-if="showFilters">
      <ListSearchField
        v-for="filter in effectiveFilters"
        :key="filter.key"
        :label="filter.label"
      >
        <Select
          v-if="filter.options"
          v-model:value="values[filter.key]"
          allow-clear
          show-search
          :filter-option="
            (input, option) =>
              String(option?.label ?? '')
                .toLowerCase()
                .includes(input.toLowerCase())
          "
          :options="filter.options"
          :placeholder="filter.label"
          class="w-full"
        />
        <InputNumber
          v-else-if="filter.type === 'number'"
          v-model:value="values[filter.key]"
          :min="1"
          :placeholder="filter.label"
        />
        <Input
          v-else
          v-model:value="values[filter.key]"
          allow-clear
          :placeholder="filter.label"
          @press-enter="search"
        />
      </ListSearchField>
      <template #actions>
        <PermissionButton icon="lucide:search" type="primary" @click="search">{{
          $t('common.actions.search')
        }}</PermissionButton>
        <PermissionButton icon="lucide:rotate-ccw" @click="reset">{{
          $t('common.actions.reset')
        }}</PermissionButton>
      </template>
    </ListSearchPanel>
    <Card
      :body-style="{ padding: 0 }"
      :bordered="false"
      class="admin-table-card"
    >
      <Table
        :ref="setTableRef"
        bordered
        class="admin-data-table"
        :columns="columns"
        :data-source="rows"
        :loading="loading"
        :pagination="pagination"
        row-key="id"
        :scroll="{ y: tableScrollY }"
        @change="changePage"
      >
        <template #bodyCell="{ column, record, text }">
          <slot
            v-if="column.dataIndex === 'action_button'"
            name="row-actions"
            :record="record"
          ></slot>
          <slot
            v-else-if="column.dataIndex === 'failure_code'"
            name="failure-reason"
            :record="record"
          ></slot>
          <Tag
            v-else-if="column.dataIndex === 'succeeded'"
            :color="text ? 'green' : 'red'"
          >
            {{
              text
                ? $t('system.loginLog.states.succeeded')
                : $t('system.loginLog.states.failed')
            }}
          </Tag>
          <Tooltip
            v-else-if="column.dataIndex === 'user_agent' && text"
            :title="String(text)"
          >
            <span class="block truncate">{{ text }}</span>
          </Tooltip>
          <span v-else-if="isDateTimeField(String(column.dataIndex ?? ''))">{{
            formatBeijingDateTime(text)
          }}</span>
          <code
            v-else-if="
              column.dataIndex === 'changes' || column.dataIndex === 'context'
            "
            >{{ JSON.stringify(text) }}</code
          >
        </template>
      </Table>
    </Card>
  </Page>
</template>
