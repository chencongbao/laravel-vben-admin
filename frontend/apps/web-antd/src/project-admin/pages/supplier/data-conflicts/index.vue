<script lang="ts" setup>
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
import { dataConflicts, detectDataConflicts, resolveDataConflict } from '#project-admin/api/dataConflicts';

const { hasAccessByCodes } = useAccess(); const rows = ref<any[]>([]); const loading = ref(false); const saving = ref(false);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 }); const filters = reactive<any>({ match_id: undefined, risk_level: undefined, status: 'open' });
const planId = ref<number>(); const selected = ref<any>(); const open = ref(false); const form = reactive({ resolution: 'keep_canonical', reason: '' });
const canDetect = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.conflict.detect']));
const canResolve = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.conflict.resolve']));
const columns = computed(() => [{ dataIndex: 'match_id', title: $t('dataConflict.fields.match') }, { dataIndex: 'data_group', title: $t('dataConflict.fields.group') }, { dataIndex: 'field_path', title: $t('dataConflict.fields.field') }, { dataIndex: 'source', title: $t('dataConflict.fields.source') }, { dataIndex: 'comparison_status', title: $t('dataConflict.fields.comparison') }, { dataIndex: 'risk_level', title: $t('dataConflict.fields.risk') }, { dataIndex: 'status', title: $t('dataConflict.fields.status') }, { key: 'actions', title: $t('dataConflict.fields.actions') , fixed: 'right' , width: 200 }]);
async function load() { loading.value = true; try { const result: any = await dataConflicts({ ...filters, page: pagination.current, per_page: pagination.pageSize }); rows.value = result.data; pagination.total = result.total; } finally { loading.value = false; } }
async function detect() {
  if (saving.value) return;
 if (!planId.value) return void message.warning($t('dataConflict.messages.planRequired')); saving.value = true; try { const result: any = await detectDataConflicts(planId.value); message.success($t('dataConflict.messages.detected', result.data)); await load(); } catch { message.error($t('dataConflict.messages.detectFailed')); } finally { saving.value = false; } }
function showResolve(record: any) { selected.value = record; form.resolution = 'keep_canonical'; form.reason = ''; open.value = true; }
async function resolve() {
  if (saving.value) return;
 if (!form.reason.trim()) return void message.warning($t('dataConflict.messages.reasonRequired')); saving.value = true; try { await resolveDataConflict(selected.value.id, { ...form, version: selected.value.version }); message.success($t('dataConflict.messages.resolved')); open.value = false; await load(); } catch { message.error($t('dataConflict.messages.resolveFailed')); } finally { saving.value = false; } }
function tableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; load(); } onMounted(load);
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { match_id: undefined, risk_level: undefined, status: 'open' }); searchFilters(); }
</script>
<template>
  <Page :title="$t('dataConflict.title')" :description="$t('dataConflict.description')">
    <Card v-if="canDetect" :title="$t('dataConflict.detect')" class="mb-4">
      <Space wrap>
        <InputNumber v-model:value="planId" :min="1" :placeholder="$t('dataConflict.fields.plan')" />
        <PermissionButton icon="lucide:check" type="primary" :loading="saving" @click="detect">
          {{ $t('dataConflict.detect') }}
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
        <ListSearchField :label="$t('dataConflict.fields.match')">
          <InputNumber
            @press-enter="searchFilters"
            v-model:value="filters.match_id"
            :min="1"
            :placeholder="$t('dataConflict.fields.match')"
          />
        </ListSearchField>
        <ListSearchField :label="$t('dataConflict.fields.risk')">
          <Select
            v-model:value="filters.risk_level"
            allow-clear
            :placeholder="$t('dataConflict.fields.risk')"
          >
            <SelectOption v-for="x in ['low', 'medium', 'high']" :key="x" :value="x">
              {{ $t(`dataConflict.risks.${x}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('dataConflict.fields.status')">
          <Select v-model:value="filters.status" allow-clear :placeholder="$t('dataConflict.fields.status')">
            <SelectOption value="open">{{ $t('dataConflict.statuses.open') }}</SelectOption>
            <SelectOption value="resolved">{{ $t('dataConflict.statuses.resolved') }}</SelectOption>
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
            <template v-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
            <template v-else-if="column.dataIndex === 'risk_level'">
              <Tag
                :color="
                  record.risk_level === 'high' ? 'red' : record.risk_level === 'medium' ? 'orange' : 'blue'
                "
              >
                {{ $t(`dataConflict.risks.${record.risk_level}`) }}
              </Tag>
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              {{ $t(`dataConflict.statuses.${record.status}`) }}
            </template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton
                icon="lucide:eye"
                v-if="canResolve && record.status === 'open'"
                type="link"
                @click="showResolve(record)"
              >
                {{ $t('dataConflict.resolve') }}
              </PermissionButton>
            </template>
          </template>
        </Table>
      </Card>
    </div>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="open"
      :confirm-loading="saving"
      :title="$t('dataConflict.resolve')"
      @ok="resolve"
    >
      <Form layout="vertical">
        <FormItem :label="$t('dataConflict.fields.resolution')">
          <Select v-model:value="form.resolution">
            <SelectOption value="keep_canonical">
              {{ $t('dataConflict.resolutions.keep_canonical') }}
            </SelectOption>
            <SelectOption value="accept_candidate_for_future_execution">
              {{ $t('dataConflict.resolutions.accept_candidate_for_future_execution') }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('dataConflict.fields.reason')" required>
          <Input.TextArea v-model:value="form.reason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
