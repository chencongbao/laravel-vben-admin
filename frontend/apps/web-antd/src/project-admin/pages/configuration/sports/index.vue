<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { Alert, Card, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Switch, Table, Tag, Tooltip, message } from 'ant-design-vue';
import { $t } from '#/locales';
import PermissionButton from '#/components/system/permission-button.vue';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import '#project-admin/components/business-ui.css';
import { changeSportStatus, createSport, deleteSport, getSports, updateSport, type SportRow } from '#project-admin/api/sports';

const { hasAccessByCodes } = useAccess();
const can = (action: string) => hasAccessByCodes(['*']) || hasAccessByCodes([`sport.${action}`]);
const rows = ref<SportRow[]>([]); const loading = ref(false); const saving = ref(false); const failed = ref(false);
const showFilters = ref(true); const open = ref(false); const selected = ref<SportRow>();
const filters = reactive<{ keyword: string; enabled?: number }>({ keyword: '' });
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const form = reactive({ code: '', name_zh: '', canonical_name: '', sort: 0, enabled: true });
const fieldErrors = ref<Record<string, string>>({});
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const columns = computed(() => [
  { title: $t('sport.fields.code'), dataIndex: 'code', width: 150 },
  { title: $t('sport.fields.nameZh'), dataIndex: 'name_zh', width: 130 },
  { title: $t('sport.fields.nameEn'), dataIndex: 'canonical_name', width: 150 },
  { title: $t('sport.fields.enabled'), dataIndex: 'enabled', width: 110 },
  { title: $t('sport.fields.sort'), dataIndex: 'sort', width: 90 },
  { title: $t('sport.fields.sourcesCount'), dataIndex: 'sources_count', width: 150 },
  { title: $t('sport.fields.adaptation'), key: 'adaptation', width: 210 },
  { title: $t('sport.fields.actions'), key: 'actions', fixed: 'right', width: 100 },
]);
async function load() {
  if (loading.value) return;
  loading.value = true; failed.value = false;
  try { const result = await getSports({ ...filters, page: pagination.current, per_page: pagination.pageSize }); rows.value = result.data; pagination.total = result.total; }
  catch { failed.value = true; }
  finally { loading.value = false; }
}
function search() { pagination.current = 1; void load(); }
function reset() { filters.keyword = ''; filters.enabled = undefined; search(); }
function edit(record?: SportRow) {
  selected.value = record; fieldErrors.value = {};
  Object.assign(form, record ? { code: record.code, name_zh: record.name_zh ?? '', canonical_name: record.canonical_name, sort: record.sort, enabled: record.enabled } : { code: '', name_zh: '', canonical_name: '', sort: 0, enabled: true });
  open.value = true;
}
function validate() {
  const errors: Record<string, string> = {};
  for (const field of ['code', 'name_zh', 'canonical_name'] as const) {
    if (!form[field].trim()) errors[field] = $t('sport.validation.required');
    else if (form[field].length > (field === 'code' ? 32 : 80)) errors[field] = $t('sport.validation.length');
  }
  if (form.code && !/^[a-z][a-z0-9_]*$/.test(form.code)) errors.code = $t('sport.validation.code');
  if (!Number.isInteger(form.sort) || form.sort < 0 || form.sort > 100000) errors.sort = $t('sport.validation.sort');
  fieldErrors.value = errors; return Object.keys(errors).length === 0;
}
function showError(error: any) {
  const body = error?.response?.data ?? error;
  if (body?.errors) { fieldErrors.value = Object.fromEntries(Object.entries(body.errors).map(([key, values]) => [key, (values as string[]).join(' ')])); }
  const keys: Record<string, string> = { SPORT_CODE_PROTECTED: 'codeProtected', SPORT_DELETE_PROTECTED: 'deleteProtected', SPORT_CONSTRAINT_CONFLICT: 'conflict', SPORT_WRITE_FAILED: 'saveFailed' };
  if (body?.code !== 'SPORT_VALIDATION_FAILED') message.error($t(`sport.messages.${keys[body?.code] ?? 'saveFailed'}`));
}
async function save() {
  if (saving.value || !validate()) return;
  saving.value = true;
  try {
    const data = { code: form.code, name_zh: form.name_zh, canonical_name: form.canonical_name, sort: form.sort };
    if (selected.value) await updateSport(selected.value.id, data); else await createSport({ ...data, enabled: form.enabled });
    open.value = false; message.success($t('sport.messages.saved')); await load();
  } catch (error) { showError(error); } finally { saving.value = false; }
}
async function changeStatus(record: SportRow) {
  try { await changeSportStatus(record.id, !record.enabled); message.success($t('sport.messages.saved')); await load(); } catch (error) { showError(error); }
}
async function remove(record: SportRow) {
  try { await deleteSport(record.id); if (rows.value.length === 1 && pagination.current > 1) pagination.current--; message.success($t('sport.messages.deleted')); await load(); } catch (error) { showError(error); }
}
function actions(record: SportRow): BusinessRowAction[] {
  return [
    { key: 'edit', label: $t('sport.edit'), icon: 'lucide:pencil', visible: can('update'), onClick: () => edit(record) },
    { key: 'status', label: $t(record.enabled ? 'sport.disable' : 'sport.enable'), icon: record.enabled ? 'lucide:pause' : 'lucide:play', visible: can('status'), confirmTitle: $t(record.enabled ? 'sport.prompts.disable' : 'sport.prompts.enable'), onClick: () => changeStatus(record) },
    { key: 'delete', label: $t('sport.delete'), icon: 'lucide:trash-2', visible: can('delete'), disabled: !record.deletable, danger: true, confirmTitle: $t('sport.prompts.delete'), onClick: () => remove(record) },
  ];
}
onMounted(load);
</script>
<template>
  <Page :title="$t('sport.title')" :description="$t('sport.description')">
    <div>
      <ListToolbar>
        <template #left>
          <PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">{{ $t('businessUi.refresh') }}</PermissionButton>
          <PermissionButton icon="lucide:filter" :aria-pressed="showFilters" @click="showFilters = !showFilters">{{ $t('businessUi.filter') }}</PermissionButton>
        </template>
        <template #right><PermissionButton v-if="can('create')" type="primary" icon="lucide:plus" @click="edit()">{{ $t('sport.create') }}</PermissionButton></template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('sport.fields.keyword')"><Input v-model:value="filters.keyword" :placeholder="$t('sport.placeholder')" @press-enter="search" /></ListSearchField>
        <ListSearchField :label="$t('sport.fields.enabled')"><Select v-model:value="filters.enabled" allow-clear><SelectOption :value="1">{{ $t('sport.enabled') }}</SelectOption><SelectOption :value="0">{{ $t('sport.disabled') }}</SelectOption></Select></ListSearchField>
        <template #actions><PermissionButton type="primary" icon="lucide:search" :loading="loading" @click="search">{{ $t('businessUi.search') }}</PermissionButton><PermissionButton icon="lucide:rotate-ccw" :disabled="loading" @click="reset">{{ $t('businessUi.reset') }}</PermissionButton></template>
      </ListSearchPanel>
      <Alert v-if="failed" type="error" show-icon :message="$t('sport.messages.loadFailed')" class="mb-3" />
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table :ref="setTableRef" class="business-data-table admin-data-table" bordered row-key="id" :columns="columns" :data-source="rows" :loading="loading" :scroll="{ x: 1190, y: tableScrollY }" :pagination="pagination" :locale="{ emptyText: $t('sport.empty') }" @change="(value: any) => { pagination.current = value.current; pagination.pageSize = value.pageSize; load(); }">
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'enabled'"><Tag :color="record.enabled ? 'success' : 'default'">{{ $t(record.enabled ? 'sport.enabled' : 'sport.disabled') }}</Tag></template>
            <template v-else-if="column.key === 'adaptation'"><Tag v-if="!record.supported_adapters.length">{{ $t('sport.notAdapted') }}</Tag><Tag v-for="adapter in record.supported_adapters" :key="adapter.code" color="blue">{{ adapter.label }}</Tag></template>
            <template v-else-if="column.key === 'actions'"><Tooltip :title="!record.deletable ? $t('sport.messages.deleteProtected') : undefined"><span><BusinessRowActions :actions="actions(record)" /></span></Tooltip></template>
          </template>
        </Table>
      </Card>
      <Modal v-model:open="open" :title="$t(selected ? 'sport.edit' : 'sport.create')" :width="560" :confirm-loading="saving" :ok-text="$t('sport.save')" :cancel-text="$t('businessUi.cancel')" :mask-closable="false" :keyboard="!saving" :closable="!saving" :cancel-button-props="{ disabled: saving }" class="business-modal" :style="{ maxWidth: 'calc(100vw - 32px)' }" @ok="save">
        <Form layout="vertical" @finish="save">
          <FormItem :label="$t('sport.fields.code')" required :validate-status="fieldErrors.code ? 'error' : undefined" :help="fieldErrors.code || $t(selected?.code_locked ? 'sport.codeLocked' : 'sport.codeHint')"><Input v-model:value="form.code" :disabled="selected?.code_locked || saving" :maxlength="32" @press-enter="save" /></FormItem>
          <FormItem :label="$t('sport.fields.nameZh')" required :validate-status="fieldErrors.name_zh ? 'error' : undefined" :help="fieldErrors.name_zh"><Input v-model:value="form.name_zh" :disabled="saving" :maxlength="80" @press-enter="save" /></FormItem>
          <FormItem :label="$t('sport.fields.nameEn')" required :validate-status="fieldErrors.canonical_name ? 'error' : undefined" :help="fieldErrors.canonical_name"><Input v-model:value="form.canonical_name" :disabled="saving" :maxlength="80" @press-enter="save" /></FormItem>
          <FormItem :label="$t('sport.fields.sort')" required :validate-status="fieldErrors.sort ? 'error' : undefined" :help="fieldErrors.sort || $t('sport.sortHint')"><InputNumber v-model:value="form.sort" :disabled="saving" :min="0" :max="100000" :precision="0" /></FormItem>
          <FormItem v-if="!selected" :label="$t('sport.fields.enabled')"><Switch v-model:checked="form.enabled" :disabled="saving" /></FormItem>
          <Alert type="info" show-icon :message="$t('sport.adaptationHint')" />
        </Form>
      </Modal>
    </div>
  </Page>
</template>
