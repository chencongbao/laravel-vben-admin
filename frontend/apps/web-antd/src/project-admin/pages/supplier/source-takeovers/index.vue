<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { Alert, Card, Descriptions, DescriptionsItem, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Space, Table, Tag, message } from 'ant-design-vue';

import { $t } from '#/locales';
import {
  activateSourceTakeover,
  approveSourceTakeover,
  cancelSourceTakeover,
  closeSourceTakeover,
  probeSourceTakeoverRecovery,
  proposeSourceTakeover,
  sourceTakeovers,
} from '#project-admin/api/sourceTakeovers';

type Action = 'activate' | 'approve' | 'cancel' | 'close' | 'probe';

const { hasAccessByCodes } = useAccess();
const dataGroups = ['base_data', 'scores', 'status', 'lineups', 'events', 'var', 'statistics', 'standings', 'commentary'];
const statuses = ['proposed', 'approved', 'activating', 'active', 'reconciling', 'closed', 'cancelled', 'failed'];
const rows = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const proposalOpen = ref(false);
const actionOpen = ref(false);
const detailOpen = ref(false);
const selected = ref<any>();
const currentAction = ref<Action>('approve');
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const filters = reactive<any>({ match_id: undefined, data_group: undefined, status: undefined });
const proposal = reactive<any>({ match_id: undefined, data_group: 'scores', reason_code: 'primary_timeout' });
const actionForm = reactive({ reason: '' });

const canPropose = computed(() => allowed('supplier.takeover.propose'));
const columns = computed(() => [
  { dataIndex: 'id', title: $t('sourceTakeover.fields.id'), width: 80 },
  { dataIndex: 'match_id', title: $t('sourceTakeover.fields.matchId'), width: 130 },
  { dataIndex: 'data_group', title: $t('sourceTakeover.fields.dataGroup'), width: 140 },
  { dataIndex: 'primary_source', title: $t('sourceTakeover.fields.primarySource'), width: 170 },
  { dataIndex: 'fallback_source', title: $t('sourceTakeover.fields.fallbackSource'), width: 170 },
  { dataIndex: 'status', title: $t('sourceTakeover.fields.status'), width: 150 },
  { key: 'progress', title: $t('sourceTakeover.fields.progress'), width: 150 },
  { key: 'generationVersion', title: $t('sourceTakeover.fields.generationVersion'), width: 130 },
  { key: 'actions', title: $t('sourceTakeover.fields.actions'), fixed: 'right', width: 310 },
]);

function allowed(code: string) {
  return hasAccessByCodes(['*']) || hasAccessByCodes([code]);
}
function statusColor(status: string) {
  return ({ proposed: 'blue', approved: 'cyan', activating: 'processing', active: 'orange', reconciling: 'purple', closed: 'green', cancelled: 'default', failed: 'red' } as Record<string, string>)[status] || 'default';
}
function availableActions(record: any): Action[] {
  if (record.status === 'proposed') return ['approve', 'cancel'];
  if (record.status === 'approved') return ['activate', 'cancel'];
  if (record.status === 'active') return record.recovery_probe_pending ? [] : ['probe'];
  if (record.status === 'reconciling') return ['close'];
  return [];
}
function canRun(action: Action) {
  const permission = ({ approve: 'supplier.takeover.approve', cancel: 'supplier.takeover.cancel', activate: 'supplier.takeover.activate', probe: 'supplier.takeover.reconcile', close: 'supplier.takeover.close' } as Record<Action, string>)[action];
  return allowed(permission);
}
async function load() {
  loading.value = true;
  try {
    const result: any = await sourceTakeovers({ ...filters, page: pagination.current, per_page: pagination.pageSize });
    rows.value = result.data;
    pagination.total = result.total;
  } finally { loading.value = false; }
}
function resetFilters() {
  Object.assign(filters, { match_id: undefined, data_group: undefined, status: undefined });
  pagination.current = 1;
  load();
}
function showProposal() {
  Object.assign(proposal, { match_id: undefined, data_group: 'scores', reason_code: 'primary_timeout' });
  proposalOpen.value = true;
}
async function submitProposal() {
  if (saving.value) return;

  if (!proposal.match_id || !proposal.data_group || !/^[a-z0-9_]{3,64}$/.test(proposal.reason_code || '')) return void message.warning($t('sourceTakeover.messages.required'));
  saving.value = true;
  try {
    await proposeSourceTakeover({ ...proposal });
    message.success($t('sourceTakeover.messages.saved'));
    proposalOpen.value = false;
    await load();
  } catch { message.error($t('sourceTakeover.messages.failed')); }
  finally { saving.value = false; }
}
function showAction(record: any, action: Action) {
  selected.value = record;
  currentAction.value = action;
  actionForm.reason = '';
  actionOpen.value = true;
}
async function submitAction() {
  if (saving.value) return;

  if (actionForm.reason.trim().length < 3) return void message.warning($t('sourceTakeover.messages.reasonRequired'));
  const payload = { expected_version: selected.value.version, reason: actionForm.reason.trim() };
  const calls: Record<Action, () => Promise<any>> = {
    approve: () => approveSourceTakeover(selected.value.id, payload),
    cancel: () => cancelSourceTakeover(selected.value.id, payload),
    activate: () => activateSourceTakeover(selected.value.id, payload),
    probe: () => probeSourceTakeoverRecovery(selected.value.id, payload),
    close: () => closeSourceTakeover(selected.value.id, payload),
  };
  saving.value = true;
  try {
    await calls[currentAction.value]();
    message.success($t('sourceTakeover.messages.saved'));
    actionOpen.value = false;
    await load();
  } catch { message.error($t('sourceTakeover.messages.failed')); }
  finally { saving.value = false; }
}
function showDetails(record: any) {
  selected.value = record;
  detailOpen.value = true;
}
function tableChange(page: any) {
  pagination.current = page.current;
  pagination.pageSize = page.pageSize;
  load();
}

