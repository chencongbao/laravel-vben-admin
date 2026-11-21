<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAccess } from '@vben/access'; import { Page } from '@vben/common-ui';
import { Card, Form, FormItem, Input, InputNumber, Modal, Select, SelectOption, Space, Table, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales'; import { createDataFieldLock, dataFieldLocks, releaseDataFieldLock } from '#project-admin/api/dataFieldLocks';
const { hasAccessByCodes } = useAccess(); const rows=ref<any[]>([]); const loading=ref(false); const saving=ref(false); const open=ref(false); const selected=ref<any>();
const pagination=reactive({current:1,pageSize:20,total:0}); const filters=reactive<any>({match_id:undefined,status:'active'}); const createForm=reactive<any>({conflict_id:undefined,reason:''}); const releaseReason=ref('');
const canManage=computed(()=>hasAccessByCodes(['*'])||hasAccessByCodes(['supplier.field-lock.manage']));
const columns=computed(()=>[{dataIndex:'match_id',title:$t('dataFieldLock.fields.match')},{dataIndex:'data_group',title:$t('dataFieldLock.fields.group')},{dataIndex:'field_path',title:$t('dataFieldLock.fields.field')},{dataIndex:'origin_conflict_id',title:$t('dataFieldLock.fields.conflict')},{dataIndex:'status',title:$t('dataFieldLock.fields.status')},{dataIndex:'locked_at',title:$t('dataFieldLock.fields.lockedAt')},{key:'actions',title:$t('dataFieldLock.fields.actions'), fixed: 'right' , width: 200 }]);
async function load(){loading.value=true;try{const r:any=await dataFieldLocks({...filters,page:pagination.current,per_page:pagination.pageSize});rows.value=r.data;pagination.total=r.total;}finally{loading.value=false;}}
async function createLock(){
  if (saving.value) return;
if(!createForm.conflict_id||!createForm.reason.trim())return void message.warning($t('dataFieldLock.messages.required'));saving.value=true;try{await createDataFieldLock(createForm);message.success($t('dataFieldLock.messages.locked'));createForm.reason='';await load();}catch{message.error($t('dataFieldLock.messages.lockFailed'));}finally{saving.value=false;}}
function showRelease(record:any){selected.value=record;releaseReason.value='';open.value=true;} async function release(){
  if (saving.value) return;
if(!releaseReason.value.trim())return void message.warning($t('dataFieldLock.messages.reasonRequired'));saving.value=true;try{await releaseDataFieldLock(selected.value.id,{reason:releaseReason.value,version:selected.value.version});message.success($t('dataFieldLock.messages.released'));open.value=false;await load();}catch{message.error($t('dataFieldLock.messages.releaseFailed'));}finally{saving.value=false;}}
function tableChange(p:any){pagination.current=p.current;pagination.pageSize=p.pageSize;load();} onMounted(load);
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; pagination.current = 1; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { match_id: undefined, status: 'active' }); searchFilters(); }
</script>
<template>
  <Page :title="$t('dataFieldLock.title')" :description="$t('dataFieldLock.description')">
    <Card v-if="canManage" :title="$t('dataFieldLock.create')" class="mb-4">
      <Space wrap>
        <InputNumber
          v-model:value="createForm.conflict_id"
          :min="1"
          :placeholder="$t('dataFieldLock.fields.conflict')"
        />
        <Input
          v-model:value="createForm.reason"
          :placeholder="$t('dataFieldLock.fields.reason')"
          style="width: 320px"
        />
        <PermissionButton icon="lucide:plus" type="primary" :loading="saving" @click="createLock">
          {{ $t('dataFieldLock.create') }}
        </PermissionButton>
      </Space>
    </Card>
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
        <ListSearchField :label="$t('dataFieldLock.fields.match')">
          <InputNumber
            @press-enter="searchFilters"
            v-model:value="filters.match_id"
            :min="1"
            :placeholder="$t('dataFieldLock.fields.match')"
          />
        </ListSearchField>
        <ListSearchField :label="$t('dataFieldLock.fields.status')">
          <Select v-model:value="filters.status" allow-clear>
            <SelectOption value="active">{{ $t('dataFieldLock.statuses.active') }}</SelectOption>
            <SelectOption value="released">{{ $t('dataFieldLock.statuses.released') }}</SelectOption>
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
              <Tag :color="record.status === 'active' ? 'red' : 'default'">
                {{ $t(`dataFieldLock.statuses.${record.status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'actions'">
              <PermissionButton
                icon="lucide:unlock"
                v-if="canManage && record.status === 'active'"
                type="link"
                @click="showRelease(record)"
              >
                {{ $t('dataFieldLock.release') }}
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
      :confirm-loading="saving"
      :title="$t('dataFieldLock.release')"
      @ok="release"
    >
      <Form layout="vertical">
        <FormItem :label="$t('dataFieldLock.fields.reason')" required>
          <Input.TextArea v-model:value="releaseReason" :rows="3" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
