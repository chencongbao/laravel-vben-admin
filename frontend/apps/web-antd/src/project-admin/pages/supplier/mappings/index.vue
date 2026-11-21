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
import { bindMapping, createMapping, detectSupplierMatchConflicts, executeCommentaryCandidate, executeEventCandidate, executeLineupCandidate, executeScoreStatusCandidate, executeStandingCandidate, executeStatisticCandidate, mappingCandidates, mappingComparison, mappingOptions, providerMappings, rejectMapping, resolveSupplierMatchConflict, shadowComparisons, supplierMatchConflicts, unbindMapping } from '#project-admin/api/providerMappings';

const options = ref<any>({ sources: [], entity_types: [], statuses: [] });
const rows = ref<any[]>([]);
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const filters = reactive<any>({ sport_code: undefined, source_id: undefined, entity_type: undefined, mapping_status: undefined, keyword: '' });
const open = ref(false);
const mode = ref<'create' | 'inspect' | 'bind' | 'reject' | 'unbind'>('create');
const selected = ref<any>();
const candidates = ref<any[]>([]);
const comparison = ref<any>();
const comparisonLoading = ref(false);
const form = reactive<any>({ candidate_data: '{}', reason: '' });
const shadowLoading = ref(false);
const shadowReport = ref<any>();
const shadowFilters = reactive<any>({ primary_source_id: undefined, shadow_source_id: undefined });
const shadowPagination = reactive({ current: 1, pageSize: 20, total: 0 });
const executeOpen = ref(false);
const executing = ref(false);
const executeForm = reactive<any>({ reason: '', version: 1, expected_match_revision: 1 });
const standingOpen = ref(false);
const standingForm = reactive<any>({ reason: '', version: 1, expected_table_revision: 0 });
const commentaryOpen = ref(false);
const commentaryForm = reactive<any>({ reason: '', version: 1, expected_match_revision: 1 });
const eventOpen = ref(false);
const eventForm = reactive<any>({ reason: '', version: 1, expected_match_revision: 1, event_overrides: '{}' });
const lineupOpen = ref(false);
const lineupForm = reactive<any>({ reason: '', version: 1, expected_match_revision: 1 });
const scoreStatusOpen = ref(false);
const scoreStatusForm = reactive<any>({ reason: '', version: 1, expected_match_revision: 1, scores_confirmed: false });
const matchConflicts = ref<any[]>([]);
const conflictLoading = ref(false);
const conflictOpen = ref(false);
const conflictForm = reactive<any>({ resolution: 'keep_canonical', reason: '', version: 1 });
const { hasAccessByCodes } = useAccess();
const canExecuteStatistics = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.statistics.execute']));
const canExecuteStanding = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.standing.execute']));
const canExecuteCommentary = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.commentary.execute']));
const canExecuteEvents = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.event.execute']));
const canExecuteLineups = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.lineup.execute']));
const canExecuteScoreStatus = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.mapping.score-status.execute']));
const canDetectMatchConflicts = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.match-conflict.detect']));
const canResolveMatchConflicts = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.match-conflict.resolve']));
const primarySources = computed(() => options.value.sources.filter((item: any) => item.adapter === 'sportmonks'));
const shadowSources = computed(() => options.value.sources.filter((item: any) => item.adapter === 'goalserve'));
const columns = computed(() => [
  { dataIndex: 'id', title: 'ID', width: 70 },
  { dataIndex: 'source', title: $t('mapping.source') },
  { dataIndex: 'sport_code', title: $t('mapping.sport') },
  { dataIndex: 'entity_type', title: $t('mapping.entityType') },
  { dataIndex: 'external_id', title: $t('mapping.externalId') },
  { dataIndex: 'internal_id', title: $t('mapping.internalEntity') },
  { dataIndex: 'mapping_status', title: $t('mapping.status') },
  { dataIndex: 'confidence', title: $t('mapping.confidence') },
  { key: 'actions', title: $t('mapping.actions'), width: 320 , fixed: 'right' },
]);
const comparisonColumns = computed(() => [
  { dataIndex: 'candidate_field', title: $t('mapping.candidateField') },
  { dataIndex: 'candidate_value', title: $t('mapping.candidateValue') },
  { dataIndex: 'canonical_field', title: $t('mapping.canonicalField') },
  { dataIndex: 'canonical_value', title: $t('mapping.canonicalValue') },
  { dataIndex: 'status', title: $t('mapping.comparisonStatus'), width: 150 },
]);
const shadowColumns = computed(() => [
  { dataIndex: 'internal_match_id', title: $t('mapping.shadow.internalMatchId'), width: 120 },
  { dataIndex: 'pair_status', title: $t('mapping.shadow.pairStatus'), width: 120 },
  { key: 'external_ids', title: $t('mapping.shadow.externalIds') },
  { key: 'name', title: $t('mapping.shadow.nameComparison'), width: 130 },
  { key: 'score', title: $t('mapping.shadow.scoreComparison'), width: 130 },
  { key: 'state', title: $t('mapping.shadow.stateComparison'), width: 130 },
  { key: 'coverage', title: $t('mapping.shadow.coverage') },
  { key: 'delta', title: $t('mapping.shadow.collectionDelta'), width: 150 },
]);
const conflictColumns = computed(() => [
  { dataIndex: 'match_id', title: $t('mapping.matchConflict.matchId'), width: 100 },
  { dataIndex: 'field', title: $t('mapping.matchConflict.field'), width: 90 },
  { key: 'values', title: $t('mapping.matchConflict.values') },
  { dataIndex: 'occurrences', title: $t('mapping.matchConflict.occurrences'), width: 90 },
  { dataIndex: 'status', title: $t('mapping.status'), width: 100 },
  { key: 'conflict_actions', title: $t('mapping.actions'), width: 100 },
]);

