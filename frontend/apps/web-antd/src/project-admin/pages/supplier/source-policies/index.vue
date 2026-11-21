<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { Card, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Space, Switch, Table, Tag, message } from 'ant-design-vue';

import { $t } from '#/locales';
import { assessMatchSources, createSourcePolicy, deleteSourcePolicy, sourcePolicies, sourcePolicyOptions, updateSourcePolicy } from '#project-admin/api/sourcePolicies';

const { hasAccessByCodes } = useAccess();
const options = ref<any>({ scope_types: [], data_groups: [], sources: [], fixed_scope_keys: {} });
const rows = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const open = ref(false);
const mode = ref<'create' | 'edit' | 'delete'>('create');
const selected = ref<any>();
const assessment = ref<any>();
const assessing = ref(false);
const assessmentForm = reactive<any>({ match_id: undefined, data_group: 'scores' });
const pagination = reactive({ current: 1, pageSize: 50, total: 0 });
const filters = reactive<any>({ scope_type: undefined, data_group: undefined, source_id: undefined });
const form = reactive<any>({ scope_type: 'global', scope_key: '*', data_group: 'base_data', source_id: undefined, priority: 1, is_enabled: true, stale_after_seconds: undefined, notes: '', reason: '', version: 1 });
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.policy.manage']));
const columns = computed(() => [
  { dataIndex: 'scope_type', title: $t('sourcePolicy.fields.scopeType') },
  { dataIndex: 'scope_key', title: $t('sourcePolicy.fields.scopeKey') },
  { dataIndex: 'data_group', title: $t('sourcePolicy.fields.dataGroup') },
  { dataIndex: 'source', title: $t('sourcePolicy.fields.source') },
  { dataIndex: 'priority', title: $t('sourcePolicy.fields.priority'), width: 100 },
  { dataIndex: 'is_enabled', title: $t('sourcePolicy.fields.enabled'), width: 100 },
  { dataIndex: 'stale_after_seconds', title: $t('sourcePolicy.fields.staleAfter'), width: 140 },
  { key: 'actions', title: $t('sourcePolicy.fields.actions'), width: 150 , fixed: 'right' },
]);
const assessmentColumns = computed(() => [
  { dataIndex: 'priority', title: $t('sourcePolicy.fields.priority'), width: 90 },
  { dataIndex: 'source', title: $t('sourcePolicy.fields.source') },
  { dataIndex: 'capability_status', title: $t('sourcePolicy.assessment.capability') },
  { dataIndex: 'availability', title: $t('sourcePolicy.assessment.availability') },
  { dataIndex: 'eligible', title: $t('sourcePolicy.assessment.eligible'), width: 100 },
  { dataIndex: 'reasons', title: $t('sourcePolicy.assessment.reasons') },
]);

