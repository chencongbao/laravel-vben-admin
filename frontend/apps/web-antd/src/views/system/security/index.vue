<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue';

import { Page } from '@vben/common-ui';

import {
  Form,
  Input,
  InputNumber,
  Modal,
  Table,
  Tabs,
  Tag,
  message,
} from 'ant-design-vue';

import { createResource, getResource } from '#/api/system';
import ListRefreshButton from '#/components/system/list-refresh-button.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';
import { createAdminPagination } from '#/utils/pagination';

const activeTab = ref('events');
const loading = ref(false);
const events = ref<Record<string, any>[]>([]);
const blocks = ref<Record<string, any>[]>([]);
const eventPagination = reactive(createAdminPagination());
const blockPagination = reactive(createAdminPagination());
const blockModalOpen = ref(false);
const blockForm = reactive({
  duration_hours: 2,
  ip_address: '',
  reason_code: 'MANUAL_SECURITY_BLOCK',
});

const eventColumns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id') },
  { dataIndex: 'code', title: $t('system.security.fields.event') },
  { dataIndex: 'severity', title: $t('system.security.fields.severity') },
  { dataIndex: 'username', title: $t('system.security.fields.username') },
  { dataIndex: 'ip_address', title: $t('system.security.fields.ipAddress') },
  { dataIndex: 'resolved_at', title: $t('system.security.fields.status') },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
  { dataIndex: 'actions', title: $t('common.fields.actions'), width: 90 },
]);
const blockColumns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id') },
  { dataIndex: 'ip_address', title: $t('system.security.fields.ipAddress') },
  { dataIndex: 'reason_code', title: $t('system.security.fields.reason') },
  { dataIndex: 'is_automatic', title: $t('system.security.fields.source') },
  { dataIndex: 'expires_at', title: $t('system.security.fields.expiresAt') },
  { dataIndex: 'released_at', title: $t('system.security.fields.status') },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
  { dataIndex: 'actions', title: $t('common.fields.actions'), width: 90 },
]);

async function loadEvents() {
  loading.value = true;
  try {
    const result = await getResource('/system/security/events', {
      page: eventPagination.current,
      per_page: eventPagination.pageSize,
    });
    events.value = result.data;
    eventPagination.total = result.total;
  } finally {
    loading.value = false;
  }
}
async function loadBlocks() {
  loading.value = true;
  try {
    const result = await getResource('/system/security/ip-blocks', {
      page: blockPagination.current,
      per_page: blockPagination.pageSize,
    });
    blocks.value = result.data;
    blockPagination.total = result.total;
  } finally {
    loading.value = false;
  }
}
function refresh() {
  return activeTab.value === 'events' ? loadEvents() : loadBlocks();
}
function blockState(record: Record<string, any>) {
  if (record.released_at) return 'released';
  if (record.expires_at && new Date(record.expires_at).getTime() <= Date.now())
    return 'expired';
  return 'active';
}
async function resolveEvent(id: number) {
  await createResource(`/system/security/events/${id}/resolve`, {});
  message.success($t('system.security.messages.resolved'));
  await loadEvents();
}
async function releaseBlock(id: number) {
  await createResource(`/system/security/ip-blocks/${id}/release`, {});
  message.success($t('system.security.messages.released'));
  await loadBlocks();
}
async function submitBlock() {
  if (!blockForm.ip_address.trim() || !blockForm.reason_code.trim()) {
    message.error($t('system.security.messages.required'));
    return;
  }
  await createResource('/system/security/ip-blocks', { ...blockForm });
  blockModalOpen.value = false;
  blockForm.ip_address = '';
  message.success($t('system.security.messages.blocked'));
  await loadBlocks();
}
function eventPageChanged(page: { current?: number; pageSize?: number }) {
  eventPagination.current = page.current ?? 1;
  eventPagination.pageSize = page.pageSize ?? eventPagination.pageSize;
  void loadEvents();
}
function blockPageChanged(page: { current?: number; pageSize?: number }) {
  blockPagination.current = page.current ?? 1;
  blockPagination.pageSize = page.pageSize ?? blockPagination.pageSize;
  void loadBlocks();
}
onMounted(() => Promise.all([loadEvents(), loadBlocks()]));
</script>