async function load() { loading.value = true; try {
  const result: any = await providerMappings({ ...filters, page: pagination.current, per_page: pagination.pageSize });
  rows.value = result.data;
  pagination.total = result.total;
 } finally { loading.value = false; } }
function reset() {
  Object.assign(filters, { sport_code: undefined, source_id: undefined, entity_type: undefined, mapping_status: undefined, keyword: '' });
  pagination.current = 1;
  load();
}
function showCreate() {
  mode.value = 'create'; selected.value = undefined;
  Object.assign(form, { source_id: undefined, entity_type: undefined, external_id: '', raw_record_id: undefined, confidence: undefined, candidate_data: '{}', mapping_status: 'pending', reason: '' });
  open.value = true;
}
async function showReview(nextMode: 'bind' | 'reject' | 'unbind', record: any) {
  mode.value = nextMode; selected.value = record;
  Object.assign(form, { internal_id: record.internal_id, reason: '', version: record.version });
  candidates.value = [];
  if (nextMode === 'bind') candidates.value = (await mappingCandidates({ entity_type: record.entity_type })).data;
  comparison.value = undefined;
  if (record.internal_id) await loadComparison(record.internal_id);
  open.value = true;
}
async function showComparison(record: any) {
  mode.value = 'inspect'; selected.value = record; comparison.value = undefined; open.value = true;
  await loadComparison(record.internal_id);
}
async function loadComparison(internalId?: number) {
  if (!selected.value) return;
  comparisonLoading.value = true;
  try { comparison.value = (await mappingComparison(selected.value.id, internalId ? { internal_id: internalId } : undefined)).data; }
  catch { message.error($t('mapping.messages.comparisonFailed')); }
  finally { comparisonLoading.value = false; }
}
async function submit() {
 if (saving.value) return;
 saving.value = true;
 try {
  try {
    if (mode.value === 'create') await createMapping({ ...form, candidate_data: JSON.parse(form.candidate_data) });
    else if (mode.value === 'bind') await bindMapping(selected.value.id, { internal_id: form.internal_id, reason: form.reason, version: form.version });
    else if (mode.value === 'reject') await rejectMapping(selected.value.id, { reason: form.reason, version: form.version });
    else await unbindMapping(selected.value.id, { reason: form.reason, version: form.version });
    message.success($t('mapping.messages.saved')); open.value = false; await load();
  } catch { message.error($t('mapping.messages.failed')); }
 } finally { saving.value = false; }
}
function showStatisticExecution(record: any) {
  selected.value = record;
  Object.assign(executeForm, { reason: '', version: record.version, expected_match_revision: record.canonical_revision });
  executeOpen.value = true;
}
async function submitStatisticExecution() {
  if (executing.value) return;

  if (executeForm.reason.trim().length < 3) return void message.error($t('mapping.statisticsExecution.reasonRequired'));
  executing.value = true;
  try {
    await executeStatisticCandidate(selected.value.id, executeForm);
    message.success($t('mapping.statisticsExecution.saved'));
    executeOpen.value = false;
    await load();
  } catch {
    message.error($t('mapping.statisticsExecution.failed'));
  } finally {
    executing.value = false;
  }
}
function showStandingExecution(record: any) { selected.value = record; Object.assign(standingForm, { reason: '', version: record.version, expected_table_revision: record.standing_table_revision }); standingOpen.value = true; }
async function submitStandingExecution() {
  if (executing.value) return;

  if (standingForm.reason.trim().length < 3) return void message.error($t('mapping.standingExecution.reasonRequired'));
  executing.value = true;
  try { await executeStandingCandidate(selected.value.id, standingForm); message.success($t('mapping.standingExecution.saved')); standingOpen.value = false; await load(); }
  catch { message.error($t('mapping.standingExecution.failed')); }
  finally { executing.value = false; }
}
function showCommentaryExecution(record: any) { selected.value = record; Object.assign(commentaryForm, { reason: '', version: record.version, expected_match_revision: record.commentary_match_revision }); commentaryOpen.value = true; }
async function submitCommentaryExecution() {
  if (executing.value) return;

  if (commentaryForm.reason.trim().length < 3) return void message.error($t('mapping.commentaryExecution.reasonRequired'));
  executing.value = true;
  try { await executeCommentaryCandidate(selected.value.id, commentaryForm); message.success($t('mapping.commentaryExecution.saved')); commentaryOpen.value = false; await load(); }
  catch { message.error($t('mapping.commentaryExecution.failed')); }
  finally { executing.value = false; }
}
function showEventExecution(record: any) { selected.value = record; Object.assign(eventForm, { reason: '', version: record.version, expected_match_revision: record.event_match_revision, event_overrides: '{}' }); eventOpen.value = true; }
async function submitEventExecution() {
  if (executing.value) return;

  if (eventForm.reason.trim().length < 3) return void message.error($t('mapping.eventExecution.reasonRequired'));
  let overrides: any;
  try { overrides = JSON.parse(eventForm.event_overrides || '{}'); } catch { return void message.error($t('mapping.eventExecution.invalidOverrides')); }
  executing.value = true;
  try { await executeEventCandidate(selected.value.id, { ...eventForm, event_overrides: overrides }); message.success($t('mapping.eventExecution.saved')); eventOpen.value = false; await load(); }
  catch { message.error($t('mapping.eventExecution.failed')); }
  finally { executing.value = false; }
}
function showLineupExecution(record: any) { selected.value = record; Object.assign(lineupForm, { reason: '', version: record.version, expected_match_revision: record.lineup_match_revision }); lineupOpen.value = true; }
async function submitLineupExecution() {
  if (executing.value) return;

  if (lineupForm.reason.trim().length < 3) return void message.error($t('mapping.lineupExecution.reasonRequired'));
  executing.value = true;
  try { await executeLineupCandidate(selected.value.id, lineupForm); message.success($t('mapping.lineupExecution.saved')); lineupOpen.value = false; await load(); }
  catch { message.error($t('mapping.lineupExecution.failed')); }
  finally { executing.value = false; }
}
function showScoreStatusExecution(record: any) { selected.value = record; Object.assign(scoreStatusForm, { reason: '', version: record.version, expected_match_revision: record.score_status_match_revision, scores_confirmed: false }); scoreStatusOpen.value = true; }
async function submitScoreStatusExecution() {
  if (executing.value) return;

  if (scoreStatusForm.reason.trim().length < 3) return void message.error($t('mapping.scoreStatusExecution.reasonRequired'));
  executing.value = true;
  try { await executeScoreStatusCandidate(selected.value.id, scoreStatusForm); message.success($t('mapping.scoreStatusExecution.saved')); scoreStatusOpen.value = false; await load(); }
  catch { message.error($t('mapping.scoreStatusExecution.failed')); }
  finally { executing.value = false; }
}
function tableChange(page: any) { pagination.current = page.current; pagination.pageSize = page.pageSize; load(); }
async function loadShadowReport() {
  if (!shadowFilters.primary_source_id || !shadowFilters.shadow_source_id) return void message.warning($t('mapping.shadow.selectSources'));
  shadowLoading.value = true;
  try {
    shadowReport.value = await shadowComparisons({ ...shadowFilters, page: shadowPagination.current, per_page: shadowPagination.pageSize });
    shadowPagination.total = shadowReport.value.pagination.total;
    await loadMatchConflicts();
  } catch { message.error($t('mapping.shadow.loadFailed')); }
  finally { shadowLoading.value = false; }
}
async function loadMatchConflicts() {
  if (!shadowFilters.primary_source_id || !shadowFilters.shadow_source_id) return;
  const result: any = await supplierMatchConflicts({ primary_source_id: shadowFilters.primary_source_id, shadow_source_id: shadowFilters.shadow_source_id, per_page: 100 });
  matchConflicts.value = result.data;
}
async function detectMatchConflicts() {
 if (conflictLoading.value) return;

  if (!shadowFilters.primary_source_id || !shadowFilters.shadow_source_id) return void message.warning($t('mapping.shadow.selectSources'));
  conflictLoading.value = true;
  try { const result: any = await detectSupplierMatchConflicts({ primary_source_id: shadowFilters.primary_source_id, shadow_source_id: shadowFilters.shadow_source_id }); message.success($t('mapping.matchConflict.detected', result.data)); await loadMatchConflicts(); }
  catch { message.error($t('mapping.matchConflict.failed')); }
  finally { conflictLoading.value = false; }
}
function showConflictResolution(record: any) { selected.value = record; Object.assign(conflictForm, { resolution: 'keep_canonical', reason: '', version: record.version }); conflictOpen.value = true; }
async function submitConflictResolution() {
 if (conflictLoading.value) return;

  if (conflictForm.reason.trim().length < 3) return void message.error($t('mapping.matchConflict.reasonRequired'));
  conflictLoading.value = true;
  try { await resolveSupplierMatchConflict(selected.value.id, conflictForm); message.success($t('mapping.matchConflict.resolved')); conflictOpen.value = false; await loadMatchConflicts(); }
  catch { message.error($t('mapping.matchConflict.failed')); }
  finally { conflictLoading.value = false; }
}
function shadowTableChange(page: any) { shadowPagination.current = page.current; shadowPagination.pageSize = page.pageSize; loadShadowReport(); }
function comparisonColor(status?: string) { return status === 'same' || status === 'paired' ? 'green' : status === 'different' ? 'red' : 'orange'; }
watch(() => form.entity_type, () => { form.internal_id = undefined; });
watch(() => form.internal_id, (value) => { if (mode.value === 'bind' && selected.value) loadComparison(value); });
onMounted(async () => {
  options.value = await mappingOptions();
  shadowFilters.primary_source_id = options.value.sources.find((x: any) => x.adapter === 'sportmonks')?.id;
  shadowFilters.shadow_source_id = options.value.sources.find((x: any) => x.adapter === 'goalserve')?.id;
  await load();
});
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const loading = ref(false);
const saving = ref(false);

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { sport_code: undefined, source_id: undefined, entity_type: undefined, mapping_status: undefined, keyword: '' }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('mapping.compare'), icon: 'lucide:git-compare', visible: Boolean(!['standing', 'commentary'].includes(record.entity_type)), danger: false, onClick: () => showComparison(record), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('mapping.bind'), icon: 'lucide:clipboard-check', visible: Boolean(!['standing', 'commentary'].includes(record.entity_type)), danger: false, onClick: () => showReview('bind', record), buttonProps: {"type": "link"} },
    { key: 'action-2', label: $t('mapping.reject'), icon: 'lucide:clipboard-check', visible: Boolean(true), danger: true, onClick: () => showReview('reject', record), buttonProps: {"type": "link"} },
    { key: 'action-3', label: $t('mapping.unbind'), icon: 'lucide:clipboard-check', visible: Boolean(record.mapping_status === 'matched' && !['standing', 'commentary'].includes(record.entity_type)), danger: false, onClick: () => showReview('unbind', record), buttonProps: {"type": "link"} },
    { key: 'action-4', label: $t('mapping.scoreStatusExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_score_status && canExecuteScoreStatus.value), danger: false, onClick: () => showScoreStatusExecution(record), buttonProps: {"type": "link"} },
    { key: 'action-5', label: $t('mapping.lineupExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_lineups && canExecuteLineups.value), danger: false, onClick: () => showLineupExecution(record), buttonProps: {"type": "link"} },
    { key: 'action-6', label: $t('mapping.eventExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_events && canExecuteEvents.value), danger: false, onClick: () => showEventExecution(record), buttonProps: {"type": "link"} },
    { key: 'action-7', label: $t('mapping.statisticsExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_statistics && canExecuteStatistics.value), danger: false, onClick: () => showStatisticExecution(record), buttonProps: {"type": "link"} },
    { key: 'action-8', label: $t('mapping.standingExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_standing && canExecuteStanding.value), danger: false, onClick: () => showStandingExecution(record), buttonProps: {"type": "link"} },
    { key: 'action-9', label: $t('mapping.commentaryExecution.action'), icon: 'lucide:play', visible: Boolean(record.can_execute_commentary && canExecuteCommentary.value), danger: false, onClick: () => showCommentaryExecution(record), buttonProps: {"type": "link"} },
  ];
}
</script>

<template>
  <Page :title="$t('mapping.title')" :description="$t('mapping.description')">
    <div class="mappings-content">
    <div class="mappings-list">
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
          <PermissionButton icon="lucide:plus" type="primary" @click="showCreate">
            {{ $t('mapping.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('mapping.sport')">
          <Select v-model:value="filters.sport_code" allow-clear :placeholder="$t('mapping.sport')">
            <SelectOption v-for="x in options.sports" :key="x.code" :value="x.code">
              {{ x.canonical_name }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('mapping.source')">
          <Select v-model:value="filters.source_id" allow-clear :placeholder="$t('mapping.source')">
            <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('mapping.entityType')">
          <Select v-model:value="filters.entity_type" allow-clear :placeholder="$t('mapping.entityType')">
            <SelectOption v-for="x in options.entity_types" :key="x" :value="x">{{ x }}</SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('mapping.status')">
          <Select v-model:value="filters.mapping_status" allow-clear :placeholder="$t('mapping.status')">
            <SelectOption v-for="x in options.statuses" :key="x" :value="x">{{ x }}</SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('mapping.externalId')">
          <Input
            v-model:value="filters.keyword"
            allow-clear
            :placeholder="$t('mapping.externalId')"
            @press-enter="searchFilters"
          />
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
          :scroll="{ x: 1500, y: Math.min(tableScrollY, 480) }"
          :loading="loading"
          bordered
          :columns="columns"
          :data-source="rows"
          :pagination="pagination"
          row-key="id"
          @change="tableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'source'">{{ record.source?.name }}</template>
            <template v-else-if="column.dataIndex === 'mapping_status'">
              <Tag
                :color="
                  record.mapping_status === 'matched'
                    ? 'green'
                    : record.mapping_status === 'conflict'
                    ? 'red'
                    : 'orange'
                "
              >
                {{ record.mapping_status }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
            </template>
          </template>
        </Table>
      </Card>
    </div>
    <Card :title="$t('mapping.shadow.title')">
      <div class="mappings-shadow-content">
      <Space wrap :size="16">
        <Select
          v-model:value="shadowFilters.primary_source_id"
          :placeholder="$t('mapping.shadow.primarySource')"
          style="width: 220px"
        >
          <SelectOption v-for="x in primarySources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
        </Select>
        <Select
          v-model:value="shadowFilters.shadow_source_id"
          :placeholder="$t('mapping.shadow.shadowSource')"
          style="width: 220px"
        >
          <SelectOption v-for="x in shadowSources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
        </Select>
        <PermissionButton
          icon="lucide:check"
          type="primary"
          :loading="shadowLoading"
          @click="loadShadowReport"
        >
          {{ $t('mapping.shadow.generate') }}
        </PermissionButton>
        <PermissionButton
          icon="lucide:check"
          v-if="canDetectMatchConflicts"
          danger
          :loading="conflictLoading"
          @click="detectMatchConflicts"
        >
          {{ $t('mapping.matchConflict.detect') }}
        </PermissionButton>
      </Space>
      <Space v-if="shadowReport" wrap>
        <Tag>{{ $t('mapping.shadow.primaryMatched') }}: {{ shadowReport.summary.primary_matched_count }}</Tag>
        <Tag>{{ $t('mapping.shadow.shadowMatched') }}: {{ shadowReport.summary.shadow_matched_count }}</Tag>
        <Tag color="green">{{ $t('mapping.shadow.paired') }}: {{ shadowReport.summary.paired_count }}</Tag>
        <Tag color="orange">
          {{ $t('mapping.shadow.primaryOnly') }}: {{ shadowReport.summary.primary_only_count }}
        </Tag>
        <Tag color="orange">
          {{ $t('mapping.shadow.shadowOnly') }}: {{ shadowReport.summary.shadow_only_count }}
        </Tag>
      </Space>
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1500 }"
          bordered
          :columns="shadowColumns"
          :data-source="shadowReport?.data || []"
          :loading="shadowLoading"
          :pagination="shadowPagination"
          row-key="internal_match_id"
          @change="shadowTableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'pair_status'">
              <Tag :color="comparisonColor(record.pair_status)">
                {{ $t(`mapping.shadow.statuses.${record.pair_status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'external_ids'">
              {{ record.primary.external_id }} / {{ record.shadow?.external_id || '-' }}
              <div class="text-xs text-gray-500">
                Mapping #{{ record.primary.mapping_id }} / #{{ record.shadow?.mapping_id || '-' }} · Raw #{{
                  record.primary.raw_record_id || '-'
                }}
                / #{{ record.shadow?.raw_record_id || '-' }}
              </div>
            </template>
            <template v-else-if="['name', 'score', 'state'].includes(column.key)">
              <Tag :color="comparisonColor(record.comparisons[column.key].status)">
                {{ $t(`mapping.shadow.statuses.${record.comparisons[column.key].status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'coverage'">
              <Space wrap>
                <Tag v-for="(value, key) in record.comparisons.coverage" :key="key">
                  {{ key }}: {{ value.primary }} / {{ value.shadow }}
                </Tag>
              </Space>
            </template>
            <template v-else-if="column.key === 'delta'">
              {{
                record.comparisons.collection_delta_ms === null
                  ? '-'
                  : `${record.comparisons.collection_delta_ms} ms`
              }}
            </template>
          </template>
        </Table>
      </Card>
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          :scroll="{ x: 1500 }"
          bordered
          class="business-data-table admin-data-table"
          :columns="conflictColumns"
          :data-source="matchConflicts"
          :loading="conflictLoading"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'values'">
              <code>{{ JSON.stringify(record.primary_value) }}</code>
              <br />
              <code>{{ JSON.stringify(record.shadow_value) }}</code>
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              <Tag :color="record.status === 'open' ? 'red' : 'green'">{{ record.status }}</Tag>
            </template>
            <template v-else-if="column.key === 'conflict_actions'">
              <PermissionButton
                icon="lucide:eye"
                v-if="record.status === 'open' && canResolveMatchConflicts"
                type="link"
                @click="showConflictResolution(record)"
              >
                {{ $t('mapping.matchConflict.resolve') }}
              </PermissionButton>
            </template>
          </template>
        </Table>
      </Card>
      </div>
    </Card>
    </div>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="open"
      :width="mode === 'inspect' || mode === 'bind' ? 1000 : 520"
      :title="
        $t(
          mode === 'create'
            ? 'mapping.create'
            : mode === 'inspect'
            ? 'mapping.compare'
            : mode === 'bind'
            ? 'mapping.bind'
            : mode === 'reject'
            ? 'mapping.reject'
            : 'mapping.unbind'
        )
      "
      :footer="mode === 'inspect' ? null : undefined"
      :confirm-loading="saving"
      @ok="submit"
    >
      <Form layout="vertical">
        <template v-if="mode === 'create'">
          <FormItem :label="$t('mapping.source')" required>
            <Select v-model:value="form.source_id">
              <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('mapping.entityType')" required>
            <Select v-model:value="form.entity_type">
              <SelectOption
                v-for="x in options.entity_types.filter((type: string) => !['standing', 'commentary'].includes(type))"
                :key="x"
                :value="x"
              >
                {{ x }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('mapping.externalId')" required>
            <Input v-model:value="form.external_id" />
          </FormItem>
          <FormItem :label="$t('mapping.rawRecordId')">
            <InputNumber v-model:value="form.raw_record_id" style="width: 100%" />
          </FormItem>
          <FormItem :label="$t('mapping.confidence')">
            <InputNumber v-model:value="form.confidence" :min="0" :max="1" :step="0.01" style="width: 100%" />
          </FormItem>
          <FormItem :label="$t('mapping.candidateData')" required>
            <Input.TextArea v-model:value="form.candidate_data" :rows="8" />
          </FormItem>
        </template>
        <template v-else-if="mode !== 'inspect'">
          <FormItem v-if="mode === 'bind'" :label="$t('mapping.internalEntity')" required>
            <Select v-model:value="form.internal_id" show-search option-filter-prop="label">
              <SelectOption v-for="x in candidates" :key="x.id" :value="x.id" :label="x.name_en">
                {{ x.name_en }} (#{{ x.id }})
              </SelectOption>
            </Select>
          </FormItem>
          <Table
            :scroll="{ x: 1500 }"
            bordered
            v-if="mode === 'bind' && comparison"
            class="business-data-table admin-data-table mb-4"
            size="small"
            :loading="comparisonLoading"
            :columns="comparisonColumns"
            :data-source="comparison.rows"
            :pagination="false"
            row-key="candidate_field"
          >
            <template #bodyCell="{ column, record }">
              <template
                v-if="column.dataIndex === 'candidate_value' || column.dataIndex === 'canonical_value'"
              >
                {{
                  typeof record[column.dataIndex] === 'object'
                    ? JSON.stringify(record[column.dataIndex])
                    : record[column.dataIndex]
                }}
              </template>
              <template v-else-if="column.dataIndex === 'status'">
                <Tag>{{ $t(`mapping.comparisonStatuses.${record.status}`) }}</Tag>
              </template>
            </template>
          </Table>
          <FormItem :label="$t('mapping.reason')" required>
            <Input.TextArea v-model:value="form.reason" :rows="4" />
          </FormItem>
        </template>
      </Form>
      <template v-if="mode === 'inspect'">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1500 }"
          bordered
          size="small"
          :loading="comparisonLoading"
          :columns="comparisonColumns"
          :data-source="comparison?.rows || []"
          :pagination="false"
          row-key="candidate_field"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'candidate_value' || column.dataIndex === 'canonical_value'">
              {{
                typeof record[column.dataIndex] === 'object'
                  ? JSON.stringify(record[column.dataIndex])
                  : record[column.dataIndex]
              }}
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              <Tag>{{ $t(`mapping.comparisonStatuses.${record.status}`) }}</Tag>
            </template>
          </template>
        </Table>
      </template>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="executeOpen"
      :confirm-loading="executing"
      :title="$t('mapping.statisticsExecution.title')"
      @ok="submitStatisticExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.statisticsExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.statisticsExecution.revisions', {
            mapping: executeForm.version,
            match: executeForm.expected_match_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="executeForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="standingOpen"
      :confirm-loading="executing"
      :title="$t('mapping.standingExecution.title')"
      @ok="submitStandingExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.standingExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.standingExecution.revisions', {
            mapping: standingForm.version,
            table: standingForm.expected_table_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="standingForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="commentaryOpen"
      :confirm-loading="executing"
      :title="$t('mapping.commentaryExecution.title')"
      @ok="submitCommentaryExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.commentaryExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.commentaryExecution.revisions', {
            mapping: commentaryForm.version,
            match: commentaryForm.expected_match_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="commentaryForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="eventOpen"
      :width="720"
      :confirm-loading="executing"
      :title="$t('mapping.eventExecution.title')"
      @ok="submitEventExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.eventExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.eventExecution.counts', {
            events: selected?.event_candidate_count || 0,
            vars: selected?.event_var_count || 0,
          })
        }}
      </p>
      <p>
        {{
          $t('mapping.eventExecution.revisions', {
            mapping: eventForm.version,
            match: eventForm.expected_match_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.eventExecution.overrides')">
          <Input.TextArea v-model:value="eventForm.event_overrides" :rows="7" />
        </FormItem>
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="eventForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="lineupOpen"
      :confirm-loading="executing"
      :title="$t('mapping.lineupExecution.title')"
      @ok="submitLineupExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.lineupExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.lineupExecution.counts', {
            teams: selected?.lineup_team_count || 0,
            players: selected?.lineup_player_count || 0,
          })
        }}
      </p>
      <p>
        {{
          $t('mapping.lineupExecution.revisions', {
            mapping: lineupForm.version,
            match: lineupForm.expected_match_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="lineupForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="scoreStatusOpen"
      :confirm-loading="executing"
      :title="$t('mapping.scoreStatusExecution.title')"
      @ok="submitScoreStatusExecution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.scoreStatusExecution.warning') }}</p>
      <p>
        {{
          $t('mapping.scoreStatusExecution.counts', {
            state: selected?.score_status_provider_state_id || '-',
            scores: selected?.score_status_score_count || 0,
          })
        }}
      </p>
      <p>
        {{
          $t('mapping.scoreStatusExecution.revisions', {
            mapping: scoreStatusForm.version,
            match: scoreStatusForm.expected_match_revision,
          })
        }}
      </p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.scoreStatusExecution.confirmScores')">
          <Switch v-model:checked="scoreStatusForm.scores_confirmed" />
        </FormItem>
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="scoreStatusForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="conflictOpen"
      :confirm-loading="conflictLoading"
      :title="$t('mapping.matchConflict.resolveTitle')"
      @ok="submitConflictResolution"
    >
      <div class="mappings-modal-content">
      <p>{{ $t('mapping.matchConflict.warning') }}</p>
      <Form layout="vertical">
        <FormItem :label="$t('mapping.matchConflict.resolution')" required>
          <Select v-model:value="conflictForm.resolution">
            <SelectOption value="keep_canonical">keep_canonical</SelectOption>
            <SelectOption value="prefer_primary_for_future_execution">
              prefer_primary_for_future_execution
            </SelectOption>
            <SelectOption value="prefer_shadow_for_future_execution">
              prefer_shadow_for_future_execution
            </SelectOption>
            <SelectOption value="defer">defer</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('mapping.reason')" required>
          <Input.TextArea v-model:value="conflictForm.reason" :rows="4" :maxlength="1000" />
        </FormItem>
      </Form>
      </div>
    </Modal>
  </Page>
</template>

<style scoped>
.mappings-content,
.mappings-list,
.mappings-shadow-content,
.mappings-modal-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.mappings-list > :deep(.admin-list-toolbar),
.mappings-list > :deep(.admin-list-search-panel) {
  margin-bottom: 0;
}

.mappings-shadow-content > :deep(.ant-space) {
  margin-bottom: 0 !important;
}

.mappings-modal-content > p {
  margin: 0;
}
</style>
