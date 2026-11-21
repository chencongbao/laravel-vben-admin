<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Card, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Table, Tabs, TabPane, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { collectionOptions, collectionRuns, createRun, cursorCheckpoints, ingestRecord, rawRecords, replayRecord, restoreCursor, saveCursor, syncCatalog, syncCursors } from '#project-admin/api/collections';

const active = ref('runs'); const options = ref<any>({ sources: [], resources: [] });
const runs = ref<any[]>([]); const records = ref<any[]>([]); const cursors = ref<any[]>([]);
const sportFilter = ref<string>();
const checkpoints = ref<any[]>([]); const open = ref(false); const mode = ref<'run' | 'record' | 'cursor' | 'pull' | 'restore'>('run');
const form = reactive<any>({ trigger: 'manual', payload: '{}' });
const runCols = computed(() => [{ dataIndex: 'id', title: 'ID' }, { dataIndex: 'source_id', title: $t('collection.source') }, { dataIndex: 'sport_code', title: $t('collection.sport') }, { dataIndex: 'resource', title: $t('collection.resource') }, { dataIndex: 'trigger', title: $t('collection.trigger') }, { dataIndex: 'status', title: $t('collection.status') }, { dataIndex: 'received_count', title: $t('businessUi.received') }, { dataIndex: 'duplicate_count', title: $t('businessUi.duplicate') }]);
const recordCols = computed(() => [{ dataIndex: 'id', title: 'ID' }, { dataIndex: 'source_id', title: $t('collection.source') }, { dataIndex: 'sport_code', title: $t('collection.sport') }, { dataIndex: 'resource', title: $t('collection.resource') }, { dataIndex: 'external_id', title: $t('collection.externalId') }, { dataIndex: 'payload_hash', title: $t('collection.hash') }, { dataIndex: 'processing_status', title: $t('collection.processing') }, { dataIndex: 'replay_status', title: $t('collection.replayStatus') }, { key: 'actions', title: $t('collection.actions') , fixed: 'right' , width: 200 }]);
const cursorCols = computed(() => [{ dataIndex: 'source_id', title: $t('collection.source') }, { dataIndex: 'resource', title: $t('collection.resource') }, { dataIndex: 'has_cursor', title: $t('collection.hasCursor') }, { dataIndex: 'version', title: $t('collection.version') }, { dataIndex: 'last_received_at', title: $t('collection.receivedAt') }, { key: 'actions', title: $t('collection.actions') , fixed: 'right' , width: 200 }]);
async function load() { loading.value = true; try {  const params = sportFilter.value ? { sport_code: sportFilter.value } : {}; runs.value = (await collectionRuns(params)).data; records.value = (await rawRecords(params)).data; cursors.value = (await syncCursors()).data;  } finally { loading.value = false; } }
function show(nextMode: 'run' | 'record' | 'cursor' | 'pull') { mode.value = nextMode; Object.keys(form).forEach((key) => delete form[key]); Object.assign(form, { trigger: 'manual', payload: '{}', page: 1, resource: nextMode === 'pull' ? 'countries' : undefined, reason: '' }); open.value = true; }
async function showRestore(record:any) { checkpoints.value = (await cursorCheckpoints(record.id)).data; if (!checkpoints.value.length) { message.warning($t('collection.messages.noCheckpoints')); return; } mode.value = 'restore'; Object.keys(form).forEach((key) => delete form[key]); Object.assign(form, { checkpoint_id: checkpoints.value[0].id, expected_version: record.version, reason: '' }); open.value = true; }
async function submit() {
  if (saving.value) return;
  saving.value = true;
  try {  if (mode.value === 'pull' && ['lineups', 'events', 'statistics', 'commentary'].includes(form.resource) && !/^[1-9][0-9]*$/.test(form.fixture_external_id || '')) { message.error($t('collection.messages.fixtureRequired')); return; } if (mode.value === 'pull' && form.resource === 'standings' && !/^[1-9][0-9]*$/.test(form.season_external_id || '')) { message.error($t('collection.messages.seasonRequired')); return; } if (['cursor','restore'].includes(mode.value) && (form.reason || '').trim().length < 3) { message.error($t('collection.messages.reasonRequired')); return; } try { if (mode.value === 'run') await createRun(form); else if (mode.value === 'pull') { await syncCatalog(form); message.success($t('collection.messages.synced')); open.value = false; await load(); return; } else if (mode.value === 'record') { const result: any = await ingestRecord({ ...form, payload: JSON.parse(form.payload) }); message.success($t(result.duplicate ? 'collection.messages.duplicate' : 'collection.messages.saved')); open.value = false; await load(); return; } else if (mode.value === 'restore') await restoreCursor(form.checkpoint_id, { expected_version: form.expected_version, reason: form.reason }); else { const existing = cursors.value.find((x:any) => x.source_id === form.source_id && x.resource === form.resource); await saveCursor({ ...form, expected_version: existing?.version || 0 }); } message.success($t(mode.value === 'restore' ? 'collection.messages.restored' : 'collection.messages.saved')); open.value = false; await load(); } catch { message.error($t('collection.messages.failed')); }  } finally { saving.value = false; }
}
async function replay(id: number) {
 if (saving.value) return;
 saving.value = true;
 try {  try { await replayRecord(id); message.success($t('collection.messages.queued')); await load(); } catch { message.error($t('collection.messages.failed')); }  } finally { saving.value = false; }
}
onMounted(async () => { options.value = await collectionOptions(); await load(); });
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const loading = ref(false);
const saving = ref(false);

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; sportFilter.value = undefined; searchFilters(); }
</script>

