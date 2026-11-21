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
  createMatchCommentary,
  rescindMatchCommentary,
  updateMatchCommentary,
  type MatchOptions,
  type MatchRow,
} from '#project-admin/api/football-matches';

const props = defineProps<{ match: MatchRow; options: MatchOptions }>();
const emit = defineEmits<{ changed: [] }>();

const commentaryTypes = ['general', 'goal', 'card', 'substitution', 'var', 'injury', 'other'];
const { hasAccessByCodes } = useAccess();
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.commentary.manage']));
const canRescind = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.match.commentary.rescind']));
const editorOpen = ref(false);
const rescindOpen = ref(false);
const saving = ref(false);
const editingId = ref<number>();
const rescinding = ref<Record<string, any>>();
const form = reactive<Record<string, any>>({});
const rescindReason = ref('');

const commentaries = computed<Record<string, any>[]>(() => props.match.commentaries ?? []);
const events = computed<Record<string, any>[]>(() => props.match.events ?? []);
const columns = computed(() => [
  { key: 'time', title: $t('footballMatches.commentaries.fields.time'), width: 85 },
  { dataIndex: 'commentary_type', title: $t('footballMatches.commentaries.fields.type'), width: 120 },
  { dataIndex: 'original_text', title: $t('footballMatches.commentaries.fields.text') },
  { key: 'flags', title: $t('footballMatches.commentaries.fields.flags'), width: 135 },
  { dataIndex: 'revision', title: $t('businessUi.revision'), width: 85 },
  { dataIndex: 'status', title: $t('footballMatches.commentaries.fields.status'), width: 100 },
  { key: 'actions', title: $t('footballMatches.actions'), width: 150 , fixed: 'right' },
]);

function nullable(value: unknown) {
  return value === '' || value === undefined ? null : value;
}

function commentaryLabel(item: Record<string, any>) {
  return `#${item.id} ${item.minute ?? '-'}' ${item.commentary_type}`;
}

function eventLabel(event: Record<string, any>) {
  return `#${event.id} ${event.minute ?? '-'}' ${event.type}${event.player_name_en ? ` · ${event.player_name_en}` : ''}`;
}

function resetForm(item?: Record<string, any>) {
  Object.keys(form).forEach((key) => delete form[key]);
  Object.assign(form, {
    match_event_id: item?.match_event_id ?? null,
    period_id: item?.period_id ?? null,
    player_id: item?.player_id ?? null,
    related_player_id: item?.related_player_id ?? null,
    commentary_type: item?.commentary_type ?? 'general',
    original_text: item?.original_text ?? '',
    minute: item?.minute ?? null,
    extra_minute: item?.extra_minute ?? null,
    sort_order: item?.sort_order ?? commentaries.value.length * 10 + 10,
    is_goal: item?.is_goal ?? false,
    is_important: item?.is_important ?? false,
    reason: '',
  });
}

function openEditor(item?: Record<string, any>) {
  editingId.value = item?.id;
  resetForm(item);
  editorOpen.value = true;
}

function payload() {
  return {
    match_event_id: nullable(form.match_event_id),
    period_id: nullable(form.period_id),
    player_id: nullable(form.player_id),
    related_player_id: nullable(form.related_player_id),
    commentary_type: form.commentary_type,
    original_text: form.original_text.trim(),
    minute: nullable(form.minute),
    extra_minute: nullable(form.extra_minute),
    sort_order: form.sort_order,
    is_goal: form.is_goal,
    is_important: form.is_important,
    expected_match_revision: props.match.revision,
    reason: form.reason.trim(),
  };
}

async function save() {
  if (saving.value) return;

  if (!form.commentary_type || !form.original_text.trim() || form.sort_order === null || form.sort_order === undefined || form.reason.trim().length < 3) {
    message.error($t('footballMatches.commentaries.messages.required'));
    return;
  }
  if (form.player_id && form.player_id === form.related_player_id) {
    message.error($t('footballMatches.commentaries.messages.playersDifferent'));
    return;
  }
  saving.value = true;
  try {
    if (editingId.value) await updateMatchCommentary(props.match.id, editingId.value, payload());
    else await createMatchCommentary(props.match.id, payload());
    editorOpen.value = false;
    message.success($t('footballMatches.commentaries.messages.saved'));
    emit('changed');
  } catch {
    message.error($t('footballMatches.commentaries.messages.failed'));
  } finally {
    saving.value = false;
  }
}

function openRescind(item: Record<string, any>) {
  rescinding.value = item;
  rescindReason.value = '';
  rescindOpen.value = true;
}

