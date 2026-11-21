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
import { createStandingTable, getStandingOptions, getStandingTable, getStandingTables, publishStandingTable, type StandingOptions, type StandingTableRow } from '#project-admin/api/standings';

const { hasAccessByCodes } = useAccess();
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.standing.manage']));
const canClear = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.standing.clear']));
const canAdjust = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.standing.adjustment']));

const standingOptions = ref<StandingOptions>({ seasons: [], stages: [], groups: [], rounds: [], teams: [] });
const tables = ref<StandingTableRow[]>([]);
const seasonId = ref<number>();
const loading = ref(false);
const saving = ref(false);
const createOpen = ref(false);
const editorOpen = ref(false);
const confirmOpen = ref(false);
const selected = ref<StandingTableRow>();
const pendingRows = ref<Record<string, any>[]>([]);
const createForm = reactive<Record<string, any>>({ snapshot_kind: 'current' });
const reason = ref('');
const rows = ref<Record<string, any>[]>([]);

const seasons = computed(() => standingOptions.value.seasons);
const stages = computed(() => standingOptions.value.stages);
const groups = computed(() => standingOptions.value.groups);
const rounds = computed(() => standingOptions.value.rounds);
const filteredStages = computed(() => stages.value.filter((item) => item.season_id === createForm.season_id));
const filteredGroups = computed(() => groups.value.filter((item) => item.stage_id === createForm.stage_id));
const filteredRounds = computed(() => rounds.value.filter((item) => item.stage_id === createForm.stage_id && (!createForm.competition_group_id || item.competition_group_id === createForm.competition_group_id)));
const columns = computed(() => [
  { dataIndex: 'id', title: 'ID', width: 70 },
  { key: 'scope', title: $t('standings.fields.scope') },
  { dataIndex: 'snapshot_kind', title: $t('standings.fields.kind'), width: 110 },
  { dataIndex: 'revision', title: $t('businessUi.revision'), width: 90 },
  { key: 'rowCount', title: $t('standings.fields.rowCount'), width: 100 },
  { dataIndex: 'published_at', title: $t('standings.fields.publishedAt'), width: 190 },
  { key: 'actions', title: $t('standings.actions'), width: 140 , fixed: 'right' },
]);

function name(collection: Record<string, any>[], id?: number | null) {
  return id ? collection.find((item) => item.id === id)?.name_en ?? `#${id}` : '-';
}

function teamName(id: number) {
  return standingOptions.value.teams.find((item) => item.id === id)?.name_en ?? `#${id}`;
}

function scopeText(table: StandingTableRow) {
  return [name(seasons.value, table.season_id), name(stages.value, table.stage_id), name(groups.value, table.group_id), name(rounds.value, table.round_id)].filter((item) => item !== '-').join(' / ');
}

async function load() {
  if (!seasonId.value) {
    tables.value = [];
    return;
  }
  loading.value = true;
  try {
    tables.value = (await getStandingTables(seasonId.value)).data;
  } catch {
    message.error($t('standings.messages.loadFailed'));
  } finally {
    loading.value = false;
  }
}

function resetDependent(level: 'season' | 'stage' | 'group' | 'kind') {
  if (level === 'season') Object.assign(createForm, { stage_id: null, competition_group_id: null, round_id: null });
  if (level === 'stage') Object.assign(createForm, { competition_group_id: null, round_id: null });
  if (level === 'group') createForm.round_id = null;
  if (level === 'kind' && createForm.snapshot_kind === 'current') createForm.round_id = null;
}

function showCreate() {
  Object.assign(createForm, { season_id: seasonId.value, stage_id: null, competition_group_id: null, round_id: null, snapshot_kind: 'current' });
  createOpen.value = true;
}

