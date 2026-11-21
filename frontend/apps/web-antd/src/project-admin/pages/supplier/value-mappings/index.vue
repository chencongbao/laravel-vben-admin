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
import { Card, Form, FormItem, Input, Modal, Select, SelectOption, Table, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { providerValueMappings, reviewValueMapping, valueMappingOptions } from '#project-admin/api/providerValueMappings';

const options = ref<any>({ sources: [], domains: [], statuses: [], canonical_values: {} });
const rows = ref<any[]>([]); const open = ref(false); const selected = ref<any>();
const filters = reactive<any>({ source_id: undefined, domain: undefined, mapping_status: undefined });
const form = reactive<any>({ action: 'map', canonical_value: undefined, reason: '', version: 1 });
const { hasAccessByCodes } = useAccess();
const canReview = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.value-mapping.review']));
const columns = computed(() => [
  { dataIndex: 'id', title: 'ID', width: 70 }, { dataIndex: 'source', title: $t('valueMapping.source') },
  { dataIndex: 'domain', title: $t('valueMapping.domain') }, { dataIndex: 'external_id', title: $t('valueMapping.externalId') },
  { dataIndex: 'external_code', title: $t('valueMapping.externalCode') }, { dataIndex: 'external_name', title: $t('valueMapping.externalName') },
  { dataIndex: 'canonical_value', title: $t('valueMapping.canonicalValue') }, { dataIndex: 'mapping_status', title: $t('valueMapping.status') },
  { key: 'actions', title: $t('valueMapping.actions'), width: 120 , fixed: 'right' },
]);
async function load() { loading.value = true; try {  rows.value = (await providerValueMappings({ ...filters, per_page: 100 })).data;  } finally { loading.value = false; } }
function show(record: any) { selected.value = record; const values = options.value.canonical_values[record.domain] || []; Object.assign(form, { action: values.length ? 'map' : 'reject', canonical_value: record.canonical_value, reason: '', version: record.version }); open.value = true; }
async function submit() {
  if (saving.value) return;
  saving.value = true;
  try {
  if (form.action === 'map' && !form.canonical_value) { message.error($t('valueMapping.messages.canonicalRequired')); return; }
  if (form.reason.trim().length < 3) { message.error($t('valueMapping.messages.reasonRequired')); return; }
  try { await reviewValueMapping(selected.value.id, form); message.success($t('valueMapping.messages.saved')); open.value = false; await load(); } catch { message.error($t('valueMapping.messages.failed')); }
 } finally { saving.value = false; }
}
onMounted(async () => { options.value = await valueMappingOptions(); await load(); });
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const loading = ref(false);
const saving = ref(false);

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { source_id: undefined, domain: undefined, mapping_status: undefined }); searchFilters(); }
</script>

<template>
  <Page :title="$t('valueMapping.title')" :description="$t('valueMapping.description')">
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
        <ListSearchField :label="$t('valueMapping.source')">
          <Select v-model:value="filters.source_id" allow-clear :placeholder="$t('valueMapping.source')">
            <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('valueMapping.domain')">
          <Select v-model:value="filters.domain" allow-clear :placeholder="$t('valueMapping.domain')">
            <SelectOption v-for="x in options.domains" :key="x" :value="x">
              {{ $t(`valueMapping.domains.${x}`) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('valueMapping.status')">
          <Select v-model:value="filters.mapping_status" allow-clear :placeholder="$t('valueMapping.status')">
            <SelectOption v-for="x in options.statuses" :key="x" :value="x">
              {{ $t(`valueMapping.statuses.${x}`) }}
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
          :pagination="false"
          :loading="loading"
          bordered
          :columns="columns"
          :data-source="rows"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
            <template v-else-if="column.dataIndex === 'domain'">
              {{ $t(`valueMapping.domains.${record.domain}`) }}
            </template>
            <template v-else-if="column.dataIndex === 'mapping_status'">
              <Tag>{{ $t(`valueMapping.statuses.${record.mapping_status}`) }}</Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton icon="lucide:eye" v-if="canReview" type="link" @click="show(record)">
                {{ $t('valueMapping.review') }}
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
      :title="$t('valueMapping.review')"
      :confirm-loading="saving"
      @ok="submit"
    >
      <Form layout="vertical">
        <FormItem :label="$t('valueMapping.action')" required>
          <Select v-model:value="form.action">
            <SelectOption value="map" :disabled="!(options.canonical_values[selected?.domain] || []).length">
              {{ $t('valueMapping.actionsMap.map') }}
            </SelectOption>
            <SelectOption value="reject">{{ $t('valueMapping.actionsMap.reject') }}</SelectOption>
            <SelectOption value="reset">{{ $t('valueMapping.actionsMap.reset') }}</SelectOption>
          </Select>
        </FormItem>
        <p v-if="selected?.domain === 'event_subtype'" class="mb-4 text-muted-foreground">
          {{ $t('valueMapping.subtypePending') }}
        </p>
        <FormItem v-if="form.action === 'map'" :label="$t('valueMapping.canonicalValue')" required>
          <Select v-model:value="form.canonical_value">
            <SelectOption v-for="x in options.canonical_values[selected?.domain] || []" :key="x" :value="x">
              {{ x }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('valueMapping.reason')" required>
          <Input.TextArea v-model:value="form.reason" :rows="4" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
