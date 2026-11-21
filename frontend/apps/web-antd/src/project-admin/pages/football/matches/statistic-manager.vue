<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import {
  Card,
  Form,
  FormItem,
  Input,
  InputNumber,
  Modal,
  Select,
  SelectOption,
  Space,
  Table,
  Tag,
  message,
} from 'ant-design-vue';

import { $t } from '#/locales';
import {
  getStatisticDefinitions,
  replacePlayerStatistics,
  replaceTeamStatistics,
  type MatchOptions,
  type MatchRow,
} from '#project-admin/api/football-matches';

const props = defineProps<{ match: MatchRow; options: MatchOptions }>();
const emit = defineEmits<{ changed: [] }>();
const { hasAccessByCodes } = useAccess();
const canViewDefinitions = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.statistic-definition.view']));
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.statistic.manage']));
const canClear = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.statistic.clear']));

const definitions = ref<Record<string, any>[]>([]);
const loadingDefinitions = ref(false);
const saving = ref(false);
const editorOpen = ref(false);
const clearOpen = ref(false);
const mode = ref<'team' | 'player'>('team');
const teamId = ref<number>();
const playerId = ref<number>();
const reason = ref('');
const values = reactive<Record<string, any>>({});

const teamRows = computed(() => props.match.statistics?.team ?? []);
const playerRows = computed(() => props.match.statistics?.player ?? []);
const currentRows = computed<Record<string, any>[]>(() => mode.value === 'team'
  ? teamRows.value.filter((row: Record<string, any>) => row.team_id === teamId.value)
  : playerRows.value.filter((row: Record<string, any>) => row.player_id === playerId.value));
const hasDeprecatedCurrent = computed(() => currentRows.value.some((row) => row.definition_status === 'deprecated'));
const applicableDefinitions = computed(() => definitions.value.filter((definition) => definition.status === 'active' && [mode.value, 'both'].includes(definition.scope)));
const overviewColumns = computed(() => [
  { key: 'subject', title: $t('footballMatches.statistics.fields.subject'), width: 210 },
  { dataIndex: 'name_en', title: $t('footballMatches.statistics.fields.metric') },
  { key: 'value', title: $t('footballMatches.statistics.fields.value'), width: 150 },
  { dataIndex: 'updated_at', title: $t('footballMatches.statistics.fields.updatedAt'), width: 190 },
]);

function teamName(id: number) {
  return props.options.teams?.find((item: Record<string, any>) => item.id === id)?.name ?? `#${id}`;
}

function playerName(id: number) {
  return props.options.players?.find((item: Record<string, any>) => item.id === id)?.name ?? `#${id}`;
}

function displayValue(row: Record<string, any>) {
  const text = typeof row.value === 'object' ? JSON.stringify(row.value) : String(row.value);
  return row.unit ? `${text} ${row.unit}` : text;
}

function resetValues() {
  Object.keys(values).forEach((key) => delete values[key]);
  for (const definition of applicableDefinitions.value) {
    const row = currentRows.value.find((item) => item.definition_code === definition.code);
    if (!row) {
      values[definition.code] = definition.value_type === 'json' ? '' : null;
      continue;
    }
    values[definition.code] = definition.value_type === 'json' ? JSON.stringify(row.value, null, 2) : row.value;
  }
}

function subjectChanged() {
  resetValues();
}

function changeMode(nextMode: 'team' | 'player') {
  mode.value = nextMode;
  teamId.value = undefined;
  playerId.value = undefined;
  resetValues();
}

function openEditor() {
  mode.value = 'team';
  teamId.value = props.match.home_team_id;
  playerId.value = undefined;
  reason.value = '';
  resetValues();
  editorOpen.value = true;
}

function normalizedValue(definition: Record<string, any>) {
  const value = values[definition.code];
  if (value === null || value === undefined || value === '') return undefined;
  if (definition.value_type === 'string') return String(value).trim() || undefined;
  if (definition.value_type !== 'json') return value;
  const parsed = JSON.parse(value);
  if (parsed === null || typeof parsed !== 'object') throw new Error('json');
  return parsed;
}

function snapshot() {
  return applicableDefinitions.value.flatMap((definition) => {
    const value = normalizedValue(definition);
    return value === undefined ? [] : [{ definition_code: definition.code, value }];
  });
}

async function submitSnapshot(statistics: Record<string, any>[]) {
  if (saving.value) return;

  saving.value = true;
  try {
    const data: Record<string, unknown> = {
      expected_match_revision: props.match.revision,
      reason: reason.value.trim(),
      statistics,
    };
    if (mode.value === 'team' && teamId.value) await replaceTeamStatistics(props.match.id, teamId.value, data);
    else if (mode.value === 'player' && playerId.value && teamId.value) await replacePlayerStatistics(props.match.id, playerId.value, { ...data, team_id: teamId.value });
    else throw new Error('subject');
    editorOpen.value = false;
    clearOpen.value = false;
    message.success($t('footballMatches.statistics.messages.saved'));
    emit('changed');
  } catch {
    message.error($t('footballMatches.statistics.messages.failed'));
  } finally {
    saving.value = false;
  }
}

async function save() {
  if (saving.value) return;
  if (!teamId.value || (mode.value === 'player' && !playerId.value) || reason.value.trim().length < 3) {
    message.error($t('footballMatches.statistics.messages.required'));
    return;
  }
  if (hasDeprecatedCurrent.value) {
    message.error($t('footballMatches.statistics.messages.deprecatedBlocked'));
    return;
  }
  let statistics: Record<string, any>[];
  try {
    statistics = snapshot();
  } catch {
    message.error($t('footballMatches.statistics.messages.invalidJson'));
    return;
  }
  if (currentRows.value.length > 0 && statistics.length === 0) {
    if (!canClear.value) {
      message.error($t('footballMatches.statistics.messages.clearForbidden'));
      return;
    }
    clearOpen.value = true;
    return;
  }
  await submitSnapshot(statistics);
}

