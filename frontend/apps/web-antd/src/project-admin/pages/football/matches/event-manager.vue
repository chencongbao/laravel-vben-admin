<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import { computed, reactive, ref } from 'vue';
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
  Switch,
  Table,
  Tag,
  message,
} from 'ant-design-vue';

import { $t } from '#/locales';
import {
  createMatchEvent,
  rescindMatchEvent,
  updateMatchEvent,
  type MatchOptions,
  type MatchRow,
} from '#project-admin/api/football-matches';

const props = defineProps<{ match: MatchRow; options: MatchOptions }>();
const emit = defineEmits<{ changed: [] }>();

const eventTypes = [
  'goal', 'own_goal', 'penalty_goal', 'penalty_missed', 'yellow_card',
  'second_yellow_card', 'red_card', 'substitution', 'var', 'injury', 'other',
];
const eventStatuses = ['active', 'under_review', 'confirmed', 'disallowed'];
const varOutcomes = ['under_review', 'confirmed', 'disallowed'];
const scoringTypes = ['goal', 'own_goal', 'penalty_goal'];

const { hasAccessByCodes } = useAccess();
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.event.manage']));
const canRescind = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.event.rescind']));
const editorOpen = ref(false);
const rescindOpen = ref(false);
const saving = ref(false);
const editingId = ref<number>();
const rescinding = ref<Record<string, any>>();
const form = reactive<Record<string, any>>({});
const rescindForm = reactive<Record<string, any>>({});

const events = computed<Record<string, any>[]>(() => props.match.events ?? []);
const requiresScore = computed(() => scoringTypes.includes(form.type) || (form.type === 'var' && form.var_outcome === 'disallowed'));
const rescindRequiresScore = computed(() => scoringTypes.includes(rescinding.value?.type));
const availableTargets = computed(() => events.value.filter((event) => event.id !== editingId.value));
const columns = computed(() => [
  { key: 'time', title: $t('footballMatches.events.fields.time'), width: 85 },
  { dataIndex: 'type', title: $t('footballMatches.events.fields.type'), width: 145 },
  { key: 'subject', title: $t('footballMatches.events.fields.subject') },
  { key: 'details', title: $t('footballMatches.events.fields.details') },
  { dataIndex: 'status', title: $t('footballMatches.events.fields.status'), width: 120 },
  { key: 'actions', title: $t('footballMatches.actions'), width: 150 , fixed: 'right' },
]);

function nullable(value: unknown) {
  return value === '' || value === undefined ? null : value;
}

function currentScore() {
  return props.match.scores?.find((score: Record<string, any>) => score.type === 'regular')
    ?? props.match.scores?.[0]
    ?? { type: 'regular', home_score: 0, away_score: 0, is_confirmed: false };
}

function resetForm(event?: Record<string, any>) {
  const score = currentScore();
  Object.keys(form).forEach((key) => delete form[key]);
  Object.assign(form, {
    type: event?.type ?? 'goal',
    subtype: event?.subtype ?? null,
    status: event?.status === 'rescinded' ? 'active' : (event?.status ?? 'active'),
    period_id: event?.period_id ?? null,
    team_id: event?.team_id ?? null,
    player_id: event?.player_id ?? null,
    related_player_id: event?.related_player_id ?? null,
    minute: event?.minute ?? null,
    extra_minute: event?.extra_minute ?? null,
    sort_order: event?.sort_order ?? events.value.length * 10 + 10,
    detail_en: event?.detail_en ?? '',
    result_text: event?.result_text ?? '',
    var_outcome: event?.var_outcome ?? null,
    reviewed_event_id: event?.reviewed_event_id ?? null,
    reviewed_event_status: null,
    supersedes_event_id: event?.supersedes_event_id ?? null,
    reason: '',
    score_type: score.type,
    score_home: score.home_score,
    score_away: score.away_score,
    score_confirmed: score.is_confirmed,
  });
}

function openEditor(event?: Record<string, any>) {
  editingId.value = event?.id;
  resetForm(event);
  editorOpen.value = true;
}

function eventLabel(event: Record<string, any>) {
  return `#${event.id} ${event.minute ?? '-'}' ${event.type}${event.player_name_en ? ` · ${event.player_name_en}` : ''}`;
}

function scoreAfter(source: Record<string, any>) {
  return {
    type: source.score_type,
    home: source.score_home,
    away: source.score_away,
    confirmed: source.score_confirmed,
  };
}

function editorPayload() {
  const payload: Record<string, unknown> = {
    type: form.type,
    subtype: nullable(form.subtype),
    status: form.status,
    period_id: nullable(form.period_id),
    team_id: nullable(form.team_id),
    player_id: nullable(form.player_id),
    related_player_id: nullable(form.related_player_id),
    minute: nullable(form.minute),
    extra_minute: nullable(form.extra_minute),
    sort_order: form.sort_order,
    detail_en: nullable(form.detail_en),
    result_text: nullable(form.result_text),
    var_outcome: form.type === 'var' ? nullable(form.var_outcome) : null,
    reviewed_event_id: form.type === 'var' ? nullable(form.reviewed_event_id) : null,
    reviewed_event_status: form.type === 'var' && form.var_outcome === 'under_review' ? nullable(form.reviewed_event_status) : null,
    supersedes_event_id: nullable(form.supersedes_event_id),
    expected_revision: props.match.revision,
    reason: form.reason.trim(),
  };
  if (requiresScore.value) payload.score_after = scoreAfter(form);
  return payload;
}