<template>
  <Page :title="$t('collection.title')" :description="$t('collection.description')">
    <div>
      <Tabs v-model:active-key="active">
        <TabPane key="runs" :tab="$t('collection.runs')" />
        <TabPane key="records" :tab="$t('collection.records')" />
        <TabPane key="cursors" :tab="$t('collection.cursors')" />
      </Tabs>
      <ListToolbar>
        <template #left>
          <PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">
            {{ $t('businessUi.refresh') }}
          </PermissionButton>
          <PermissionButton
            v-if="active !== 'cursors'"
            icon="lucide:filter"
            :aria-pressed="showFilters"
            @click="showFilters = !showFilters"
          >
            {{ $t('businessUi.filter') }}
          </PermissionButton>
        </template>
        <template #right>
          <PermissionButton icon="lucide:plus" v-if="active === 'runs'" type="primary" @click="show('run')">
            {{ $t('collection.createRun') }}
          </PermissionButton>
          <PermissionButton icon="lucide:download" v-if="active === 'runs'" @click="show('pull')">
            {{ $t('collection.syncCatalog') }}
          </PermissionButton>
          <PermissionButton
            icon="lucide:upload"
            v-if="active === 'records'"
            type="primary"
            @click="show('record')"
          >
            {{ $t('collection.ingest') }}
          </PermissionButton>
          <PermissionButton
            icon="lucide:save"
            v-if="active === 'cursors'"
            type="primary"
            @click="show('cursor')"
          >
            {{ $t('collection.saveCursor') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters && active !== 'cursors'">
        <ListSearchField :label="$t('collection.sport')">
          <Select
            v-if="active !== 'cursors'"
            v-model:value="sportFilter"
            allow-clear
            :placeholder="$t('collection.sport')"
            @change="load"
          >
            <SelectOption v-for="sport in options.sports" :key="sport.code" :value="sport.code">
              {{ sport.canonical_name }}
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
      <Card v-if="active === 'runs'" class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :ref="setTableRef"
          :scroll="{ x: 1100, y: tableScrollY }"
          :loading="loading"
          bordered
          :columns="runCols"
          :data-source="runs"
          :pagination="false"
          row-key="id"
        />
      </Card>
      <Card v-if="active === 'records'" class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :ref="setTableRef"
          :scroll="{ x: 1100, y: tableScrollY }"
          :loading="loading"
          bordered
          :columns="recordCols"
          :data-source="records"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'payload_hash'">
              {{ record.payload_hash?.slice(0, 12) }}…
            </template>
            <template v-else-if="column.dataIndex === 'replay_status'">
              <Tag>{{ record.replay_status }}</Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton
                :loading="saving"
                icon="lucide:rotate-cw"
                type="link"
                @click="replay(record.id)"
              >
                {{ $t('collection.replay') }}
              </PermissionButton>
            </template>
          </template>
        </Table>
      </Card>
      <Card v-if="active === 'cursors'" class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :ref="setTableRef"
          :scroll="{ x: 1100, y: tableScrollY }"
          :loading="loading"
          bordered
          :columns="cursorCols"
          :data-source="cursors"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'actions'">
              <PermissionButton icon="lucide:history" type="link" @click="showRestore(record)">
                {{ $t('collection.restoreCursor') }}
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
      :title="
        $t(
          mode === 'run'
            ? 'collection.createRun'
            : mode === 'record'
            ? 'collection.ingest'
            : mode === 'pull'
            ? 'collection.syncCatalog'
            : mode === 'restore'
            ? 'collection.restoreCursor'
            : 'collection.saveCursor'
        )
      "
      :confirm-loading="saving"
      @ok="submit"
    >
      <Form layout="vertical">
        <p v-if="mode === 'pull'" class="mb-4 text-muted-foreground">
          {{
            $t(
              form.resource === 'scores'
                ? 'collection.liveScoreHint'
                : form.resource === 'lineups'
                ? 'collection.lineupHint'
                : form.resource === 'events'
                ? 'collection.eventHint'
                : form.resource === 'statistics'
                ? 'collection.statisticHint'
                : form.resource === 'standings'
                ? 'collection.standingHint'
                : form.resource === 'commentary'
                ? 'collection.commentaryHint'
                : form.resource === 'types'
                ? 'collection.typeHint'
                : 'collection.syncHint'
            )
          }}
        </p>
        <template v-if="mode !== 'restore'">
          <FormItem :label="$t('collection.source')" required>
            <Select v-model:value="form.source_id">
              <SelectOption v-for="x in options.sources" :key="x.id" :value="x.id">
                {{ x.name }} ({{ x.code }})
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('collection.resource')" required>
            <Select v-model:value="form.resource">
              <SelectOption
                v-for="x in mode === 'pull'
                  ? [
                      'countries',
                      'competitions',
                      'seasons',
                      'stages',
                      'rounds',
                      'teams',
                      'players',
                      'coaches',
                      'venues',
                      'matches',
                      'scores',
                      'lineups',
                      'events',
                      'statistics',
                      'standings',
                      'commentary',
                      'types',
                    ]
                  : options.resources"
                :key="x"
                :value="x"
              >
                {{ $t(`collection.resources.${x}`) }}
              </SelectOption>
            </Select>
          </FormItem>
        </template>
        <FormItem
          v-if="
            mode === 'pull' &&
            !['scores', 'lineups', 'events', 'statistics', 'standings', 'commentary'].includes(form.resource)
          "
          :label="$t('collection.page')"
        >
          <InputNumber v-model:value="form.page" :min="1" style="width: 100%" />
        </FormItem>
        <FormItem
          v-if="mode === 'pull' && ['lineups', 'events', 'statistics', 'commentary'].includes(form.resource)"
          :label="$t('collection.fixtureExternalId')"
          required
        >
          <Input v-model:value="form.fixture_external_id" />
        </FormItem>
        <FormItem
          v-if="mode === 'pull' && form.resource === 'standings'"
          :label="$t('collection.seasonExternalId')"
          required
        >
          <Input v-model:value="form.season_external_id" />
        </FormItem>
        <FormItem v-if="mode === 'run'" :label="$t('collection.trigger')">
          <Select v-model:value="form.trigger">
            <SelectOption value="manual">manual</SelectOption>
            <SelectOption value="scheduled">scheduled</SelectOption>
            <SelectOption value="replay">replay</SelectOption>
          </Select>
        </FormItem>
        <template v-if="mode === 'record'">
          <FormItem :label="$t('businessUi.collectionRunId')">
            <Input v-model:value="form.collection_run_id" />
          </FormItem>
          <FormItem :label="$t('collection.externalId')"><Input v-model:value="form.external_id" /></FormItem>
          <FormItem :label="$t('collection.payload')" required>
            <Input.TextArea v-model:value="form.payload" :rows="10" />
          </FormItem>
        </template>
        <FormItem v-if="mode === 'cursor'" :label="$t('collection.cursor')">
          <Input.TextArea v-model:value="form.cursor" :rows="4" />
        </FormItem>
        <FormItem v-if="mode === 'restore'" :label="$t('collection.checkpoint')" required>
          <Select v-model:value="form.checkpoint_id">
            <SelectOption v-for="x in checkpoints" :key="x.id" :value="x.id">
              v{{ x.cursor_version }} · {{ x.reason }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem v-if="['cursor', 'restore'].includes(mode)" :label="$t('collection.reason')" required>
          <Input.TextArea v-model:value="form.reason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