async function createScope() {
  if (saving.value) return;

  if (!createForm.season_id || (createForm.snapshot_kind === 'round' && !createForm.round_id)) {
    message.error($t('standings.messages.scopeRequired'));
    return;
  }
  saving.value = true;
  try {
    const result = await createStandingTable({
      season_id: createForm.season_id,
      stage_id: createForm.stage_id || null,
      competition_group_id: createForm.competition_group_id || null,
      round_id: createForm.snapshot_kind === 'round' ? createForm.round_id : null,
      snapshot_kind: createForm.snapshot_kind,
    });
    seasonId.value = createForm.season_id;
    createOpen.value = false;
    await load();
    message.success($t('standings.messages.scopeSaved'));
    await openEditor(result.data);
  } catch {
    message.error($t('standings.messages.scopeFailed'));
  } finally {
    saving.value = false;
  }
}

function normalizeRow(row: Record<string, any>) {
  return {
    team_id: row.team_id,
    position: row.position ?? 1,
    played: row.played ?? 0,
    won: row.won ?? 0,
    drawn: row.drawn ?? 0,
    lost: row.lost ?? 0,
    goals_for: row.goals_for ?? 0,
    goals_against: row.goals_against ?? 0,
    goal_difference: row.goal_difference ?? 0,
    points: row.points ?? 0,
    points_before_adjustment: row.points_before_adjustment ?? null,
    points_adjustment: row.points_adjustment ?? null,
    adjustment_reason_en: row.adjustment_reason_en ?? '',
    position_change: row.position_change ?? null,
    form_text: Array.isArray(row.form) ? row.form.join(',') : '',
  };
}

async function openEditor(table: StandingTableRow) {
  try {
    selected.value = (await getStandingTable(table.id)).data;
    rows.value = selected.value.rows.map(normalizeRow);
    reason.value = '';
    editorOpen.value = true;
  } catch {
    message.error($t('standings.messages.loadFailed'));
  }
}

function addRow() {
  rows.value.push(normalizeRow({ position: rows.value.length + 1 }));
}

function removeRow(index: number) {
  rows.value.splice(index, 1);
}

function publishRows() {
  return rows.value.map((row) => ({
    team_id: row.team_id,
    position: row.position,
    played: row.played,
    won: row.won,
    drawn: row.drawn,
    lost: row.lost,
    goals_for: row.goals_for,
    goals_against: row.goals_against,
    goal_difference: row.goal_difference,
    points: row.points,
    points_before_adjustment: row.points_before_adjustment ?? null,
    points_adjustment: row.points_adjustment ?? null,
    adjustment_reason_en: row.adjustment_reason_en?.trim() || null,
    position_change: row.position_change || null,
    form: row.form_text?.trim() ? row.form_text.split(',').map((item: string) => item.trim().toUpperCase()).filter(Boolean) : null,
  }));
}

function validateRows(nextRows: Record<string, any>[]) {
  if (!selected.value || reason.value.trim().length < 3) return $t('standings.messages.reasonRequired');
  if (nextRows.some((row) => !row.team_id || !row.position)) return $t('standings.messages.rowRequired');
  if (new Set(nextRows.map((row) => row.team_id)).size !== nextRows.length) return $t('standings.messages.duplicateTeam');
  if (nextRows.some((row) => row.played !== row.won + row.drawn + row.lost || row.goal_difference !== row.goals_for - row.goals_against)) return $t('standings.messages.arithmetic');
  if (nextRows.some((row) => row.form?.some((result: string) => !['W', 'D', 'L'].includes(result)))) return $t('standings.messages.formInvalid');
  const adjusted = nextRows.some((row) => row.points_adjustment !== null);
  if (adjusted && !canAdjust.value) return $t('standings.messages.adjustmentForbidden');
  if (nextRows.some((row) => (row.points_before_adjustment === null) !== (row.points_adjustment === null))) return $t('standings.messages.adjustmentPair');
  if (nextRows.some((row) => row.points_adjustment !== null && row.points_before_adjustment + row.points_adjustment !== row.points)) return $t('standings.messages.adjustmentArithmetic');
  if (selected.value.rows.length > 0 && nextRows.length === 0 && !canClear.value) return $t('standings.messages.clearForbidden');
  return null;
}