async function rescind() {
  if (saving.value) return;

  if (!rescinding.value || rescindReason.value.trim().length < 3) {
    message.error($t('footballMatches.commentaries.messages.reasonRequired'));
    return;
  }
  saving.value = true;
  try {
    await rescindMatchCommentary(props.match.id, rescinding.value.id, {
      expected_match_revision: props.match.revision,
      reason: rescindReason.value.trim(),
    });
    rescindOpen.value = false;
    message.success($t('footballMatches.commentaries.messages.rescinded'));
    emit('changed');
  } catch {
    message.error($t('footballMatches.commentaries.messages.failed'));
  } finally {
    saving.value = false;
  }
}

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('footballMatches.edit'), icon: 'lucide:pencil', visible: Boolean(canManage.value && record.status !== 'rescinded'), danger: false, onClick: () => openEditor(record), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-1', label: $t('footballMatches.commentaries.rescind'), icon: 'lucide:undo-2', visible: Boolean(canRescind.value && record.status !== 'rescinded'), danger: true, onClick: () => openRescind(record), buttonProps: {"type": "link", "size": "small"} },
  ];
}
</script>

<template>
  <Card size="small" :title="$t('footballMatches.commentaries.title')" class="mt-3">
    <template #extra>
      <PermissionButton icon="lucide:plus" v-if="canManage" type="primary" size="small" @click="openEditor()">
        {{ $t('footballMatches.commentaries.create') }}
      </PermissionButton>
    </template>
    <p class="text-gray-500">{{ $t('footballMatches.commentaries.help') }}</p>
    <Table
      class="business-data-table admin-data-table"
      bordered
      :columns="columns"
      :data-source="commentaries"
      :pagination="false"
      row-key="id"
      size="small"
      :scroll="{ x: 1000 }"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'time'">
          {{ record.minute ?? '-' }}
          <template v-if="record.extra_minute">+{{ record.extra_minute }}</template>
          '
        </template>
        <template v-else-if="column.dataIndex === 'original_text'">
          <div>{{ record.original_text }}</div>
          <div class="text-gray-500">
            <span v-if="record.player_name_en">{{ record.player_name_en }}</span>
            <span v-if="record.related_player_name_en">→ {{ record.related_player_name_en }}</span>
            <span v-if="record.match_event_id">
              · {{ $t('businessUi.event') }} #{{ record.match_event_id }}
            </span>
          </div>
        </template>
        <template v-else-if="column.key === 'flags'">
          <Tag v-if="record.is_goal" color="green">{{ $t('businessUi.goal') }}</Tag>
          <Tag v-if="record.is_important" color="orange">{{ $t('businessUi.important') }}</Tag>
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
    :title="$t(editingId ? 'footballMatches.commentaries.edit' : 'footballMatches.commentaries.create')"
    width="820px"
    @ok="save"
  >
    <Form layout="vertical">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <FormItem :label="$t('footballMatches.commentaries.fields.type')" required>
          <Select v-model:value="form.commentary_type">
            <SelectOption v-for="item in commentaryTypes" :key="item" :value="item">{{ item }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.period')">
          <Select v-model:value="form.period_id" allow-clear>
            <SelectOption v-for="item in match.periods || []" :key="item.id" :value="item.id">
              {{ item.sequence }}. {{ item.type }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.event')">
          <Select v-model:value="form.match_event_id" allow-clear show-search option-filter-prop="label">
            <SelectOption v-for="item in events" :key="item.id" :value="item.id" :label="eventLabel(item)">
              {{ eventLabel(item) }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.minute')">
          <Space wrap>
            <InputNumber v-model:value="form.minute" :min="0" :max="999" />
            <span>+</span>
            <InputNumber v-model:value="form.extra_minute" :min="0" :max="999" />
          </Space>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.player')">
          <Select v-model:value="form.player_id" allow-clear show-search option-filter-prop="label">
            <SelectOption v-for="item in options.players" :key="item.id" :value="item.id" :label="item.name">
              {{ item.name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.relatedPlayer')">
          <Select v-model:value="form.related_player_id" allow-clear show-search option-filter-prop="label">
            <SelectOption v-for="item in options.players" :key="item.id" :value="item.id" :label="item.name">
              {{ item.name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.sortOrder')" required>
          <InputNumber v-model:value="form.sort_order" class="w-full" :min="0" />
        </FormItem>
        <FormItem :label="$t('footballMatches.commentaries.fields.flags')">
          <Space wrap>
            <Switch v-model:checked="form.is_goal" />
            {{ $t('businessUi.goal') }}
            <Switch v-model:checked="form.is_important" />
            {{ $t('businessUi.important') }}
          </Space>
        </FormItem>
      </div>
      <FormItem :label="$t('footballMatches.commentaries.fields.text')" required>
        <Input.TextArea v-model:value="form.original_text" :rows="5" :maxlength="5000" show-count />
      </FormItem>
      <FormItem :label="$t('footballMatches.commentaries.fields.reason')" required>
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
    :title="$t('footballMatches.commentaries.rescindTitle')"
    ok-type="danger"
    @ok="rescind"
  >
    <p>{{ $t('footballMatches.commentaries.rescindWarning') }}</p>
    <p v-if="rescinding">
      <b>{{ commentaryLabel(rescinding) }}</b>
      · {{ rescinding.original_text }}
    </p>
    <Form layout="vertical">
      <FormItem :label="$t('footballMatches.commentaries.fields.reason')" required>
        <Input.TextArea v-model:value="rescindReason" :rows="3" :maxlength="500" />
      </FormItem>
    </Form>
  </Modal>
</template>
