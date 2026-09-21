<script lang="ts" setup>
import type { AdminNotificationRecord } from '#/api/core/notification';

import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';

import { Button, Table, Tag } from 'ant-design-vue';

import {
  getNotificationsApi,
  hideNotificationApi,
  markAllNotificationsReadApi,
  markNotificationReadApi,
} from '#/api/core/notification';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';

const loading = ref(false);
const records = ref<AdminNotificationRecord[]>([]);
const pagination = reactive(createAdminPagination());
const columns = computed(() => [
  { dataIndex: 'title', title: $t('notification.fields.title') },
  { dataIndex: 'message', title: $t('notification.fields.message') },
  {
    dataIndex: 'severity',
    title: $t('notification.fields.severity'),
    width: 110,
  },
  {
    dataIndex: 'published_at',
    title: $t('notification.fields.publishedAt'),
    width: 190,
  },
  { dataIndex: 'actions', title: $t('common.fields.actions'), width: 120 },
]);

function translated(
  key?: null | string,
  fallback = '',
  parameters: Record<string, unknown> = {},
) {
  if (!key) return fallback;
  const result = $t(key, parameters as Record<string, string | number>);
  return result === key ? fallback : result;
}

async function load() {
  loading.value = true;
  try {
    const result = await getNotificationsApi({
      page: pagination.current,
      per_page: pagination.pageSize,
    });
    records.value = result.data;
    pagination.total = result.total;
  } finally {
    loading.value = false;
  }
}

async function read(record: Record<string, any>) {
  await markNotificationReadApi(String(record.id));
  await load();
}
async function hide(record: Record<string, any>) {
  await hideNotificationApi(String(record.id));
  await load();
}
async function readAll() {
  await markAllNotificationsReadApi();
  await load();
}
function pageChanged(page: { current?: number; pageSize?: number }) {
  pagination.current = page.current ?? 1;
  pagination.pageSize = page.pageSize ?? pagination.pageSize;
  void load();
}
onMounted(load);
</script>

<template>
  <Page
    :description="$t('notification.description')"
    :title="$t('notification.title')"
  >
    <div class="mb-3 flex justify-end">
      <Button :disabled="records.length === 0" @click="readAll">
        {{ $t('notification.actions.readAll') }}
      </Button>
    </div>
    <Table
      bordered
      :columns
      :data-source="records"
      :loading
      :pagination
      row-key="id"
      @change="pageChanged"
    >
      <template #bodyCell="{ column, record, text }">
        <span
          v-if="column.dataIndex === 'title'"
          :class="{ 'font-semibold': !record.is_read }"
        >
          {{
            translated(
              record.title_key,
              record.title || $t('notification.defaultTitle'),
              record.parameters,
            )
          }}
        </span>
        <span v-else-if="column.dataIndex === 'message'">
          {{
            translated(
              record.message_key,
              record.message || '—',
              record.parameters,
            )
          }}
        </span>
        <Tag
          v-else-if="column.dataIndex === 'severity'"
          :color="
            record.severity === 'error'
              ? 'red'
              : record.severity === 'warning'
                ? 'orange'
                : 'blue'
          "
        >
          {{ $t(`notification.severities.${record.severity}`) }}
        </Tag>
        <span v-else-if="column.dataIndex === 'published_at'">{{
          formatBeijingDateTime(text)
        }}</span>
        <div v-else-if="column.dataIndex === 'actions'" class="flex gap-2">
          <Button
            v-if="!record.is_read"
            size="small"
            type="link"
            @click="read(record)"
            >{{ $t('notification.actions.read') }}</Button
          >
          <Button danger size="small" type="link" @click="hide(record)">{{
            $t('notification.actions.hide')
          }}</Button>
        </div>
      </template>
    </Table>
  </Page>
</template>