async function save() {
  const nextRows = publishRows();
  const error = validateRows(nextRows);
  if (error) {
    message.error(error);
    return;
  }
  pendingRows.value = nextRows;
  if ((selected.value?.revision ?? 0) > 0 || nextRows.length === 0) {
    confirmOpen.value = true;
    return;
  }
  await publish();
}

async function publish() {
  if (saving.value) return;

  if (!selected.value) return;
  saving.value = true;
  try {
    selected.value = (await publishStandingTable(selected.value.id, {
      expected_table_revision: selected.value.revision,
      reason: reason.value.trim(),
      rows: pendingRows.value,
    })).data;
    rows.value = selected.value.rows.map(normalizeRow);
    confirmOpen.value = false;
    await load();
    message.success($t('standings.messages.published'));
  } catch {
    message.error($t('standings.messages.publishFailed'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    standingOptions.value = (await getStandingOptions()).data;
    seasonId.value = seasons.value[0]?.id;
    await load();
  } catch {
    message.error($t('standings.messages.optionsFailed'));
  }
});
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; seasonId.value = seasons.value[0]?.id; searchFilters(); }
</script>

<template>
  <Page :title="$t('standings.title')" :description="$t('standings.description')">
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
            {{ $t('standings.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('standings.fields.season')">
          <Select
            v-model:value="seasonId"
            show-search
            option-filter-prop="label"
            :placeholder="$t('standings.fields.season')"
            @change="load"
          >
            <SelectOption v-for="item in seasons" :key="item.id" :value="item.id" :label="item.name_en">
              {{ item.name_en }}
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
          :data-source="tables"
          :loading="loading"
          :pagination="false"
          row-key="id"
          :scroll="{ x: 950, y: tableScrollY }"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'scope'">{{ scopeText(record) }}</template>
            <template v-else-if="column.dataIndex === 'snapshot_kind'">
              <Tag>{{ record.snapshot_kind }}</Tag>
            </template>
            <template v-else-if="column.key === 'rowCount'">{{ record.rows?.length ?? 0 }}</template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton icon="lucide:eye" type="link" @click="openEditor(record)">
                {{ canManage ? $t('standings.edit') : $t('standings.view') }}
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
      v-model:open="createOpen"
      :confirm-loading="saving"
      :title="$t('standings.create')"
      width="700px"
      @ok="createScope"
    >
      <Form layout="vertical">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <FormItem :label="$t('standings.fields.season')" required>
            <Select v-model:value="createForm.season_id" @change="resetDependent('season')">
              <SelectOption v-for="item in seasons" :key="item.id" :value="item.id">
                {{ item.name_en }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('standings.fields.kind')" required>
            <Select v-model:value="createForm.snapshot_kind" @change="resetDependent('kind')">
              <SelectOption value="current">current</SelectOption>
              <SelectOption value="round">round</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('standings.fields.stage')">
            <Select v-model:value="createForm.stage_id" allow-clear @change="resetDependent('stage')">
              <SelectOption v-for="item in filteredStages" :key="item.id" :value="item.id">
                {{ item.name_en }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('standings.fields.group')">
            <Select
              v-model:value="createForm.competition_group_id"
              allow-clear
              @change="resetDependent('group')"
            >
              <SelectOption v-for="item in filteredGroups" :key="item.id" :value="item.id">
                {{ item.name_en }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem
            v-if="createForm.snapshot_kind === 'round'"
            :label="$t('standings.fields.round')"
            required
          >
            <Select v-model:value="createForm.round_id">
              <SelectOption v-for="item in filteredRounds" :key="item.id" :value="item.id">
                {{ item.name_en }}
              </SelectOption>
            </Select>
          </FormItem>
        </div>
      </Form>
    </Modal>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="editorOpen"
      :footer="null"
      :title="$t('standings.editorTitle')"
      width="1200px"
    >
      <template v-if="selected">
        <p>
          <b>{{ scopeText(selected) }}</b>
          · {{ selected.snapshot_kind }} · Revision {{ selected.revision }}
        </p>
        <p class="text-gray-500">{{ $t('standings.snapshotWarning') }}</p>
        <div v-for="(row, index) in rows" :key="index" class="mb-3 rounded border p-3">
          <Space wrap>
            <Select
              v-model:value="row.team_id"
              show-search
              option-filter-prop="label"
              style="width: 190px"
              :placeholder="$t('standings.fields.team')"
              :disabled="!canManage"
            >
              <SelectOption
                v-for="item in standingOptions.teams"
                :key="item.id"
                :value="item.id"
                :label="item.name_en"
              >
                {{ item.name_en }}
              </SelectOption>
            </Select>
            <InputNumber
              v-model:value="row.position"
              :min="1"
              :placeholder="$t('standings.fields.position')"
              :disabled="!canManage"
            />
            <InputNumber v-model:value="row.played" :min="0" placeholder="P" :disabled="!canManage" />
            <InputNumber v-model:value="row.won" :min="0" placeholder="W" :disabled="!canManage" />
            <InputNumber v-model:value="row.drawn" :min="0" placeholder="D" :disabled="!canManage" />
            <InputNumber v-model:value="row.lost" :min="0" placeholder="L" :disabled="!canManage" />
            <InputNumber v-model:value="row.goals_for" :min="0" placeholder="GF" :disabled="!canManage" />
            <InputNumber v-model:value="row.goals_against" :min="0" placeholder="GA" :disabled="!canManage" />
            <InputNumber v-model:value="row.goal_difference" placeholder="GD" :disabled="!canManage" />
            <InputNumber v-model:value="row.points" placeholder="PTS" :disabled="!canManage" />
            <Select
              v-model:value="row.position_change"
              allow-clear
              style="width: 110px"
              :placeholder="$t('standings.fields.change')"
              :disabled="!canManage"
            >
              <SelectOption v-for="item in ['up', 'down', 'same', 'new']" :key="item" :value="item">
                {{ item }}
              </SelectOption>
            </Select>
            <Input
              v-model:value="row.form_text"
              style="width: 150px"
              placeholder="W,D,L"
              :disabled="!canManage"
            />
            <PermissionButton icon="lucide:trash-2" v-if="canManage" danger @click="removeRow(index)">
              {{ $t('standings.remove') }}
            </PermissionButton>
          </Space>
          <Space class="mt-2" wrap>
            <InputNumber
              v-model:value="row.points_before_adjustment"
              :placeholder="$t('standings.fields.pointsBefore')"
              :disabled="!canManage || !canAdjust"
            />
            <InputNumber
              v-model:value="row.points_adjustment"
              :placeholder="$t('standings.fields.adjustment')"
              :disabled="!canManage || !canAdjust"
            />
            <Input
              v-model:value="row.adjustment_reason_en"
              style="width: 360px"
              :placeholder="$t('standings.fields.adjustmentReason')"
              :disabled="!canManage || !canAdjust"
            />
          </Space>
        </div>
        <template v-if="canManage">
          <PermissionButton icon="lucide:plus" class="mb-3" @click="addRow">
            {{ $t('standings.addTeam') }}
          </PermissionButton>
          <Form layout="vertical">
            <FormItem :label="$t('standings.fields.reason')" required>
              <Input.TextArea v-model:value="reason" :rows="2" :maxlength="500" />
            </FormItem>
          </Form>
          <PermissionButton icon="lucide:save" type="primary" :loading="saving" @click="save">
            {{ $t('standings.publish') }}
          </PermissionButton>
        </template>
      </template>
    </Modal>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="confirmOpen"
      :confirm-loading="saving"
      :title="$t('standings.confirmTitle')"
      ok-type="danger"
      @ok="publish"
    >
      <p>{{ pendingRows.length === 0 ? $t('standings.clearWarning') : $t('standings.overwriteWarning') }}</p>
    </Modal>
  </Page>
</template>
