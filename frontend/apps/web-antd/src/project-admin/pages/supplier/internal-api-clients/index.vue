<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { Alert, Card, Form, FormItem, Input, Modal, Select, SelectOption, Space, Table, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { changeInternalApiClientScopes, changeInternalApiClientStatus, createInternalApiClient, internalApiClients, rotateInternalApiClient } from '#project-admin/api/internalApiClients';

const { hasAccessByCodes } = useAccess();
const canManage = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['supplier.internal-client.manage']));
const rows = ref<any[]>([]); const loading = ref(false); const saving = ref(false);
const createOpen = ref(false); const actionOpen = ref(false); const secretOpen = ref(false);
const selected = ref<any>(); const action = ref<'rotate' | 'scopes' | 'active' | 'disabled'>('rotate'); const secret = ref('');
const scopeOptions = ['matches:read', 'catalog:read', 'standings:read'];
const createForm = reactive<any>({ name: '', scopes: ['matches:read', 'catalog:read'], expires_at: undefined }); const reason = ref(''); const selectedScopes = ref<string[]>([]);
const filters = reactive<any>({ keyword: '', status: undefined }); const pagination = reactive({ current: 1, pageSize: 20, total: 0 });
const columns = computed(() => [
  { dataIndex: 'name', title: $t('internalClient.fields.name') }, { dataIndex: 'key_id', title: $t('internalClient.fields.keyId') },
  { dataIndex: 'status', title: $t('internalClient.fields.status') }, { dataIndex: 'scopes', title: $t('internalClient.fields.scopes') },
  { dataIndex: 'expires_at', title: $t('internalClient.fields.expiresAt') }, { dataIndex: 'last_used_at', title: $t('internalClient.fields.lastUsedAt') },
  { key: 'actions', title: $t('internalClient.fields.actions') , fixed: 'right' , width: 200 },
]);

async function load() { loading.value = true; try { const result: any = await internalApiClients({ ...filters, page: pagination.current, per_page: pagination.pageSize }); rows.value = result.data; pagination.total = result.total; } finally { loading.value = false; } }
async function createClient() {
  if (saving.value) return;
 if (!createForm.name.trim() || !createForm.scopes.length) return void message.warning($t('internalClient.messages.required')); saving.value = true; try { const result: any = await createInternalApiClient(createForm); secret.value = result.secret; createOpen.value = false; secretOpen.value = true; createForm.name = ''; createForm.scopes = ['matches:read', 'catalog:read']; createForm.expires_at = undefined; message.success($t('internalClient.messages.created')); await load(); } catch { message.error($t('internalClient.messages.createFailed')); } finally { saving.value = false; } }
function showAction(record: any, value: 'rotate' | 'scopes' | 'active' | 'disabled') { selected.value = record; action.value = value; reason.value = ''; selectedScopes.value = [...record.scopes]; actionOpen.value = true; }
async function submitAction() {
  if (saving.value) return;
 if (!reason.value.trim() || (action.value === 'scopes' && !selectedScopes.value.length)) return void message.warning($t('internalClient.messages.required')); saving.value = true; try { if (action.value === 'rotate') { const result: any = await rotateInternalApiClient(selected.value.id, { reason: reason.value }); secret.value = result.secret; secretOpen.value = true; message.success($t('internalClient.messages.rotated')); } else if (action.value === 'scopes') { await changeInternalApiClientScopes(selected.value.id, { scopes: selectedScopes.value, reason: reason.value }); message.success($t('internalClient.messages.scopesChanged')); } else { await changeInternalApiClientStatus(selected.value.id, action.value, { reason: reason.value }); message.success($t('internalClient.messages.statusChanged')); } actionOpen.value = false; await load(); } catch { message.error($t('internalClient.messages.operationFailed')); } finally { saving.value = false; } }
