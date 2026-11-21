<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref } from 'vue';

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
  Table,
  Tag,
  message,
} from 'ant-design-vue';

import { $t } from '#/locales';
import {
  getCapabilityOptions,
  getSourceCapabilities,
  updateSourceCapability,
  type CapabilityOptions,
  type CapabilityStatus,
  type SourceCapability,
} from '#project-admin/api/suppliers';

const { hasAccessByCodes } = useAccess();
const loading = ref(false);
const saving = ref(false);
const modalOpen = ref(false);
const sourceId = ref<number>();
const capabilities = ref<SourceCapability[]>([]);
const editing = ref<SourceCapability>();
const options = ref<CapabilityOptions>({
  delivery_methods: [],
  resources: [],
  sources: [],
  support_statuses: [],
});
const form = reactive({
  competition_limit: '',
  delivery_methods: [] as string[],
  expected_latency_ms: undefined as number | undefined,
  languages: [] as string[],
  last_verified_at: '',
  notes: '',
  package_limit: '',
  support_status: 'unknown' as CapabilityStatus,
});

const canUpdate = computed(
  () => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.capability.update']),
);
const columns = computed(() => [
  { dataIndex: 'resource', key: 'resource', title: $t('supplier.capability.fields.resource'), width: 150 },
  { dataIndex: 'support_status', key: 'support_status', title: $t('supplier.capability.fields.supportStatus'), width: 170 },
  { dataIndex: 'languages', key: 'languages', title: $t('supplier.capability.fields.languages'), width: 240 },
  { dataIndex: 'delivery_methods', key: 'delivery_methods', title: $t('supplier.capability.fields.deliveryMethods'), width: 200 },
  { dataIndex: 'expected_latency_ms', key: 'expected_latency_ms', title: $t('supplier.capability.fields.expectedLatency'), width: 180 },
  { dataIndex: 'last_verified_at', key: 'last_verified_at', title: $t('supplier.capability.fields.lastVerifiedAt'), width: 280 },
  { dataIndex: 'actions', key: 'actions', title: $t('supplier.actions.actions'), width: 100 , fixed: 'right' },
]);

function resourceLabel(value: string) {
  return $t(`supplier.capability.resources.${value}`);
}

function statusLabel(value: string) {
  return $t(`supplier.capability.statuses.${value}`);
}

async function load() {
  if (!sourceId.value) {
    capabilities.value = [];
    return;
  }
  loading.value = true;
  try {
    capabilities.value = (await getSourceCapabilities(sourceId.value)).data;
  } catch {
    message.error($t('supplier.capability.messages.loadFailed'));
  } finally {
    loading.value = false;
  }
}

function openEdit(capability: SourceCapability) {
  editing.value = capability;
  Object.assign(form, {
    competition_limit: capability.competition_limit ?? '',
    delivery_methods: [...capability.delivery_methods],
    expected_latency_ms: capability.expected_latency_ms ?? undefined,
    languages: [...capability.languages],
    last_verified_at: capability.last_verified_at?.slice(0, 16) ?? '',
    notes: capability.notes ?? '',
    package_limit: capability.package_limit ?? '',
    support_status: capability.support_status,
  });
  modalOpen.value = true;
}