onMounted(load);
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { match_id: undefined, data_group: undefined, status: undefined }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('sourceTakeover.details'), icon: 'lucide:eye', visible: Boolean(true), danger: false, onClick: () => showDetails(record), buttonProps: {"type": "link"} },
    ...availableActions(record).filter(canRun).map((action) => ({ key: action, label: $t(`sourceTakeover.actions.${action}`), icon: 'lucide:settings', visible: Boolean(true), danger: action === 'cancel', onClick: () => showAction(record, action), buttonProps: { type: 'link' as const } })),
  ];
}
</script>

<template>
  <Page :title="$t('sourceTakeover.title')" :description="$t('sourceTakeover.description')">
    <div class="source-takeovers-content">
    <Alert
      type="warning"
      show-icon
      :message="$t('sourceTakeover.help.activation')"
      :description="$t('sourceTakeover.help.recovery')"
    />
    <div class="source-takeovers-list">
      <ListToolbar>
        <template #left>
          <PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">
            {{ $t('businessUi.refresh') }}
          </PermissionButton>
          <PermissionButton
            icon="lucide:filter"
            :aria-pressed="showFilters"
            @click="showFilters = !showFilters"
          >
            {{ $t('businessUi.filter') }}
          </PermissionButton>
        </template>
        <template #right>
          <PermissionButton icon="lucide:eye" v-if="canPropose" type="primary" @click="showProposal">
            {{ $t('sourceTakeover.propose') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('sourceTakeover.fields.matchId')">
          <InputNumber
            @press-enter="searchFilters"
            v-model:value="filters.match_id"
            :min="1"
            :placeholder="$t('sourceTakeover.fields.matchId')"
          />
        </ListSearchField>
        <ListSearchField :label="$t('sourceTakeover.fields.dataGroup')">
          <Select
            v-model:value="filters.data_group"
            allow-clear
            :placeholder="$t('sourceTakeover.fields.dataGroup')"
          >
            <SelectOption v-for="item in dataGroups" :key="item" :value="item">
              {{ $t(`sourceTakeover.dataGroups.${item}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('sourceTakeover.fields.status')">
          <Select
            v-model:value="filters.status"
            allow-clear
            :placeholder="$t('sourceTakeover.fields.status')"
          >
            <SelectOption v-for="item in statuses" :key="item" :value="item">
              {{ $t(`sourceTakeover.statuses.${item}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <template #actions>
          <PermissionButton icon="lucide:search" type="primary" :loading="loading" @click="searchFilters">
            {{ $t('businessUi.search') }}
          </PermissionButton>
          <PermissionButton icon="lucide:rotate-ccw" :disabled="loading" @click="resetSearchFilters">
            {{ $t('businessUi.reset') }}
          </PermissionButton>
        </template>
      </ListSearchPanel>
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :ref="setTableRef"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="pagination"
          :scroll="{ x: 1550, y: tableScrollY }"
          row-key="id"
          @change="tableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'data_group'">
              {{ $t(`sourceTakeover.dataGroups.${record.data_group}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'primary_source'">
              {{ record.primary_source?.name || '-' }}
            </template>
            <template v-else-if="column.dataIndex === 'fallback_source'">
              {{ record.fallback_source?.name || '-' }}
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              <Tag :color="statusColor(record.status)">
                {{ $t(`sourceTakeover.statuses.${record.status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'progress'">
              {{ record.recovery_success_count || 0 }} / 3
              <span v-if="record.recovery_probe_pending">· …</span>
            </template>
            <template v-else-if="column.key === 'generationVersion'">
              {{ record.generation }} / {{ record.version }}
            </template>
            <template v-else-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </div>
    </div>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="proposalOpen"
      :confirm-loading="saving"
      :title="$t('sourceTakeover.propose')"
      @ok="submitProposal"
    >
      <Form layout="vertical">
        <FormItem :label="$t('sourceTakeover.fields.matchId')" required>
          <InputNumber v-model:value="proposal.match_id" :min="1" class="w-full" />
        </FormItem>
        <FormItem
          :label="$t('sourceTakeover.fields.dataGroup')"
          :extra="$t('sourceTakeover.help.activation')"
          required
        >
          <Select v-model:value="proposal.data_group">
            <SelectOption v-for="item in dataGroups" :key="item" :value="item">
              {{ $t(`sourceTakeover.dataGroups.${item}`) }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem
          :label="$t('sourceTakeover.fields.reasonCode')"
          :extra="$t('sourceTakeover.help.reasonCode')"
          required
        >
          <Input v-model:value="proposal.reason_code" />
        </FormItem>
      </Form>
    </Modal>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="actionOpen"
      :confirm-loading="saving"
      :title="$t(`sourceTakeover.actions.${currentAction}`)"
      @ok="submitAction"
    >
      <Alert
        v-if="currentAction === 'close'"
        class="source-takeovers-close-alert"
        type="warning"
        show-icon
        :message="$t('sourceTakeover.help.close')"
      />
      <Form layout="vertical">
        <FormItem :label="$t('sourceTakeover.fields.reason')" required>
          <Input.TextArea v-model:value="actionForm.reason" :rows="4" />
        </FormItem>
      </Form>
    </Modal>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="detailOpen"
      :footer="null"
      :title="$t('sourceTakeover.details')"
      width="760px"
    >
      <Descriptions v-if="selected" bordered :column="2" size="small">
        <DescriptionsItem :label="$t('sourceTakeover.fields.id')">{{ selected.id }}</DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.matchId')">
          {{ selected.match_id }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.primarySource')">
          {{ selected.primary_source?.name || '-' }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.fallbackSource')">
          {{ selected.fallback_source?.name || '-' }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.status')">
          {{ $t(`sourceTakeover.statuses.${selected.status}`) }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.dataGroup')">
          {{ $t(`sourceTakeover.dataGroups.${selected.data_group}`) }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.lastRun')">
          {{ selected.last_collection_run_id || '-' }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.failure')">
          {{ selected.failure_code || selected.recovery_failure_code || '-' }}
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.progress')">
          {{ selected.recovery_success_count || 0 }} / 3
        </DescriptionsItem>
        <DescriptionsItem :label="$t('sourceTakeover.fields.generationVersion')">
          {{ selected.generation }} / {{ selected.version }}
        </DescriptionsItem>
      </Descriptions>
    </Modal>
  </Page>
</template>

<style scoped>
.source-takeovers-content,
.source-takeovers-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.source-takeovers-list > :deep(.admin-list-toolbar),
.source-takeovers-list > :deep(.admin-list-search-panel) {
  margin-bottom: 0;
}

.source-takeovers-close-alert {
  margin-bottom: 16px;
}
</style>