async function save() {
  if (saving.value) return;

  if (!form.type || !form.status || form.sort_order === null || form.sort_order === undefined || form.reason.trim().length < 3) {
    message.error($t('footballMatches.events.messages.required'));
    return;
  }
  if (form.type === 'var' && (!form.reviewed_event_id || !form.var_outcome)) {
    message.error($t('footballMatches.events.messages.varRequired'));
    return;
  }
  saving.value = true;
  try {
    const payload = editorPayload();
    if (editingId.value) await updateMatchEvent(props.match.id, editingId.value, payload);
    else await createMatchEvent(props.match.id, payload);
    editorOpen.value = false;
    message.success($t('footballMatches.events.messages.saved'));
    emit('changed');
  } catch {
    message.error($t('footballMatches.events.messages.failed'));
  } finally {
    saving.value = false;
  }
}

function openRescind(event: Record<string, any>) {
  const score = currentScore();
  rescinding.value = event;
  Object.assign(rescindForm, { reason: '', score_type: score.type, score_home: score.home_score, score_away: score.away_score, score_confirmed: score.is_confirmed });
  rescindOpen.value = true;
}

async function rescind() {
  if (saving.value) return;

  if (!rescinding.value || rescindForm.reason.trim().length < 3) {
    message.error($t('footballMatches.events.messages.reasonRequired'));
    return;
  }
  saving.value = true;
  try {
    const payload: Record<string, unknown> = {
      expected_revision: props.match.revision,
      reason: rescindForm.reason.trim(),
    };
    if (rescindRequiresScore.value) payload.score_after = scoreAfter(rescindForm);
    await rescindMatchEvent(props.match.id, rescinding.value.id, payload);
    rescindOpen.value = false;
    message.success($t('footballMatches.events.messages.rescinded'));
    emit('changed');
  } catch {
    message.error($t('footballMatches.events.messages.failed'));
  } finally {
    saving.value = false;
  }
}

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('footballMatches.edit'), icon: 'lucide:pencil', visible: Boolean(canManage.value && record.status !== 'rescinded'), danger: false, onClick: () => openEditor(record), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-1', label: $t('footballMatches.events.rescind'), icon: 'lucide:undo-2', visible: Boolean(canRescind.value && record.status !== 'rescinded'), danger: true, onClick: () => openRescind(record), buttonProps: {"type": "link", "size": "small"} },
  ];
}
</script>