async function save() {
  if (saving.value) return;

  if (!sourceId.value || !editing.value) return;
  if (!form.support_status) return void message.warning($t('supplier.capability.messages.required'));

  saving.value = true;
  try {
    await updateSourceCapability(sourceId.value, editing.value.resource, {
      competition_limit: form.competition_limit.trim() || null,
      delivery_methods: form.delivery_methods,
      expected_latency_ms: form.expected_latency_ms ?? null,
      languages: form.languages.map((item) => item.trim()).filter(Boolean),
      last_verified_at: form.last_verified_at || null,
      notes: form.notes.trim() || null,
      package_limit: form.package_limit.trim() || null,
      support_status: form.support_status,
    });
    message.success($t('supplier.capability.messages.saved'));
    modalOpen.value = false;
    await load();
  } catch {
    message.error($t('supplier.capability.messages.saveFailed'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  options.value = await getCapabilityOptions();
  sourceId.value = options.value.sources[0]?.id;
  await load();
});
const { setTableRef, tableScrollY } = useAdminTableScrollY();
const capabilityTableScrollY = computed(() => tableScrollY.value + 64);


</script>

<template>
  <Page :description="$t('supplier.capability.description')" :title="$t('supplier.capability.title')">
    <div class="capabilities-content">
      <ListToolbar>
        <template #left>
          <span>{{ $t('supplier.capability.fields.source') }}</span>
          <Select v-model:value="sourceId" class="w-72" @change="load">
            <SelectOption v-for="source in options.sources" :key="source.id" :value="source.id">
              {{ source.name }} ({{ source.code }})
            </SelectOption>
          </Select>
          <PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">
            {{ $t('businessUi.refresh') }}
          </PermissionButton>
        </template>
      </ListToolbar>

      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :ref="setTableRef"
          :scroll="{ x: 1320, y: capabilityTableScrollY }"
          bordered
          :columns="columns"
          :data-source="capabilities"
          :loading="loading"
          :pagination="false"
          row-key="resource"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'resource'">{{ resourceLabel(record.resource) }}</template>
            <template v-else-if="column.key === 'support_status'">
              <Tag>{{ statusLabel(record.support_status) }}</Tag>
            </template>
            <template v-else-if="column.key === 'languages'">
              <span class="capabilities-cell-text">
                {{ record.languages.join(', ') || '-' }}
              </span>
            </template>
            <template v-else-if="column.key === 'delivery_methods'">
              <span class="capabilities-cell-text">
                {{ record.delivery_methods.map((item: string) => $t(`supplier.capability.deliveryMethods.${item}`)).join(', ') || '-' }}
              </span>
            </template>
            <template v-else-if="column.key === 'expected_latency_ms'">
              {{ record.expected_latency_ms ?? '-' }}
            </template>
            <template v-else-if="column.key === 'last_verified_at'">
              <span class="capabilities-cell-text">
                {{ record.last_verified_at ?? '-' }}
              </span>
            </template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton
                icon="lucide:pencil"
                v-if="canUpdate"
                size="small"
                type="link"
                @click="openEdit(record)"
              >
                {{ $t('supplier.actions.edit') }}
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
      v-model:open="modalOpen"
      :confirm-loading="saving"
      :title="editing ? resourceLabel(editing.resource) : ''"
      @ok="save"
    >
      <Form layout="vertical">
        <FormItem :label="$t('supplier.capability.fields.supportStatus')" required>
          <Select v-model:value="form.support_status">
            <SelectOption v-for="status in options.support_statuses" :key="status" :value="status">
              {{ statusLabel(status) }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem
          :help="$t('supplier.capability.help.languages')"
          :label="$t('supplier.capability.fields.languages')"
        >
          <Select
            v-model:value="form.languages"
            mode="tags"
            :placeholder="$t('supplier.capability.placeholders.languages')"
          />
        </FormItem>
        <FormItem :label="$t('supplier.capability.fields.deliveryMethods')">
          <Select v-model:value="form.delivery_methods" mode="multiple">
            <SelectOption v-for="method in options.delivery_methods" :key="method" :value="method">
              {{ $t(`supplier.capability.deliveryMethods.${method}`) }}
            </SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('supplier.capability.fields.expectedLatency')">
          <InputNumber v-model:value="form.expected_latency_ms" class="w-full" :min="0" :max="86400000" />
        </FormItem>
        <FormItem :label="$t('supplier.capability.fields.packageLimit')">
          <Input.TextArea v-model:value="form.package_limit" :rows="2" />
        </FormItem>
        <FormItem :label="$t('supplier.capability.fields.competitionLimit')">
          <Input.TextArea v-model:value="form.competition_limit" :rows="2" />
        </FormItem>
        <FormItem :label="$t('supplier.capability.fields.lastVerifiedAt')">
          <Input v-model:value="form.last_verified_at" type="datetime-local" />
        </FormItem>
        <FormItem :label="$t('supplier.fields.notes')">
          <Input.TextArea v-model:value="form.notes" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>

<style scoped>
.capabilities-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.capabilities-content > :deep(.admin-list-toolbar) {
  margin-bottom: 0;
}

.capabilities-cell-text {
  white-space: normal;
  word-break: normal;
  overflow-wrap: anywhere;
}
</style>
