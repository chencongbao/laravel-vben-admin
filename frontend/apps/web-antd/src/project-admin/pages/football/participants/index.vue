<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import {computed,onMounted,reactive,ref,watch} from 'vue'; import {useAccess} from '@vben/access'; import {Page} from '@vben/common-ui'; import {Card,Form,FormItem,Input,InputNumber,Modal,Popconfirm,Select,SelectOption,Switch,Table,Tabs,TabPane,message} from 'ant-design-vue'; import {$t} from '#/locales';
import {archiveParticipantRow,createParticipantRow,getParticipantOptions,getParticipantRows,updateParticipantRow,type ParticipantOptions,type ParticipantResource,type ParticipantRow} from '#project-admin/api/football-participants';
const resources:ParticipantResource[]=['teams','players','coaches','referees','venues','team-seasons','player-team-tenures']; const active=ref<ParticipantResource>('teams'),rows=ref<ParticipantRow[]>([]),loading=ref(false),saving=ref(false),open=ref(false),editing=ref<ParticipantRow>(),keyword=ref(''); const form=reactive<Record<string,any>>({}); const options=ref<ParticipantOptions>({countries:[],seasons:[],teams:[],players:[],team_types:[],genders:[],age_groups:[],positions:[]}); const {hasAccessByCodes}=useAccess();
const named=computed(()=>['teams','players','coaches','referees','venues'].includes(active.value)); const can=(x:string)=>hasAccessByCodes(['*'])||hasAccessByCodes([`football.participant.${x}`]); const columns=computed(()=>[{dataIndex:'id',title:'ID',width:70},{dataIndex:'name_en',title:$t('footballParticipants.fields.main'),width:580},{dataIndex:'updated_at',title:$t('footballParticipants.fields.updatedAt'),width:300},{key:'actions',title:$t('footballParticipants.actions.actions'),width:150, fixed: 'right' }]);
async function load(){loading.value=true;try{rows.value=(await getParticipantRows(active.value,{keyword:keyword.value||undefined,per_page:100})).data;}catch{message.error($t('footballParticipants.messages.loadFailed'));}finally{loading.value=false;}} async function refresh(){options.value=await getParticipantOptions();} function reset(row?:ParticipantRow){Object.keys(form).forEach(k=>delete form[k]);Object.assign(form,{is_active:true,is_current:false,type:'club',gender:'male',age_group:'senior',primary_position:'unknown',...row});} function show(row?:ParticipantRow){editing.value=row;reset(row);open.value=true;} async function save(){
  if (saving.value) return;
saving.value=true;try{const payload=Object.fromEntries(Object.entries(form).filter(([k])=>!['id','created_at','updated_at'].includes(k)));editing.value?await updateParticipantRow(active.value,editing.value.id,payload):await createParticipantRow(active.value,payload);open.value=false;message.success($t('footballParticipants.messages.saved'));await Promise.all([load(),refresh()]);}catch{message.error($t('footballParticipants.messages.saveFailed'));}finally{saving.value=false;}} async function archive(row:ParticipantRow){
 if (saving.value) return;
 saving.value = true;
 try { try{await archiveParticipantRow(active.value,row.id);message.success($t('footballParticipants.messages.archived'));await Promise.all([load(),refresh()]);}catch{message.error($t('footballParticipants.messages.archiveFailed'));} } finally { saving.value = false; }
} onMounted(async()=>{await refresh();await load();});
const showFilters = ref(true);
const { setTableRef, tableScrollY, updateTableScrollY } = useAdminTableScrollY();
const participantTableScrollY = computed(() => tableScrollY.value + 64);
watch([showFilters, named], updateTableScrollY, { flush: 'post' });

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; keyword.value = ''; searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('footballParticipants.actions.edit'), icon: 'lucide:pencil', visible: Boolean(can('update')), danger: false, onClick: () => show(record), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('footballParticipants.actions.archive'), icon: 'lucide:archive', visible: Boolean(can('archive')), danger: true, onClick: () => archive(record), confirmTitle: $t('footballParticipants.archivePrompt'), buttonProps: {"type": "link"} },
  ];
}
</script>
<template>
  <Page :title="$t('footballParticipants.title')" :description="$t('footballParticipants.description')">
    <div class="participants-content">
      <Tabs
        class="participants-tabs"
        v-model:active-key="active"
        @change="
          () => {
            keyword = '';
            load();
          }
        "
      >
        <TabPane v-for="r in resources" :key="r" :tab="$t(`footballParticipants.resources.${r}`)" />
      </Tabs>
      <ListToolbar>
        <template #left>
          <PermissionButton icon="lucide:refresh-cw" :loading="loading" @click="load">
            {{ $t('businessUi.refresh') }}
          </PermissionButton>
          <PermissionButton
            v-if="named"
            icon="lucide:filter"
            :aria-pressed="showFilters"
            @click="showFilters = !showFilters"
          >
            {{ $t('businessUi.filter') }}
          </PermissionButton>
        </template>
        <template #right>
          <PermissionButton icon="lucide:plus" v-if="can('create')" type="primary" @click="show()">
            {{ $t('footballParticipants.actions.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters && named">
        <ListSearchField :label="$t('footballParticipants.keyword')">
          <Input
            v-if="named"
            v-model:value="keyword"
            allow-clear
            :placeholder="$t('footballParticipants.keyword')"
            @press-enter="searchFilters"
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
          :ref="setTableRef"
          :scroll="{ x: 1100, y: participantTableScrollY }"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record, text }">
            <template v-if="column.key === 'actions'">
              <BusinessRowActions :actions="rowActions(record)" />
            </template>
            <template v-else-if="column.dataIndex === 'name_en' || column.dataIndex === 'updated_at'">
              <span class="participants-cell-text">{{ text }}</span>
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
      :title="$t(editing ? 'footballParticipants.editTitle' : 'footballParticipants.createTitle')"
      @ok="save"
    >
      <Form layout="vertical">
        <FormItem v-if="named" :label="$t('footballParticipants.fields.name_en')" required>
          <Input v-model:value="form.name_en" />
        </FormItem>
        <FormItem v-if="named" :label="$t('footballParticipants.fields.country_id')">
          <Select v-model:value="form.country_id" allow-clear>
            <SelectOption v-for="x in options.countries" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
          </Select>
        </FormItem>
        <template v-if="active === 'teams'">
          <FormItem :label="$t('footballParticipants.fields.code')">
            <Input v-model:value="form.code" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.short_name_en')">
            <Input v-model:value="form.short_name_en" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.type')">
            <Select v-model:value="form.type">
              <SelectOption v-for="x in options.team_types" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.age_group')">
            <Select v-model:value="form.age_group">
              <SelectOption v-for="x in options.age_groups" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.founded_year')">
            <InputNumber v-model:value="form.founded_year" class="w-full" />
          </FormItem>
        </template>
        <template v-if="active === 'players'">
          <FormItem :label="$t('footballParticipants.fields.date_of_birth')">
            <Input v-model:value="form.date_of_birth" type="date" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.position')">
            <Select v-model:value="form.primary_position">
              <SelectOption v-for="x in options.positions" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.height_cm')">
            <InputNumber v-model:value="form.height_cm" class="w-full" />
          </FormItem>
        </template>
        <template v-if="['coaches', 'referees'].includes(active)">
          <FormItem :label="$t('footballParticipants.fields.date_of_birth')">
            <Input v-model:value="form.date_of_birth" type="date" />
          </FormItem>
        </template>
        <template v-if="active === 'venues'">
          <FormItem :label="$t('footballParticipants.fields.city_en')">
            <Input v-model:value="form.city_en" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.capacity')">
            <InputNumber v-model:value="form.capacity" class="w-full" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.timezone')">
            <Input v-model:value="form.timezone" placeholder="Europe/London" />
          </FormItem>
        </template>
        <template v-if="active === 'team-seasons'">
          <FormItem :label="$t('footballParticipants.fields.team_id')" required>
            <Select v-model:value="form.team_id">
              <SelectOption v-for="x in options.teams" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.season_id')" required>
            <Select v-model:value="form.season_id">
              <SelectOption v-for="x in options.seasons" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.squad_name_en')">
            <Input v-model:value="form.squad_name_en" />
          </FormItem>
        </template>
        <template v-if="active === 'player-team-tenures'">
          <FormItem :label="$t('footballParticipants.fields.player_id')" required>
            <Select v-model:value="form.player_id">
              <SelectOption v-for="x in options.players" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.team_id')" required>
            <Select v-model:value="form.team_id">
              <SelectOption v-for="x in options.teams" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.season_id')">
            <Select v-model:value="form.season_id" allow-clear>
              <SelectOption v-for="x in options.seasons" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.shirt_number')">
            <InputNumber v-model:value="form.shirt_number" class="w-full" />
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.position')">
            <Select v-model:value="form.position" allow-clear>
              <SelectOption v-for="x in options.positions" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballParticipants.fields.is_current')">
            <Switch v-model:checked="form.is_current" />
          </FormItem>
        </template>
        <FormItem
          v-if="['team-seasons', 'player-team-tenures'].includes(active)"
          :label="$t('footballParticipants.fields.starts_on')"
        >
          <Input v-model:value="form.starts_on" type="date" />
        </FormItem>
        <FormItem
          v-if="['team-seasons', 'player-team-tenures'].includes(active)"
          :label="$t('footballParticipants.fields.ends_on')"
        >
          <Input v-model:value="form.ends_on" type="date" />
        </FormItem>
        <FormItem v-if="named" :label="$t('footballParticipants.fields.is_active')">
          <Switch v-model:checked="form.is_active" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>

<style scoped>
.participants-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.participants-content > :deep(.admin-list-toolbar),
.participants-content > :deep(.admin-list-search-panel),
.participants-tabs > :deep(.ant-tabs-nav) {
  margin-bottom: 0;
}

.participants-cell-text {
  white-space: normal;
  word-break: normal;
  overflow-wrap: anywhere;
}
</style>
