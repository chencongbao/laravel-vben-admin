<script lang="ts" setup>
import type { Dayjs } from 'dayjs';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';

import {
  Card,
  Descriptions,
  DescriptionsItem,
  Input,
  InputNumber,
  Modal,
  RangePicker,
  Select,
  Spin,
  Table,
  Tag,
  Tooltip,
} from 'ant-design-vue';

import { getCollection, getResource, getResourceDetail } from '#/api/system';
import AutoRefresh from '#/components/system/auto-refresh.vue';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import TableExportButton from '#/components/system/table-export-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';
import { useAdminTableScrollY } from '#/utils/table';

interface AuditActor { id: number; name?: null | string; username?: null | string }
interface AuditLog {
  action?: null | string;
  action_label_key?: null | string;
  action_type: 'created' | 'deleted' | 'other' | 'updated';
  actor?: AuditActor | null;
  actor_id?: null | number;
  changed_count: number;
  changes?: Record<string, any>;
  context?: Record<string, any>;
  created_at: string;
  description?: null | string;
  field_label_keys?: Record<string, string>;
  id: number;
  ip_address?: null | string;
  method?: null | string;
  path?: null | string;
  request_id?: null | string;
  request_input?: Record<string, any>;
  route_name?: null | string;
  subject_id?: null | number;
  subject_type?: null | string;
  subject_type_name?: null | string;
  subject_label_key?: null | string;
  user_agent?: null | string;
}
interface ChangeRow { after: any; before: any; field: string }
interface Operator { id: number; name?: null | string; username: string }
interface ActionOption { code: string; label_key?: null | string; module?: null | string; type?: null | string }

const loading = ref(false);
const rows = ref<AuditLog[]>([]);
const detail = ref<AuditLog>();
const detailLoading = ref(false);
const detailOpen = ref(false);
const dateRange = ref<[Dayjs, Dayjs]>();
const actions = ref<ActionOption[]>([]);
const operators = ref<Operator[]>([]);
const filters = reactive({ action: '', action_type: '', actor_id: undefined as number | undefined, id: undefined as number | undefined, ip_address: '', subject_id: undefined as number | undefined });
const pagination = reactive(createAdminPagination());
const { setTableRef, tableScrollY } = useAdminTableScrollY();

const columns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id'), width: 90 },
  { dataIndex: 'actor', title: $t('system.auditLog.fields.actor'), width: 190 },
  { dataIndex: 'action_type', title: $t('system.auditLog.fields.actionType'), width: 110 },
  { dataIndex: 'description', ellipsis: true, title: $t('system.auditLog.fields.description'), width: 220 },
  { dataIndex: 'subject', title: $t('system.auditLog.fields.subject'), width: 180 },
  { dataIndex: 'ip_address', title: $t('system.auditLog.fields.ipAddress'), width: 140 },
  { dataIndex: 'created_at', title: $t('system.auditLog.fields.createdAt'), width: 170 },
  { dataIndex: 'action_button', fixed: 'right' as const, title: $t('system.auditLog.fields.actions'), width: 70 },
]);
const changeColumns = computed(() => [
  { dataIndex: 'field', title: $t('system.auditLog.detail.field'), width: 220 },
  { dataIndex: 'before', title: $t('system.auditLog.detail.before') },
  { dataIndex: 'after', title: $t('system.auditLog.detail.after') },
]);
const changeRows = computed(() => collectChangeRows(detail.value?.changes ?? {}));

function actionLabel(record: AuditLog | Record<string, any>) {
  const code = record.action || ('code' in record ? record.code : undefined);
  const key = record.action_label_key || ('label_key' in record ? record.label_key : undefined) || actions.value.find((item) => item.code === code)?.label_key;
  return translatedLabel(key, code || record.description || '-');
}
function actionTypeLabel(type: AuditLog['action_type']) { return $t(`system.auditLog.actionTypes.${type}`); }
function actionTypeColor(type: AuditLog['action_type']) { return { created: 'green', deleted: 'red', other: 'default', updated: 'orange' }[type]; }
function actorLabel(record: AuditLog | Record<string, any>) {
  const actor = record.actor;
  if (!actor) return record.actor_id ? `#${record.actor_id}` : '-';
  const identity = actor.name && actor.username ? `${actor.name}（${actor.username}）` : (actor.name || actor.username || '');
  return `${identity || $t('system.auditLog.detail.unknownActor')} #${actor.id}`;
}
function subjectLabel(record: AuditLog | Record<string, any>) {
  if (!record.subject_type_name || !record.subject_id) return '-';
  const type = translatedLabel(record.subject_label_key, record.subject_type_name);
  return `${type} #${record.subject_id}`;
}
function translatedLabel(key: null | string | undefined, fallback: string) {
  if (!key) return fallback;
  const translated = $t(key);
  return translated === key ? fallback : translated;
}
function changeFieldLabel(field: string) {
  const directKey = detail.value?.field_label_keys?.[field];
  const leaf = field.split('.').pop() || field;
  return translatedLabel(directKey || detail.value?.field_label_keys?.[leaf], field);
}
function displayValue(value: any) {
  if (value === undefined) return '-';
  if (value === null) return 'null';
  if (typeof value === 'string') return value;
  return JSON.stringify(value, null, 2);
}
function isPlainRecord(value: any): value is Record<string, any> { return value !== null && typeof value === 'object' && !Array.isArray(value); }
function pairedRows(before: Record<string, any>, after: Record<string, any>, prefix: string): ChangeRow[] {
  return [...new Set([...Object.keys(before), ...Object.keys(after)])]
    .filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
    .map((key) => ({ after: after[key], before: before[key], field: prefix ? `${prefix}.${key}` : key }));
}
function collectChangeRows(value: Record<string, any>, prefix = ''): ChangeRow[] {
  if (!value || typeof value !== 'object') return [];
  if ('old' in value || 'attributes' in value) return pairedRows(value.old ?? {}, value.attributes ?? {}, prefix);
  if ('before' in value || 'after' in value) {
    const before = value.before;
    const after = value.after;
    if (isPlainRecord(before) || isPlainRecord(after)) return pairedRows(before ?? {}, after ?? {}, prefix);
    return [{ after, before, field: prefix || '-' }];
  }
  return Object.entries(value).flatMap(([key, child]) => {
    const field = prefix ? `${prefix}.${key}` : key;
    return isPlainRecord(child) ? collectChangeRows(child, field) : [{ after: child, before: undefined, field }];
  });
}