async function load() {
  loading.value = true;
  try {
    const result: any = await sourcePolicies({ ...filters, page: pagination.current, per_page: pagination.pageSize });
    rows.value = result.data;
    pagination.total = result.total;
  } finally { loading.value = false; }
}
async function assess() {
  if (assessing.value) return;

  if (!assessmentForm.match_id) return void message.warning($t('sourcePolicy.assessment.matchRequired'));
  assessing.value = true;
  try {
    const result: any = await assessMatchSources(assessmentForm.match_id, assessmentForm.data_group);
    assessment.value = result.data.groups[assessmentForm.data_group];
  } catch { assessment.value = undefined; message.error($t('sourcePolicy.assessment.failed')); }
  finally { assessing.value = false; }
}
function showCreate() {
  mode.value = 'create'; selected.value = undefined;
  Object.assign(form, { scope_type: 'global', scope_key: '*', data_group: 'base_data', source_id: options.value.sources[0]?.id, priority: 1, is_enabled: true, stale_after_seconds: undefined, notes: '', reason: '', version: 1 });
  open.value = true;
}
function showEdit(record: any) {
  mode.value = 'edit'; selected.value = record;
  Object.assign(form, { ...record, notes: record.notes || '', reason: '' });
  open.value = true;
}
function showDelete(record: any) {
  mode.value = 'delete'; selected.value = record;
  Object.assign(form, { reason: '', version: record.version });
  open.value = true;
}
async function submit() {
  if (saving.value) return;

  if (!form.reason?.trim() && mode.value !== 'create') return void message.warning($t('sourcePolicy.messages.reasonRequired'));
  saving.value = true;
  try {
    if (mode.value === 'delete') await deleteSourcePolicy(selected.value.id, { reason: form.reason, version: form.version });
    else {
      const payload = { scope_type: form.scope_type, scope_key: String(form.scope_key), data_group: form.data_group, source_id: form.source_id, priority: form.priority, is_enabled: form.is_enabled, stale_after_seconds: form.stale_after_seconds ?? null, notes: form.notes?.trim() || null, ...(mode.value === 'edit' ? { reason: form.reason, version: form.version } : {}) };
      if (mode.value === 'create') await createSourcePolicy(payload);
      else await updateSourcePolicy(selected.value.id, payload);
    }
    message.success($t('sourcePolicy.messages.saved')); open.value = false; await load();
  } catch { message.error($t('sourcePolicy.messages.failed')); }
  finally { saving.value = false; }
}
function tableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; load(); }
watch(() => form.scope_type, (value) => {
  if (value === 'global' || value === 'sport') form.scope_key = options.value.fixed_scope_keys[value];
  else if (mode.value === 'create') form.scope_key = '';
});
onMounted(async () => { options.value = await sourcePolicyOptions(); await load(); });
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { scope_type: undefined, data_group: undefined, source_id: undefined }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('sourcePolicy.edit'), icon: 'lucide:pencil', visible: Boolean(canManage.value), danger: false, onClick: () => showEdit(record), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('sourcePolicy.delete'), icon: 'lucide:trash-2', visible: Boolean(canManage.value), danger: true, onClick: () => showDelete(record), buttonProps: {"type": "link"} },
  ];
}
</script>

