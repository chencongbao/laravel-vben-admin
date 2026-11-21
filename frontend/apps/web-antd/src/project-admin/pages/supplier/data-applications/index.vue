<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { computed, onMounted, reactive, ref } from 'vue'; import { useAccess } from '@vben/access'; import { Page } from '@vben/common-ui';
import { Card, Input, InputNumber, Space, Table, message } from 'ant-design-vue'; import { $t } from '#/locales'; import { dataApplications, executeDataApplication, fieldProvenances } from '#project-admin/api/dataApplications';
const {hasAccessByCodes}=useAccess(); const runs=ref<any[]>([]); const provenance=ref<any[]>([]); const loading=ref(false); const saving=ref(false); const filters=reactive<any>({match_id:undefined}); const form=reactive<any>({conflict_id:undefined,reason:'',version:1});
const canExecute=computed(()=>hasAccessByCodes(['*'])||hasAccessByCodes(['supplier.application.execute']));
const runColumns=computed(()=>[{dataIndex:'id',title:'ID'},{dataIndex:'match_id',title:$t('dataApplication.fields.match')},{dataIndex:'field_path',title:$t('dataApplication.fields.field')},{dataIndex:'before_value',title:$t('dataApplication.fields.before')},{dataIndex:'after_value',title:$t('dataApplication.fields.after')},{dataIndex:'match_revision_after',title:$t('dataApplication.fields.revision')},{dataIndex:'executed_at',title:$t('dataApplication.fields.executedAt')}]);
const provenanceColumns=computed(()=>[{dataIndex:'match_id',title:$t('dataApplication.fields.match')},{dataIndex:'field_path',title:$t('dataApplication.fields.field')},{dataIndex:'source_id',title:$t('businessUi.sourceId')},{dataIndex:'raw_record_id',title:$t('businessUi.rawRecordId')},{dataIndex:'applied_value',title:$t('dataApplication.fields.after')},{dataIndex:'match_revision',title:$t('dataApplication.fields.revision')}]);
async function load(){loading.value=true;try{const [a,p]:any[]=await Promise.all([dataApplications({...filters,per_page:50}),fieldProvenances({...filters,per_page:50})]);runs.value=a.data;provenance.value=p.data;}finally{loading.value=false;}}
async function execute(){
  if (saving.value) return;
if(!form.conflict_id||!form.reason.trim())return void message.warning($t('dataApplication.messages.required'));saving.value=true;try{const r:any=await executeDataApplication(form.conflict_id,{reason:form.reason,version:form.version});message.success(r.created?$t('dataApplication.messages.executed'):$t('dataApplication.messages.exists'));await load();}catch{message.error($t('dataApplication.messages.failed'));}finally{saving.value=false;}} onMounted(load);
const showFilters = ref(true);

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; Object.assign(filters, { match_id: undefined }); searchFilters(); }
</script>
<template>
  <Page :title="$t('dataApplication.title')" :description="$t('dataApplication.description')">
    <div class="data-applications-content">
    <Card v-if="canExecute" :title="$t('dataApplication.execute')">
      <Space wrap :size="16">
        <InputNumber
          v-model:value="form.conflict_id"
          :min="1"
          :placeholder="$t('dataApplication.fields.conflict')"
        />
        <Input
          v-model:value="form.reason"
          :placeholder="$t('dataApplication.fields.reason')"
          style="width: 320px"
        />
        <InputNumber
          v-model:value="form.version"
          :min="1"
          :placeholder="$t('dataApplication.fields.version')"
        />
        <PermissionButton icon="lucide:check" type="primary" danger :loading="saving" @click="execute">
          {{ $t('dataApplication.execute') }}
        </PermissionButton>
      </Space>
    </Card>
    <Card :title="$t('dataApplication.runs')">
      <div class="data-applications-runs">
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
        <ListSearchField :label="$t('dataApplication.fields.match')">
          <InputNumber
            @press-enter="searchFilters"
            v-model:value="filters.match_id"
            :min="1"
            :placeholder="$t('dataApplication.fields.match')"
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
          :scroll="{ x: 1100 }"
          bordered
          :columns="runColumns"
          :data-source="runs"
          :loading="loading"
          :pagination="false"
          row-key="id"
        />
      </Card>
      </div>
    </Card>
    <Card :title="$t('dataApplication.provenance')">
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1100 }"
          bordered
          :columns="provenanceColumns"
          :data-source="provenance"
          :loading="loading"
          :pagination="false"
          row-key="id"
        />
      </Card>
    </Card>
    </div>
  </Page>
</template>

<style scoped>
.data-applications-content,
.data-applications-runs {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.data-applications-runs > :deep(.admin-list-toolbar),
.data-applications-runs > :deep(.admin-list-search-panel) {
  margin-bottom: 0;
}
</style>