async function load() {
  loading.value = true;
  try {
    const params: Record<string, any> = { ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== undefined)), page: pagination.current, per_page: pagination.pageSize };
    if (dateRange.value) {
      params.started_at = dateRange.value[0].startOf('day').format('YYYY-MM-DD HH:mm:ss');
      params.ended_at = dateRange.value[1].endOf('day').format('YYYY-MM-DD HH:mm:ss');
    }
    const result = await getResource('/system/audit-logs', params);
    rows.value = result.data as AuditLog[];
    pagination.total = result.total;
  } finally { loading.value = false; }
}
function search() { pagination.current = 1; void load(); }
function reset() {
  Object.assign(filters, { action: '', action_type: '', actor_id: undefined, id: undefined, ip_address: '', subject_id: undefined });
  dateRange.value = undefined;
  search();
}
function changePage(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1;
  pagination.pageSize = page.pageSize ?? pagination.pageSize;
  void load();
}
async function showDetail(record: AuditLog | Record<string, any>) {
  detailOpen.value = true;
  detailLoading.value = true;
  detail.value = record as AuditLog;
  try { detail.value = await getResourceDetail<AuditLog>('/system/audit-logs', Number(record.id)); }
  finally { detailLoading.value = false; }
}
onMounted(async () => {
  const [operatorResult, actionResult] = await Promise.all([
    getCollection<{ operators: Operator[] }>('/system/audit-logs/operators'),
    getCollection<{ actions: ActionOption[] }>('/system/audit-logs/actions'),
  ]);
  operators.value = operatorResult.operators;
  actions.value = actionResult.actions;
  await load();
});
</script>

