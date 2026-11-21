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
  changeSourceStatus,
  createSource,
  getSourceOptions,
  getSources,
  probeSource,
  updateSource,
  type Source,
  type SourceOptions,
  type SourcePayload,
  type SourceStatus,
} from '#project-admin/api/suppliers';

const { hasAccessByCodes } = useAccess();
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const editing = ref<Source>();
const sources = ref<Source[]>([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const options = ref<SourceOptions>({
  adapters: [],
  sports: [],
  credential_storages: ['encrypted', 'reference'],
  health_statuses: [],
  run_modes: ['testing', 'shadow', 'production'],
  statuses: ['draft', 'active', 'degraded', 'suspended', 'disabled'],
});
const filters = reactive({ keyword: '', sport_code: undefined as string | undefined, run_mode: undefined as string | undefined, status: undefined as string | undefined });
const form = reactive({
  adapter: '',
  sport_code: '',
  api_base_url: '',
  api_version: '',
  code: '',
  concurrency_limit: 1,
  credential_json: '',
  credential_reference: '',
  credential_storage: 'encrypted' as 'encrypted' | 'reference',
  name: '',
  notes: '',
  request_timeout: 10,
  rate_limit_per_day: undefined as number | undefined,
  rate_limit_per_hour: undefined as number | undefined,
  rate_limit_per_minute: undefined as number | undefined,
  retry_backoff_ms: 1000,
  retry_max_attempts: 2,
  retry_max_delay_ms: 30000,
  run_mode: 'testing' as 'production' | 'shadow' | 'testing',
  score_poll_enabled: false,
  score_poll_interval_seconds: 60,
  status: 'draft' as SourceStatus,
  website: '',
});

const canCreate = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.source.create']));
const canUpdate = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.source.update']));
const canStatus = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.source.status']));
const canCredentials = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.source.credentials']));
const canTest = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.source.test']));
const selectedAdapter = computed(() => options.value.adapters.find((item) => item.code === form.adapter));
const supportedSports = computed(() => {
  const supported = selectedAdapter.value?.supported_sports ?? [];
  return options.value.sports.filter((sport) => supported.includes(sport.code));
});
const adapterSupportsSport = computed(() => supportedSports.value.some((sport) => sport.code === form.sport_code));

const columns = computed(() => [
  { dataIndex: 'id', key: 'id', title: $t('supplier.fields.id'), width: 80 },
  { dataIndex: 'name', key: 'name', title: $t('supplier.fields.name') },
  { dataIndex: 'code', key: 'code', title: $t('supplier.fields.code') },
  { dataIndex: 'sport_code', key: 'sport_code', title: $t('supplier.fields.sport') },
  { dataIndex: 'adapter', key: 'adapter', title: $t('supplier.fields.adapter') },
  { dataIndex: 'run_mode', key: 'run_mode', title: $t('supplier.fields.runMode') },
  { dataIndex: 'status', key: 'status', title: $t('supplier.fields.status') },
  { dataIndex: 'health_status', key: 'health_status', title: $t('supplier.fields.healthStatus') },
  { dataIndex: 'credentials_configured', key: 'credentials_configured', title: $t('supplier.fields.credentials') },
  { dataIndex: 'updated_at', key: 'updated_at', title: $t('supplier.fields.updatedAt') },
  { dataIndex: 'actions', key: 'actions', title: $t('supplier.actions.actions'), width: 250 , fixed: 'right' },
]);

function label(group: 'healthStatuses' | 'runModes' | 'statuses', value: string) {
  return $t(`supplier.${group}.${value}`);
}

function sportLabel(code: string) {
  const sport = options.value.sports.find((item) => item.code === code);
  return sport?.canonical_name ?? code;
}

watch(() => form.adapter, () => {
  if (!adapterSupportsSport.value) {
    form.sport_code = supportedSports.value[0]?.code ?? '';
  }
});

async function load() {
  loading.value = true;
  try {
    const result = await getSources({
      keyword: filters.keyword || undefined,
      page: page.value,
      sport_code: filters.sport_code,
      per_page: perPage.value,
      run_mode: filters.run_mode,
      status: filters.status,
    });
    sources.value = result.data;
    total.value = result.total;
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  Object.assign(form, {
    adapter: options.value.adapters[0]?.code ?? '',
    sport_code: options.value.sports[0]?.code ?? '',
    api_base_url: '',
    api_version: '',
    code: '',
    concurrency_limit: 1,
    credential_json: '',
    credential_reference: '',
    credential_storage: 'encrypted',
    name: '',
    notes: '',
    request_timeout: 10,
    rate_limit_per_day: undefined,
    rate_limit_per_hour: undefined,
    rate_limit_per_minute: undefined,
    retry_backoff_ms: 1000,
    retry_max_attempts: 2,
    retry_max_delay_ms: 30000,
    run_mode: 'testing',
    score_poll_enabled: false,
    score_poll_interval_seconds: 60,
    status: 'draft',
    website: '',
  });
}

function openCreate() {
  editing.value = undefined;
  resetForm();
  modalOpen.value = true;
}

function openEdit(source: Source) {
  editing.value = source;
  Object.assign(form, {
    adapter: source.adapter,
    sport_code: source.sport_code,
    api_base_url: source.api_base_url,
    api_version: source.api_version ?? '',
    code: source.code,
    concurrency_limit: source.concurrency_limit,
    credential_json: '',
    credential_reference: '',
    credential_storage: source.credential_storage,
    name: source.name,
    notes: source.notes ?? '',
    request_timeout: source.request_timeout,
    rate_limit_per_day: source.rate_limit_per_day ?? undefined,
    rate_limit_per_hour: source.rate_limit_per_hour ?? undefined,
    rate_limit_per_minute: source.rate_limit_per_minute ?? undefined,
    retry_backoff_ms: source.retry_backoff_ms,
    retry_max_attempts: source.retry_max_attempts,
    retry_max_delay_ms: source.retry_max_delay_ms,
    run_mode: source.run_mode,
    score_poll_enabled: source.score_poll_enabled,
    score_poll_interval_seconds: source.score_poll_interval_seconds,
    status: source.status,
    website: source.website ?? '',
  });
  modalOpen.value = true;
}

function parseCredentials(): Record<string, string> | undefined {
  const value = form.credential_json.trim();
  if (!value) return undefined;
  let parsed: unknown;
  try {
    parsed = JSON.parse(value);
  } catch {
    throw new Error('invalid-json');
  }
  if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') throw new Error('invalid-json');
  if (Object.values(parsed).some((item) => typeof item !== 'string')) throw new Error('invalid-json');
  return parsed as Record<string, string>;
}

function sourceSportErrorCode(error: unknown): string | undefined {
  if (!error || typeof error !== 'object') return undefined;

  const candidate = error as {
    data?: { errors?: Record<string, unknown> };
    errors?: Record<string, unknown>;
    response?: { data?: { errors?: Record<string, unknown> } };
  };
  const errors = candidate.errors ?? candidate.data?.errors ?? candidate.response?.data?.errors;
  const sportError = errors?.sport_code;

  if (Array.isArray(sportError)) {
    return typeof sportError[0] === 'string' ? sportError[0] : undefined;
  }

  return typeof sportError === 'string' ? sportError : undefined;
}

async function save() {
  if (saving.value) return;

  if (!form.name.trim() || !form.code.trim() || !form.sport_code || !form.adapter || !form.api_base_url.trim()) {
    return void message.warning($t('supplier.messages.required'));
  }
  if (!adapterSupportsSport.value) {
    return void message.warning($t('supplier.messages.sportUnsupported'));
  }
  let credentials: Record<string, string> | undefined;
  try {
    credentials = parseCredentials();
  } catch {
    return void message.warning($t('supplier.messages.credentialsInvalid'));
  }

  const payload: SourcePayload = {
    adapter: form.adapter,
    api_base_url: form.api_base_url.trim(),
    api_version: form.api_version.trim() || undefined,
    code: form.code.trim(),
    concurrency_limit: form.concurrency_limit,
    credential_storage: form.credential_storage,
    name: form.name.trim(),
    notes: form.notes.trim() || undefined,
    request_timeout: form.request_timeout,
    rate_limit_per_day: form.rate_limit_per_day ?? null,
    rate_limit_per_hour: form.rate_limit_per_hour ?? null,
    rate_limit_per_minute: form.rate_limit_per_minute ?? null,
    retry_backoff_ms: form.retry_backoff_ms,
    retry_max_attempts: form.retry_max_attempts,
    retry_max_delay_ms: form.retry_max_delay_ms,
    run_mode: form.run_mode,
    score_poll_enabled: form.score_poll_enabled,
    score_poll_interval_seconds: form.score_poll_interval_seconds,
    sport_code: form.sport_code,
    status: form.status,
    website: form.website.trim() || undefined,
  };
  if (canCredentials.value && form.credential_storage === 'encrypted' && credentials) payload.credentials = credentials;
  if (canCredentials.value && form.credential_storage === 'reference' && form.credential_reference.trim()) {
    payload.credential_reference = form.credential_reference.trim();
  }

  saving.value = true;
  try {
    if (editing.value) await updateSource(editing.value.id, payload);
    else await createSource(payload);
    message.success($t('supplier.messages.saved'));
    modalOpen.value = false;
    await load();
  } catch (error: unknown) {
    const code = sourceSportErrorCode(error);
    if (code === 'SOURCE_SPORT_IMMUTABLE') {
      message.error($t('supplier.messages.sportImmutable'));
    } else if (code === 'SUPPLIER_SPORT_UNSUPPORTED') {
      message.error($t('supplier.messages.sportUnsupported'));
    } else {
      message.error($t('supplier.messages.saveFailed'));
    }
  } finally {
    saving.value = false;
  }
}

async function changeStatus(source: Source, status: 'active' | 'disabled' | 'suspended') {
 if (saving.value) return;
 saving.value = true;
 try {
  try {
    await changeSourceStatus(source.id, status);
    message.success($t('supplier.messages.statusChanged'));
    await load();
  } catch {
    message.error($t('supplier.messages.statusChangeFailed'));
  }
 } finally { saving.value = false; }
}

async function probe(source: Source, type: 'connection' | 'sample_pull') {
 if (saving.value) return;
 saving.value = true;
 try {
  try {
    const result = (await probeSource(source.id, type)).test;
    if (result.status === 'succeeded') {
      message.success($t('supplier.messages.probeSucceeded', { duration: result.duration_ms }));
    } else {
      message.error($t(`supplier.probeErrors.${result.error_code ?? 'SUPPLIER_REQUEST_FAILED'}`));
    }
    await load();
  } catch {
    message.error($t('supplier.messages.probeFailed'));
  }
 } finally { saving.value = false; }
}

onMounted(async () => {
  options.value = await getSourceOptions();
  resetForm();
  await load();
});
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; page.value = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { keyword: '', sport_code: undefined, run_mode: undefined, status: undefined }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('supplier.actions.edit'), icon: 'lucide:pencil', visible: Boolean(canUpdate.value), danger: false, onClick: () => openEdit(record), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-1', label: $t('supplier.actions.testConnection'), icon: 'lucide:plug', visible: Boolean(canTest.value), danger: false, onClick: () => probe(record, 'connection'), loading: saving.value, buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-2', label: $t('supplier.actions.testPull'), icon: 'lucide:plug', visible: Boolean(canTest.value), danger: false, onClick: () => probe(record, 'sample_pull'), loading: saving.value, buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-3', label: $t('supplier.actions.activate'), icon: 'lucide:check', visible: Boolean(canStatus.value && record.status !== 'active'), danger: false, onClick: () => changeStatus(record, 'active'), loading: saving.value, confirmTitle: $t('supplier.prompts.activate'), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-4', label: $t('supplier.actions.suspend'), icon: 'lucide:pause', visible: Boolean(canStatus.value && record.status !== 'suspended'), danger: false, onClick: () => changeStatus(record, 'suspended'), loading: saving.value, confirmTitle: $t('supplier.prompts.suspend'), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-5', label: $t('supplier.actions.disable'), icon: 'lucide:ban', visible: Boolean(canStatus.value && record.status !== 'disabled'), danger: true, onClick: () => changeStatus(record, 'disabled'), loading: saving.value, confirmTitle: $t('supplier.prompts.disable'), buttonProps: {"type": "link", "size": "small"} },
  ];
}
</script>

<template>
  <Page :description="$t('supplier.description')" :title="$t('supplier.title')">
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
          <PermissionButton icon="lucide:plus" v-if="canCreate" type="primary" @click="openCreate">
            {{ $t('supplier.actions.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('supplier.filters.keyword')">
          <Input
            v-model:value="filters.keyword"
            allow-clear
            :placeholder="$t('supplier.filters.keyword')"
            @press-enter="searchFilters"
          />
        </ListSearchField>
        <ListSearchField :label="$t('supplier.filters.sport')">
          <Select v-model:value="filters.sport_code" allow-clear :placeholder="$t('supplier.filters.sport')">
            <SelectOption v-for="item in options.sports" :key="item.code" :value="item.code">
              {{ item.canonical_name }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('supplier.filters.runMode')">
          <Select v-model:value="filters.run_mode" allow-clear :placeholder="$t('supplier.filters.runMode')">
            <SelectOption v-for="item in options.run_modes" :key="item" :value="item">
              {{ label('runModes', item) }}
            </SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('supplier.filters.status')">
          <Select v-model:value="filters.status" allow-clear :placeholder="$t('supplier.filters.status')">
            <SelectOption v-for="item in options.statuses" :key="item" :value="item">
              {{ label('statuses', item) }}
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
          :scroll="{ x: 1500, y: tableScrollY }"
          bordered
          :columns="columns"
          :data-source="sources"
          :loading="loading"
          :pagination="{ current: page, pageSize: perPage, total, showSizeChanger: true }"
          row-key="id"
          @change="
            (pagination) => {
              page = pagination.current ?? 1;
              perPage = pagination.pageSize ?? 20;
              load();
            }
          "
        >
          <template #bodyCell="{ column, record }">
            <Tag v-if="column.dataIndex === 'run_mode'">{{ label('runModes', record.run_mode) }}</Tag>
            <Tag v-else-if="column.dataIndex === 'sport_code'">{{ sportLabel(record.sport_code) }}</Tag>
            <Tag v-else-if="column.dataIndex === 'status'">{{ label('statuses', record.status) }}</Tag>
            <Tag v-else-if="column.dataIndex === 'health_status'">
              {{ label('healthStatuses', record.health_status) }}
            </Tag>
            <Tag
              v-else-if="column.dataIndex === 'credentials_configured'"
              :color="record.credentials_configured ? 'success' : 'default'"
            >
              {{
                record.credentials_configured
                  ? $t('supplier.credentials.configured')
                  : $t('supplier.credentials.notConfigured')
              }}
            </Tag>
            <BusinessRowActions v-else-if="column.dataIndex === 'actions'" :actions="rowActions(record)" />
          </template>
        </Table>
      </Card>
    </div>

    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="modalOpen"
      :confirm-loading="saving"
      :title="editing ? $t('supplier.form.editTitle') : $t('supplier.form.createTitle')"
      width="760px"
      @ok="save"
    >
      <Form layout="vertical">
        <h3 class="mb-4 mt-2 font-semibold">{{ $t('businessUi.basic') }}</h3>
        <FormItem :label="$t('supplier.fields.name')" required>
          <Input v-model:value="form.name" :placeholder="$t('supplier.placeholders.name')" />
        </FormItem>
        <FormItem :label="$t('supplier.fields.code')" required>
          <Input
            v-model:value="form.code"
            :disabled="!!editing"
            :placeholder="$t('supplier.placeholders.code')"
          />
        </FormItem>
        <FormItem :label="$t('supplier.fields.adapter')" required>
          <Select v-model:value="form.adapter">
            <SelectOption v-for="item in options.adapters" :key="item.code" :value="item.code">
              {{ item.label }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem
          :extra="
            adapterSupportsSport || !form.sport_code ? undefined : $t('supplier.messages.sportUnsupported')
          "
          :label="$t('supplier.fields.sport')"
          required
        >
          <Select v-model:value="form.sport_code" :disabled="supportedSports.length === 0">
            <SelectOption v-for="item in supportedSports" :key="item.code" :value="item.code">
              {{ item.canonical_name }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('supplier.fields.website')">
          <Input v-model:value="form.website" placeholder="https://" />
        </FormItem>
        <h3 class="mb-4 mt-2 font-semibold">{{ $t('businessUi.access') }}</h3>
        <FormItem :label="$t('supplier.fields.apiBaseUrl')" required>
          <Input v-model:value="form.api_base_url" placeholder="https://" />
        </FormItem>
        <FormItem :label="$t('supplier.fields.apiVersion')">
          <Input v-model:value="form.api_version" />
        </FormItem>
        <h3 class="mb-4 mt-2 font-semibold">{{ $t('businessUi.policy') }}</h3>
        <Space wrap class="w-full" size="large">
          <FormItem :label="$t('supplier.fields.runMode')" required>
            <Select v-model:value="form.run_mode" style="width: 180px">
              <SelectOption v-for="item in options.run_modes" :key="item" :value="item">
                {{ label('runModes', item) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('supplier.fields.status')" required>
            <Select v-model:value="form.status" style="width: 180px">
              <SelectOption v-for="item in options.statuses" :key="item" :value="item">
                {{ label('statuses', item) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('supplier.fields.requestTimeout')" required>
            <InputNumber v-model:value="form.request_timeout" :max="120" :min="1" />
          </FormItem>
        </Space>
        <h3 class="mb-4 mt-2 font-semibold">{{ $t('businessUi.collection') }}</h3>
        <Space class="w-full" size="large" wrap>
          <FormItem :label="$t('supplier.fields.concurrencyLimit')" required>
            <InputNumber v-model:value="form.concurrency_limit" :max="100" :min="1" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.rateLimitPerMinute')">
            <InputNumber v-model:value="form.rate_limit_per_minute" :min="1" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.rateLimitPerHour')">
            <InputNumber v-model:value="form.rate_limit_per_hour" :min="1" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.rateLimitPerDay')">
            <InputNumber v-model:value="form.rate_limit_per_day" :min="1" />
          </FormItem>
        </Space>
        <Space class="w-full" size="large" wrap>
          <FormItem :extra="$t('supplier.help.scorePolling')" :label="$t('supplier.fields.scorePollEnabled')">
            <Switch v-model:checked="form.score_poll_enabled" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.scorePollIntervalSeconds')" required>
            <InputNumber
              v-model:value="form.score_poll_interval_seconds"
              :disabled="!form.score_poll_enabled"
              :max="3600"
              :min="15"
            />
          </FormItem>
        </Space>
        <Space class="w-full" size="large" wrap>
          <FormItem :label="$t('supplier.fields.retryMaxAttempts')" required>
            <InputNumber v-model:value="form.retry_max_attempts" :max="10" :min="1" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.retryBackoffMs')" required>
            <InputNumber v-model:value="form.retry_backoff_ms" :max="600000" :min="0" />
          </FormItem>
          <FormItem :label="$t('supplier.fields.retryMaxDelayMs')" required>
            <InputNumber v-model:value="form.retry_max_delay_ms" :max="3600000" :min="0" />
          </FormItem>
        </Space>
        <template v-if="canCredentials">
          <FormItem :label="$t('supplier.fields.credentialStorage')">
            <Select v-model:value="form.credential_storage">
              <SelectOption value="encrypted">{{ $t('supplier.credentialStorages.encrypted') }}</SelectOption>
              <SelectOption value="reference">{{ $t('supplier.credentialStorages.reference') }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem
            v-if="form.credential_storage === 'encrypted'"
            :extra="$t('supplier.help.credentials')"
            :label="$t('supplier.fields.credentials')"
          >
            <Input.TextArea
              v-model:value="form.credential_json"
              :placeholder="$t('supplier.placeholders.credentials')"
              :rows="4"
            />
          </FormItem>
          <FormItem
            v-else
            :extra="$t('supplier.help.credentialReference')"
            :label="$t('supplier.fields.credentialReference')"
          >
            <Input.Password
              v-model:value="form.credential_reference"
              :placeholder="$t('supplier.placeholders.credentialReference')"
            />
          </FormItem>
        </template>
        <FormItem :label="$t('supplier.fields.notes')">
          <Input.TextArea v-model:value="form.notes" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
