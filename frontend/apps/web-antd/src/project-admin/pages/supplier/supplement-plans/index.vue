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
import { Card, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Space, Table, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { createSupplementPlan, reviewSupplementPlan, supplementPlan, supplementPlans } from '#project-admin/api/supplementPlans';

const { hasAccessByCodes } = useAccess();
const rows = ref<any[]>([]); const loading = ref(false); const saving = ref(false);
const detail = ref<any>(); const detailOpen = ref(false); const reviewOpen = ref(false); const selected = ref<any>();
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const filters = reactive<any>({ match_id: undefined, data_group: undefined, status: undefined });
const createForm = reactive<any>({ match_id: undefined, data_group: 'lineups' });
const reviewForm = reactive<any>({ action: 'approve', reason: '' });
const groups = ['base_data', 'scores', 'status', 'lineups', 'events', 'var', 'statistics'];
const statuses = ['draft', 'approved', 'rejected'];
const canCreate = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.supplement.create']));
const canReview = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.supplement.review']));
const columns = computed(() => [
  { dataIndex: 'id', title: 'ID', width: 80 }, { dataIndex: 'match_id', title: $t('supplementPlan.fields.matchId') },
  { dataIndex: 'data_group', title: $t('supplementPlan.fields.dataGroup') }, { dataIndex: 'source', title: $t('supplementPlan.fields.source') },
  { dataIndex: 'resolved_scope_type', title: $t('supplementPlan.fields.scope') }, { dataIndex: 'items_count', title: $t('supplementPlan.fields.items') },
  { dataIndex: 'status', title: $t('supplementPlan.fields.status') }, { key: 'actions', title: $t('supplementPlan.fields.actions'), width: 180 , fixed: 'right' },
]);

async function load() { loading.value = true; try { const result: any = await supplementPlans({ ...filters, page: pagination.current, per_page: pagination.pageSize }); rows.value = result.data; pagination.total = result.total; } finally { loading.value = false; } }
async function createDraft() {
  if (saving.value) return;
 if (!createForm.match_id) return void message.warning($t('supplementPlan.messages.matchRequired')); saving.value = true; try { const result: any = await createSupplementPlan(createForm); message.success(result.created ? $t('supplementPlan.messages.created') : $t('supplementPlan.messages.exists')); await load(); } catch { message.error($t('supplementPlan.messages.createFailed')); } finally { saving.value = false; } }
async function showDetail(record: any) { const result: any = await supplementPlan(record.id); detail.value = result.data; detailOpen.value = true; }
function showReview(record: any) { selected.value = record; reviewForm.action = 'approve'; reviewForm.reason = ''; reviewOpen.value = true; }
async function review() {
  if (saving.value) return;
 if (!reviewForm.reason.trim()) return void message.warning($t('supplementPlan.messages.reasonRequired')); saving.value = true; try { await reviewSupplementPlan(selected.value.id, { ...reviewForm, version: selected.value.version }); message.success($t('supplementPlan.messages.reviewed')); reviewOpen.value = false; await load(); } catch { message.error($t('supplementPlan.messages.reviewFailed')); } finally { saving.value = false; } }
function tableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; load(); }
onMounted(load);
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { match_id: undefined, data_group: undefined, status: undefined }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('supplementPlan.detail'), icon: 'lucide:eye', visible: Boolean(true), danger: false, onClick: () => showDetail(record), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('supplementPlan.review'), icon: 'lucide:clipboard-check', visible: Boolean(canReview.value && record.status === 'draft'), danger: false, onClick: () => showReview(record), buttonProps: {"type": "link"} },
  ];
}
</script>

<template>
  <Page :title="$t('supplementPlan.title')" :description="$t('supplementPlan.description')">
    <Card v-if="canCreate" :title="$t('supplementPlan.create')" class="mb-4">
      <Space wrap>
        <InputNumber
          v-model:value="createForm.match_id"
          :min="1"
          :placeholder="$t('supplementPlan.fields.matchId')"
          style="width: 180px"
        />
        <Select v-model:value="createForm.data_group" style="width: 180px">
          <SelectOption v-for="x in groups" :key="x" :value="x">
            {{ $t(`sourcePolicy.dataGroups.${x}`) }}
          </SelectOption>
        </Select>
        <PermissionButton icon="lucide:plus" type="primary" :loading="saving" @click="createDraft">
          {{ $t('supplementPlan.create') }}
        </PermissionButton>
      </Space>
    </Card>
    <div>
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
        <template #right></template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('supplementPlan.fields.matchId')">
          <InputNumber
            @press-enter="searchFilters"
            v-model:value="filters.match_id"
            :min="1"
            :placeholder="$t('supplementPlan.fields.matchId')"
          />
        </ListSearchField>
        <ListSearchField :label="$t('supplementPlan.fields.dataGroup')">
          <Select
            v-model:value="filters.data_group"
            allow-clear
            :placeholder="$t('supplementPlan.fields.dataGroup')"
          >
            <SelectOption v-for="x in groups" :key="x" :value="x">
              {{ $t(`sourcePolicy.dataGroups.${x}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('supplementPlan.fields.status')">
          <Select
            v-model:value="filters.status"
            allow-clear
            :placeholder="$t('supplementPlan.fields.status')"
          >
            <SelectOption v-for="x in statuses" :key="x" :value="x">
              {{ $t(`supplementPlan.statuses.${x}`) }}
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
          :scroll="{ x: 1100, y: tableScrollY }"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="pagination"
          row-key="id"
          @change="tableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'data_group'">
              {{ $t(`sourcePolicy.dataGroups.${record.data_group}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
            <template v-else-if="column.dataIndex === 'resolved_scope_type'">
              {{ $t(`sourcePolicy.scopeTypes.${record.resolved_scope_type}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              <Tag
                :color="
                  record.status === 'approved' ? 'green' : record.status === 'rejected' ? 'red' : 'orange'
                "
              >
                {{ $t(`supplementPlan.statuses.${record.status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </div>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="detailOpen"
      :footer="null"
      :title="$t('supplementPlan.detail')"
      width="800px"
    >
      <Table
        class="business-data-table admin-data-table"
        :scroll="{ x: 1100 }"
        :loading="loading"
        bordered
        v-if="detail"
        :data-source="detail.items"
        :pagination="false"
        row-key="id"
        size="small"
      >
        <Table.Column data-index="field_path" :title="$t('supplementPlan.fields.fieldPath')" />
        <Table.Column :title="$t('supplementPlan.fields.value')">
          <template #default="{ record }">
            <pre class="max-w-lg whitespace-pre-wrap">{{
              JSON.stringify(record.candidate_value, null, 2)
            }}</pre>
          </template>
        </Table.Column>
        <Table.Column data-index="raw_record_id" :title="$t('businessUi.rawRecordId')" />
      </Table>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="reviewOpen"
      :confirm-loading="saving"
      :title="$t('supplementPlan.review')"
      @ok="review"
    >
      <Form layout="vertical">
        <FormItem :label="$t('supplementPlan.fields.action')">
          <Select v-model:value="reviewForm.action">
            <SelectOption value="approve">{{ $t('supplementPlan.approve') }}</SelectOption>
            <SelectOption value="reject">{{ $t('supplementPlan.reject') }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('supplementPlan.fields.reason')" required>
          <Input.TextArea v-model:value="reviewForm.reason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