<template>
  <Page
    :description="$t('system.security.description')"
    :title="$t('system.securityCenter')"
  >
    <ListToolbar>
      <template #left><ListRefreshButton :loading="loading" /></template>
      <template #right>
        <PermissionButton
          v-if="activeTab === 'blocks'"
          icon="lucide:shield-plus"
          permission="system.security.update"
          type="primary"
          @click="blockModalOpen = true"
        >
          {{ $t('system.security.actions.blockIp') }}
        </PermissionButton>
      </template>
    </ListToolbar>
    <Tabs v-model:active-key="activeTab" @change="refresh">
      <Tabs.TabPane key="events" :tab="$t('system.security.tabs.events')">
        <Table
          bordered
          :columns="eventColumns"
          :data-source="events"
          :loading="loading"
          :pagination="eventPagination"
          row-key="id"
          @change="eventPageChanged"
        >
          <template #bodyCell="{ column, record, text }">
            <Tag
              v-if="column.dataIndex === 'severity'"
              :color="
                text === 'critical'
                  ? 'red'
                  : text === 'warning'
                    ? 'orange'
                    : 'blue'
              "
              >{{ $t(`system.security.severities.${text}`) }}</Tag
            >
            <Tag
              v-else-if="column.dataIndex === 'resolved_at'"
              :color="text ? 'green' : 'orange'"
              >{{
                text
                  ? $t('system.security.states.resolved')
                  : $t('system.security.states.open')
              }}</Tag
            >
            <span v-else-if="column.dataIndex === 'created_at'">{{
              formatBeijingDateTime(text)
            }}</span>
            <PermissionButton
              v-else-if="column.dataIndex === 'actions' && !record.resolved_at"
              icon="lucide:check"
              icon-only
              permission="system.security.update"
              :tooltip="$t('system.security.actions.resolve')"
              @click="resolveEvent(record.id)"
            />
          </template>
        </Table>
      </Tabs.TabPane>
      <Tabs.TabPane key="blocks" :tab="$t('system.security.tabs.ipBlocks')">
        <Table
          bordered
          :columns="blockColumns"
          :data-source="blocks"
          :loading="loading"
          :pagination="blockPagination"
          row-key="id"
          @change="blockPageChanged"
        >
          <template #bodyCell="{ column, record, text }">
            <Tag v-if="column.dataIndex === 'is_automatic'">{{
              text
                ? $t('system.security.sources.automatic')
                : $t('system.security.sources.manual')
            }}</Tag>
            <Tag
              v-else-if="column.dataIndex === 'released_at'"
              :color="blockState(record) === 'active' ? 'red' : 'default'"
              >{{ $t(`system.security.states.${blockState(record)}`) }}</Tag
            >
            <span
              v-else-if="
                column.dataIndex === 'expires_at' ||
                column.dataIndex === 'created_at'
              "
              >{{
                text
                  ? formatBeijingDateTime(text)
                  : $t('system.security.states.permanent')
              }}</span
            >
            <PermissionButton
              v-else-if="
                column.dataIndex === 'actions' &&
                blockState(record) === 'active'
              "
              icon="lucide:shield-off"
              icon-only
              permission="system.security.update"
              :tooltip="$t('system.security.actions.release')"
              @click="releaseBlock(record.id)"
            />
          </template>
        </Table>
      </Tabs.TabPane>
    </Tabs>
    <Modal
      v-model:open="blockModalOpen"
      :title="$t('system.security.actions.blockIp')"
      @ok="submitBlock"
    >
      <Form layout="vertical">
        <Form.Item :label="$t('system.security.fields.ipAddress')" required
          ><Input
            v-model:value="blockForm.ip_address"
            :placeholder="$t('system.security.placeholders.ipAddress')"
        /></Form.Item>
        <Form.Item :label="$t('system.security.fields.reason')" required
          ><Input v-model:value="blockForm.reason_code"
        /></Form.Item>
        <Form.Item :label="$t('system.security.fields.durationHours')"
          ><InputNumber
            v-model:value="blockForm.duration_hours"
            :min="1"
            :max="8760"
            class="w-full"
        /></Form.Item>
      </Form>
    </Modal>
  </Page>
</template>