<template>
  <Page :title="$t('sourcePolicy.title')" :description="$t('sourcePolicy.description')">
    <Card :title="$t('sourcePolicy.assessment.title')" class="mb-4">
      <Space wrap>
        <InputNumber
          v-model:value="assessmentForm.match_id"
          :min="1"
          :placeholder="$t('sourcePolicy.assessment.matchId')"
          style="width: 180px"
        />
        <Select v-model:value="assessmentForm.data_group" style="width: 190px">
          <SelectOption v-for="x in options.data_groups" :key="x" :value="x">
            {{ $t(`sourcePolicy.dataGroups.${x}`) }}
          </SelectOption>
        </Select>
        <PermissionButton icon="lucide:check" type="primary" :loading="assessing" @click="assess">
          {{ $t('sourcePolicy.assessment.run') }}
        </PermissionButton>
      </Space>
      <template v-if="assessment">
        <div class="my-4">
          <Tag
            :color="
              assessment.status === 'primary_ready'
                ? 'green'
                : assessment.status === 'fallback_recommended'
                ? 'orange'
                : 'red'
            "
          >
            {{ $t(`sourcePolicy.assessment.statuses.${assessment.status}`) }}
          </Tag>
          <span class="ml-2">
            {{ $t('sourcePolicy.assessment.scope') }}:
            {{
              assessment.resolved_scope
                ? $t(`sourcePolicy.scopeTypes.${assessment.resolved_scope.type}`)
                : '-'
            }}
          </span>
          <span class="ml-4">
            {{ $t('sourcePolicy.assessment.selected') }}: {{ assessment.selected_source?.name || '-' }}
          </span>
        </div>
        <Card class="admin-table-card" :body-style="{ padding: '0' }">
          <Table
            class="business-data-table admin-data-table"
            :ref="setTableRef"
            :scroll="{ x: 1100, y: tableScrollY }"
            :loading="loading"
            bordered
            :columns="assessmentColumns"
            :data-source="assessment.candidates"
            :pagination="false"
            row-key="policy_id"
            size="small"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
              <template v-else-if="column.dataIndex === 'eligible'">
                <Tag :color="record.eligible ? 'green' : 'red'">
                  {{ record.eligible ? $t('sourcePolicy.assessment.yes') : $t('sourcePolicy.assessment.no') }}
                </Tag>
              </template>
              <template v-else-if="column.dataIndex === 'reasons'">
                {{ record.reasons.join(', ') || '-' }}
              </template>
            </template>
          </Table>
        </Card>
      </template>
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
        <template #right>
          <PermissionButton icon="lucide:plus" v-if="canManage" type="primary" @click="showCreate">
            {{ $t('sourcePolicy.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('sourcePolicy.fields.scopeType')">
          <Select
            v-model:value="filters.scope_type"
            allow-clear
            :placeholder="$t('sourcePolicy.fields.scopeType')"
          >
            <SelectOption v-for="x in options.scope_types" :key="x" :value="x">
              {{ $t(`sourcePolicy.scopeTypes.${x}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('sourcePolicy.fields.dataGroup')">
          <Select
            v-model:value="filters.data_group"
            allow-clear
            :placeholder="$t('sourcePolicy.fields.dataGroup')"
          >
            <SelectOption v-for="x in options.data_groups" :key="x" :value="x">
              {{ $t(`sourcePolicy.dataGroups.${x}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('sourcePolicy.fields.source')">
          <Select
            v-model:value="filters.source_id"
            allow-clear
            :placeholder="$t('sourcePolicy.fields.source')"
          >
            <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
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
          :scroll="{ x: 1100 }"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="pagination"
          row-key="id"
          @change="tableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'scope_type'">
              {{ $t(`sourcePolicy.scopeTypes.${record.scope_type}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'data_group'">
              {{ $t(`sourcePolicy.dataGroups.${record.data_group}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
            <template v-else-if="column.dataIndex === 'priority'">
              <Tag :color="record.priority === 1 ? 'green' : 'orange'">
                {{
                  record.priority === 1
                    ? $t('sourcePolicy.primary')
                    : `${$t('sourcePolicy.fallback')} ${record.priority - 1}`
                }}
              </Tag>
            </template>
            <template v-else-if="column.dataIndex === 'is_enabled'">
              <Tag :color="record.is_enabled ? 'green' : 'default'">
                {{ record.is_enabled ? $t('sourcePolicy.enabled') : $t('sourcePolicy.disabled') }}
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
      v-model:open="open"
      :confirm-loading="saving"
      :title="$t(`sourcePolicy.${mode}`)"
      @ok="submit"
    >
      <Form layout="vertical">
        <template v-if="mode !== 'delete'">
          <FormItem :label="$t('sourcePolicy.fields.scopeType')" required>
            <Select v-model:value="form.scope_type">
              <SelectOption v-for="x in options.scope_types" :key="x" :value="x">
                {{ $t(`sourcePolicy.scopeTypes.${x}`) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem
            :extra="$t('sourcePolicy.help.scopeKey')"
            :label="$t('sourcePolicy.fields.scopeKey')"
            required
          >
            <Input
              v-model:value="form.scope_key"
              :disabled="form.scope_type === 'global' || form.scope_type === 'sport'"
            />
          </FormItem>
          <FormItem :label="$t('sourcePolicy.fields.dataGroup')" required>
            <Select v-model:value="form.data_group">
              <SelectOption v-for="x in options.data_groups" :key="x" :value="x">
                {{ $t(`sourcePolicy.dataGroups.${x}`) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('sourcePolicy.fields.source')" required>
            <Select v-model:value="form.source_id">
              <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">
                {{ x.name }} ({{ x.code }})
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem
            :extra="$t('sourcePolicy.help.priority')"
            :label="$t('sourcePolicy.fields.priority')"
            required
          >
            <InputNumber v-model:value="form.priority" :min="1" :max="100" class="w-full" />
          </FormItem>
          <FormItem :label="$t('sourcePolicy.fields.enabled')">
            <Switch v-model:checked="form.is_enabled" />
          </FormItem>
          <FormItem :extra="$t('sourcePolicy.help.staleAfter')" :label="$t('sourcePolicy.fields.staleAfter')">
            <InputNumber v-model:value="form.stale_after_seconds" :min="1" :max="604800" class="w-full" />
          </FormItem>
          <FormItem :label="$t('sourcePolicy.fields.notes')">
            <Input.TextArea v-model:value="form.notes" :rows="3" />
          </FormItem>
        </template>
        <FormItem v-if="mode !== 'create'" :label="$t('sourcePolicy.fields.reason')" required>
          <Input.TextArea v-model:value="form.reason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