function tableChange(value: any) { pagination.current = value.current; pagination.pageSize = value.pageSize; load(); }
onMounted(load);
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { keyword: '', status: undefined }); searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('internalClient.changeScopes'), icon: 'lucide:settings', visible: Boolean(canManage.value), danger: false, onClick: () => showAction(record, 'scopes'), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('internalClient.rotate'), icon: 'lucide:settings', visible: Boolean(canManage.value), danger: false, onClick: () => showAction(record, 'rotate'), buttonProps: {"type": "link"} },
    { key: 'action-2', label: $t('internalClient.disable'), icon: 'lucide:settings', visible: Boolean(canManage.value && record.status === 'active'), danger: true, onClick: () => showAction(record, 'disabled'), buttonProps: {"type": "link"} },
    { key: 'action-3', label: $t('internalClient.enable'), icon: 'lucide:settings', visible: Boolean(canManage.value && record.status !== 'active'), danger: false, onClick: () => showAction(record, 'active'), buttonProps: {"type": "link"} },
  ];
}
</script>

<template>
  <Page :title="$t('internalClient.title')" :description="$t('internalClient.description')">
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
          <PermissionButton icon="lucide:plus" v-if="canManage" type="primary" @click="createOpen = true">
            {{ $t('internalClient.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('internalClient.fields.name')">
          <Input
            @press-enter="searchFilters"
            v-model:value="filters.keyword"
            :placeholder="$t('internalClient.fields.name')"
          />
        </ListSearchField>
        <ListSearchField :label="$t('internalClient.fields.status')">
          <Select v-model:value="filters.status" allow-clear>
            <SelectOption value="active">{{ $t('internalClient.statuses.active') }}</SelectOption>
            <SelectOption value="disabled">{{ $t('internalClient.statuses.disabled') }}</SelectOption>
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
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="pagination"
          row-key="id"
          @change="tableChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'status'">
              <Tag :color="record.status === 'active' ? 'green' : 'default'">
                {{ $t(`internalClient.statuses.${record.status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.dataIndex === 'scopes'">
              <Tag v-for="scope in record.scopes" :key="scope">{{ scope }}</Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
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
      :title="$t('internalClient.create')"
      @ok="createClient"
    >
      <Form layout="vertical">
        <FormItem :label="$t('internalClient.fields.name')" required>
          <Input v-model:value="createForm.name" />
        </FormItem>
        <FormItem :label="$t('internalClient.fields.scopes')" required>
          <Select v-model:value="createForm.scopes" mode="multiple">
            <SelectOption v-for="scope in scopeOptions" :key="scope" :value="scope">{{ scope }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('internalClient.fields.expiresAt')">
          <Input v-model:value="createForm.expires_at" placeholder="2027-01-01T00:00:00Z" />
        </FormItem>
      </Form>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="actionOpen"
      :confirm-loading="saving"
      :title="
        action === 'rotate'
          ? $t('internalClient.rotate')
          : action === 'scopes'
          ? $t('internalClient.changeScopes')
          : action === 'active'
          ? $t('internalClient.enable')
          : $t('internalClient.disable')
      "
      @ok="submitAction"
    >
      <Form layout="vertical">
        <FormItem v-if="action === 'scopes'" :label="$t('internalClient.fields.scopes')" required>
          <Select v-model:value="selectedScopes" mode="multiple">
            <SelectOption v-for="scope in scopeOptions" :key="scope" :value="scope">{{ scope }}</SelectOption>
          </Select>
        </FormItem>
        <FormItem :label="$t('internalClient.fields.reason')" required>
          <Input.TextArea v-model:value="reason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="secretOpen"
      :footer="null"
      :title="$t('internalClient.secretTitle')"
    >
      <Alert class="mb-4" type="warning" show-icon :message="$t('internalClient.secretWarning')" />
      <Form layout="vertical">
        <FormItem :label="$t('internalClient.fields.secret')">
          <Input.TextArea :value="secret" :rows="3" readonly />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