<template>
  <Page :description="$t('system.auditLogsDescription')" :title="$t('system.auditLogs')">
    <ListToolbar>
      <template #left><ListRefreshButton :loading="loading" /></template>
      <template #right>
        <AutoRefresh :loading="loading" storage-key="/system/audit-logs" />
        <TableExportButton :columns="columns" :filename="$t('system.auditLogs')" permission="system.audit.view" :rows="rows" />
      </template>
    </ListToolbar>
    <ListSearchPanel>
      <ListSearchField :label="$t('common.fields.id')"><InputNumber v-model:value="filters.id" :min="1" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.filters.actor')"><Select v-model:value="filters.actor_id" allow-clear show-search :filter-option="(input, option) => String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())" :options="operators.map((operator) => ({ label: operator.name ? `${operator.name}（${operator.username}）` : operator.username, value: operator.id }))" :placeholder="$t('system.auditLog.placeholders.actor')" class="w-full" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.filters.businessAction')"><Select v-model:value="filters.action" allow-clear show-search :filter-option="(input, option) => String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())" :options="actions.map((action) => ({ label: actionLabel(action), value: action.code }))" :placeholder="$t('system.auditLog.placeholders.businessAction')" class="w-full" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.fields.actionType')"><Select v-model:value="filters.action_type" allow-clear show-search :filter-option="(input, option) => String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())" :options="(['created', 'updated', 'deleted', 'other'] as const).map((type) => ({ label: actionTypeLabel(type), value: type }))" :placeholder="$t('system.auditLog.placeholders.actionType')" class="w-full" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.filters.subjectId')"><InputNumber v-model:value="filters.subject_id" :min="1" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.filters.ipAddress')"><Input v-model:value="filters.ip_address" allow-clear @press-enter="search" /></ListSearchField>
      <ListSearchField :label="$t('system.auditLog.filters.timeRange')"><RangePicker v-model:value="dateRange" class="w-full" /></ListSearchField>
      <template #actions>
        <PermissionButton icon="lucide:search" type="primary" @click="search">{{ $t('common.actions.search') }}</PermissionButton>
        <PermissionButton icon="lucide:rotate-ccw" @click="reset">{{ $t('common.actions.reset') }}</PermissionButton>
      </template>
    </ListSearchPanel>
    <Card :body-style="{ padding: 0 }" :bordered="false" class="admin-table-card">
      <Table :ref="setTableRef" bordered class="admin-data-table" :columns="columns" :data-source="rows" :loading="loading" :pagination="pagination" row-key="id" :scroll="{ x: 1280, y: tableScrollY }" @change="changePage">
        <template #bodyCell="{ column, record, text }">
          <span v-if="column.dataIndex === 'actor'">{{ actorLabel(record) }}</span>
          <Tag v-else-if="column.dataIndex === 'action_type'" :color="actionTypeColor(record.action_type)">{{ actionTypeLabel(record.action_type) }}</Tag>
          <Tooltip v-else-if="column.dataIndex === 'description'" :title="record.action || undefined"><span class="block truncate">{{ actionLabel(record) }}</span></Tooltip>
          <span v-else-if="column.dataIndex === 'subject'">{{ subjectLabel(record) }}</span>
          <span v-else-if="column.dataIndex === 'created_at'">{{ formatBeijingDateTime(text) }}</span>
          <PermissionButton v-else-if="column.dataIndex === 'action_button'" icon="lucide:eye" icon-only permission="system.audit.view" :tooltip="$t('system.auditLog.actions.detail')" type="text" @click="showDetail(record)" />
        </template>
      </Table>
    </Card>
    <Modal v-model:open="detailOpen" :footer="null" :title="$t('system.auditLog.detail.title', { id: detail?.id ?? '' })" :width="980" :styles="{ body: { maxHeight: '70vh', overflowY: 'auto' } }">
      <Spin v-if="detail" :spinning="detailLoading">
        <div class="space-y-5">
        <Descriptions bordered :column="2" size="small">
          <DescriptionsItem :label="$t('system.auditLog.fields.actor')">{{ actorLabel(detail) }}</DescriptionsItem>
          <DescriptionsItem :label="$t('system.auditLog.fields.createdAt')">{{ formatBeijingDateTime(detail.created_at) }}</DescriptionsItem>
          <DescriptionsItem :label="$t('system.auditLog.fields.actionType')"><Tag :color="actionTypeColor(detail.action_type)">{{ actionTypeLabel(detail.action_type) }}</Tag></DescriptionsItem>
          <DescriptionsItem :label="$t('system.auditLog.filters.actionCode')"><code>{{ detail.action || '-' }}</code></DescriptionsItem>
          <DescriptionsItem :label="$t('system.auditLog.fields.subject')">{{ subjectLabel(detail) }}</DescriptionsItem>
          <DescriptionsItem :label="$t('system.auditLog.fields.description')">{{ actionLabel(detail) }}</DescriptionsItem>
        </Descriptions>
        <section>
          <h3 class="mb-2 font-medium">{{ $t('system.auditLog.detail.changes') }}</h3>
          <Table bordered :columns="changeColumns" :data-source="changeRows" :pagination="false" row-key="field" size="small">
            <template #bodyCell="{ column, text }"><code v-if="column.dataIndex === 'field'">{{ changeFieldLabel(text) }}</code><pre v-else class="m-0 max-h-40 whitespace-pre-wrap break-all">{{ displayValue(text) }}</pre></template>
            <template #emptyText>{{ $t('system.auditLog.detail.noChanges') }}</template>
          </Table>
        </section>
        <section>
          <h3 class="mb-2 font-medium">{{ $t('system.auditLog.detail.request') }}</h3>
          <Descriptions bordered :column="2" size="small">
            <DescriptionsItem :label="$t('system.auditLog.fields.method')">{{ detail.method || '-' }}</DescriptionsItem>
            <DescriptionsItem :label="$t('system.auditLog.fields.ipAddress')">{{ detail.ip_address || '-' }}</DescriptionsItem>
            <DescriptionsItem :label="$t('system.auditLog.fields.path')" :span="2"><code>{{ detail.path || '-' }}</code></DescriptionsItem>
            <DescriptionsItem :label="$t('system.auditLog.detail.routeName')">{{ detail.route_name || '-' }}</DescriptionsItem>
            <DescriptionsItem :label="$t('system.auditLog.detail.requestId')">{{ detail.request_id || '-' }}</DescriptionsItem>
            <DescriptionsItem :label="$t('system.loginLog.fields.userAgent')" :span="2"><span class="break-all">{{ detail.user_agent || '-' }}</span></DescriptionsItem>
          </Descriptions>
        </section>
          <section><h3 class="mb-2 font-medium">{{ $t('system.auditLog.detail.requestInput') }}</h3><pre class="max-h-64 overflow-auto rounded bg-muted p-3 text-xs">{{ displayValue(detail.request_input ?? {}) }}</pre></section>
          <section><h3 class="mb-2 font-medium">{{ $t('system.auditLog.detail.context') }}</h3><pre class="max-h-64 overflow-auto rounded bg-muted p-3 text-xs">{{ displayValue(detail.context ?? {}) }}</pre></section>
        </div>
      </Spin>
    </Modal>
  </Page>
</template>