async function confirmClear() {
  if (saving.value) return;
  await submitSnapshot([]);
}

onMounted(async () => {
  if (!canViewDefinitions.value) return;
  loadingDefinitions.value = true;
  try {
    definitions.value = (await getStatisticDefinitions()).data;
  } catch {
    message.error($t('footballMatches.statistics.messages.definitionsFailed'));
  } finally {
    loadingDefinitions.value = false;
  }
});
</script>

<template>
  <Card size="small" :title="$t('footballMatches.statistics.title')" class="mt-3">
    <template #extra>
      <PermissionButton
        icon="lucide:pencil"
        v-if="canManage && canViewDefinitions"
        type="primary"
        size="small"
        :disabled="loadingDefinitions || definitions.length === 0"
        @click="openEditor"
      >
        {{ $t('footballMatches.statistics.edit') }}
      </PermissionButton>
    </template>
    <p class="text-gray-500">{{ $t('footballMatches.statistics.help') }}</p>
    <Table
      class="business-data-table admin-data-table"
      bordered
      :columns="overviewColumns"
      :data-source="[...teamRows, ...playerRows]"
      :pagination="false"
      size="small"
      :row-key="(row:any) => `${row.player_id ? 'p' : 't'}-${row.player_id || row.team_id}-${row.definition_code}`"
      :scroll="{ x: 800 }"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'subject'">
          <Tag>
            {{
              $t(record.player_id ? 'footballMatches.statistics.player' : 'footballMatches.statistics.team')
            }}
          </Tag>
          {{ record.player_id ? playerName(record.player_id) : teamName(record.team_id) }}
        </template>
        <template v-else-if="column.key === 'value'">{{ displayValue(record) }}</template>
      </template>
    </Table>
  </Card>

  <Modal
    :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
    :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
    wrap-class-name="business-modal"
    v-model:open="editorOpen"
    :confirm-loading="saving"
    :title="$t('footballMatches.statistics.edit')"
    width="820px"
    @ok="save"
  >
    <Form layout="vertical">
      <Space class="mb-3" wrap>
        <Select :value="mode" style="width: 150px" @change="changeMode">
          <SelectOption value="team">{{ $t('footballMatches.statistics.team') }}</SelectOption>
          <SelectOption value="player">{{ $t('footballMatches.statistics.player') }}</SelectOption>
        </Select>
        <Select
          v-model:value="teamId"
          style="width: 210px"
          :placeholder="$t('footballMatches.statistics.fields.team')"
          @change="subjectChanged"
        >
          <SelectOption :value="match.home_team_id">{{ teamName(match.home_team_id) }}</SelectOption>
          <SelectOption :value="match.away_team_id">{{ teamName(match.away_team_id) }}</SelectOption>
        </Select>
        <Select
          v-if="mode === 'player'"
          v-model:value="playerId"
          show-search
          option-filter-prop="label"
          style="width: 220px"
          :placeholder="$t('footballMatches.statistics.fields.player')"
          @change="subjectChanged"
        >
          <SelectOption v-for="item in options.players" :key="item.id" :value="item.id" :label="item.name">
            {{ item.name }}
          </SelectOption>
        </Select>
      </Space>
      <p class="text-gray-500">{{ $t('footballMatches.statistics.snapshotWarning') }}</p>
      <p v-if="hasDeprecatedCurrent" class="text-red-500">
        {{ $t('footballMatches.statistics.deprecatedWarning') }}
      </p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <FormItem
          v-for="definition in applicableDefinitions"
          :key="definition.code"
          :label="`${definition.name_en} · ${definition.code}${
            definition.unit ? ` (${definition.unit})` : ''
          }`"
        >
          <InputNumber
            v-if="definition.value_type === 'integer'"
            v-model:value="values[definition.code]"
            class="w-full"
            :precision="0"
          />
          <InputNumber
            v-else-if="definition.value_type === 'decimal'"
            v-model:value="values[definition.code]"
            class="w-full"
            :precision="definition.decimal_places"
          />
          <Select
            v-else-if="definition.value_type === 'boolean'"
            v-model:value="values[definition.code]"
            allow-clear
          >
            <SelectOption :value="true">true</SelectOption>
            <SelectOption :value="false">false</SelectOption>
          </Select>
          <Input
            v-else-if="definition.value_type === 'string'"
            v-model:value="values[definition.code]"
            :maxlength="500"
          />
          <Input.TextArea
            v-else
            v-model:value="values[definition.code]"
            :rows="3"
            :placeholder="$t('businessUi.jsonHint')"
          />
        </FormItem>
      </div>
      <FormItem :label="$t('footballMatches.statistics.fields.reason')" required>
        <Input.TextArea v-model:value="reason" :rows="2" :maxlength="500" />
      </FormItem>
    </Form>
  </Modal>

  <Modal
    :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
    :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
    wrap-class-name="business-modal"
    v-model:open="clearOpen"
    :confirm-loading="saving"
    :title="$t('footballMatches.statistics.clearTitle')"
    ok-type="danger"
    @ok="confirmClear"
  >
    <p>{{ $t('footballMatches.statistics.clearWarning') }}</p>
  </Modal>
</template>