<template>
  <Card size="small" :title="$t('footballMatches.events.title')" class="mt-3">
    <template #extra>
      <PermissionButton icon="lucide:plus" v-if="canManage" type="primary" size="small" @click="openEditor()">
        {{ $t('footballMatches.events.create') }}
      </PermissionButton>
    </template>
    <p class="text-gray-500">{{ $t('footballMatches.events.help') }}</p>
    <Table
      class="business-data-table admin-data-table"
      bordered
      :columns="columns"
      :data-source="events"
      :pagination="false"
      row-key="id"
      size="small"
      :scroll="{ x: 920 }"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'time'">
          {{ record.minute ?? '-' }}
          <template v-if="record.extra_minute">+{{ record.extra_minute }}</template>
          '
        </template>
        <template v-else-if="column.key === 'subject'">
          <div>{{ record.team_name_en || '-' }}</div>
          <div>
            {{ record.player_name_en || '-' }}
            <template v-if="record.related_player_name_en">→ {{ record.related_player_name_en }}</template>
          </div>
        </template>
        <template v-else-if="column.key === 'details'">
          <div>{{ record.detail_en || record.result_text || '-' }}</div>
          <div v-if="record.type === 'var'" class="text-gray-500">
            VAR: {{ record.var_outcome }} · #{{ record.reviewed_event_id }}
          </div>
        </template>
        <template v-else-if="column.dataIndex === 'status'">
          <Tag>{{ record.status }}</Tag>
        </template>
        <template v-else-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
            </template>
      </template>
    </Table>
  </Card>

  <Modal
    :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
    :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
    wrap-class-name="business-modal"
    v-model:open="editorOpen"
    :confirm-loading="saving"
    :title="$t(editingId ? 'footballMatches.events.edit' : 'footballMatches.events.create')"
    width="820px"
    @ok="save"
  >
    <Form layout="vertical">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <FormItem :label="$t('footballMatches.events.fields.type')" required>
          <Select v-model:value="form.type">
            <SelectOption v-for="item in eventTypes" :key="item" :value="item">{{ item }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.status')" required>
          <Select v-model:value="form.status">
            <SelectOption v-for="item in eventStatuses" :key="item" :value="item">{{ item }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.period')">
          <Select v-model:value="form.period_id" allow-clear>
            <SelectOption v-for="item in match.periods || []" :key="item.id" :value="item.id">
              {{ item.sequence }}. {{ item.type }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.team')">
          <Select v-model:value="form.team_id" allow-clear>
            <SelectOption :value="match.home_team_id">
              {{ options.teams?.find((item:any) => item.id === match.home_team_id)?.name }}
            </SelectOption>
            <SelectOption :value="match.away_team_id">
              {{ options.teams?.find((item:any) => item.id === match.away_team_id)?.name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.player')">
          <Select v-model:value="form.player_id" allow-clear show-search option-filter-prop="label">
            <SelectOption v-for="item in options.players" :key="item.id" :value="item.id" :label="item.name">
              {{ item.name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.relatedPlayer')">
          <Select v-model:value="form.related_player_id" allow-clear show-search option-filter-prop="label">
            <SelectOption v-for="item in options.players" :key="item.id" :value="item.id" :label="item.name">
              {{ item.name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.minute')">
          <Space wrap>
            <InputNumber v-model:value="form.minute" :min="0" :max="999" />
            <span>+</span>
            <InputNumber v-model:value="form.extra_minute" :min="0" :max="999" />
          </Space>
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.sortOrder')" required>
          <InputNumber v-model:value="form.sort_order" class="w-full" :min="0" />
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.subtype')">
          <Input v-model:value="form.subtype" :maxlength="64" />
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.resultText')">
          <Input v-model:value="form.result_text" :maxlength="64" />
        </FormItem>
        <FormItem :label="$t('footballMatches.events.fields.supersedes')">
          <Select v-model:value="form.supersedes_event_id" allow-clear>
            <SelectOption v-for="item in availableTargets" :key="item.id" :value="item.id">
              {{ eventLabel(item) }}
            </SelectOption>
          </Select>
        </FormItem>
        <template v-if="form.type === 'var'">
          <FormItem :label="$t('footballMatches.events.fields.reviewedEvent')" required>
            <Select v-model:value="form.reviewed_event_id" allow-clear>
              <SelectOption v-for="item in availableTargets" :key="item.id" :value="item.id">
                {{ eventLabel(item) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.events.fields.varOutcome')" required>
            <Select v-model:value="form.var_outcome">
              <SelectOption v-for="item in varOutcomes" :key="item" :value="item">{{ item }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem
            v-if="form.var_outcome === 'under_review'"
            :label="$t('footballMatches.events.fields.reviewedStatus')"
          >
            <Select v-model:value="form.reviewed_event_status" allow-clear>
              <SelectOption value="active">active</SelectOption>
              <SelectOption value="under_review">under_review</SelectOption>
            </Select>
          </FormItem>
        </template>
      </div>
      <FormItem :label="$t('footballMatches.events.fields.details')">
        <Input.TextArea v-model:value="form.detail_en" :rows="2" :maxlength="2000" />
      </FormItem>
      <Card v-if="requiresScore" size="small" :title="$t('footballMatches.events.scoreAfter')" class="mb-3">
        <Space wrap>
          <Select v-model:value="form.score_type" style="width: 140px">
            <SelectOption v-for="item in options.score_types" :key="item" :value="item">
              {{ item }}
            </SelectOption>
          </Select>
          <InputNumber v-model:value="form.score_home" :min="0" :max="999" />
          <span>:</span>
          <InputNumber v-model:value="form.score_away" :min="0" :max="999" />
          <Switch v-model:checked="form.score_confirmed" />
          {{ $t('footballMatches.fields.confirmed') }}
        </Space>
      </Card>
      <FormItem :label="$t('footballMatches.events.fields.reason')" required>
        <Input.TextArea v-model:value="form.reason" :rows="2" :maxlength="500" />
      </FormItem>
    </Form>
  </Modal>

  <Modal
    :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
    :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
    wrap-class-name="business-modal"
    v-model:open="rescindOpen"
    :confirm-loading="saving"
    :title="$t('footballMatches.events.rescindTitle')"
    ok-type="danger"
    @ok="rescind"
  >
    <p>{{ $t('footballMatches.events.rescindWarning') }}</p>
    <Card
      v-if="rescindRequiresScore"
      size="small"
      :title="$t('footballMatches.events.scoreAfter')"
      class="mb-3"
    >
      <Space wrap>
        <Select v-model:value="rescindForm.score_type" style="width: 140px">
          <SelectOption v-for="item in options.score_types" :key="item" :value="item">
            {{ item }}
          </SelectOption>
        </Select>
        <InputNumber v-model:value="rescindForm.score_home" :min="0" :max="999" />
        <span>:</span>
        <InputNumber v-model:value="rescindForm.score_away" :min="0" :max="999" />
        <Switch v-model:checked="rescindForm.score_confirmed" />
      </Space>
    </Card>
    <Form layout="vertical">
      <FormItem :label="$t('footballMatches.events.fields.reason')" required>
        <Input.TextArea v-model:value="rescindForm.reason" :rows="3" :maxlength="500" />
      </FormItem>
    </Form>
  </Modal>
</template>
