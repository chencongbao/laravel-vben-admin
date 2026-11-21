<script lang="ts" setup>
import BusinessRowActions, { type BusinessRowAction } from '#project-admin/components/BusinessRowActions.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { useAdminTableScrollY } from '#/utils/table';
import {computed,onMounted,reactive,ref,watch} from 'vue'; import {useAccess} from '@vben/access'; import {Page} from '@vben/common-ui'; import {Card,Form,FormItem,Input,InputNumber,Modal,Popconfirm,Select,SelectOption,Space,Switch,Table,Tag,message} from 'ant-design-vue'; import {$t} from '#/locales';
import {addPeriod,archiveMatch,changeMatchStatus,createMatch,getMatch,getMatchOptions,getMatches,saveLineup,saveScore,updateMatch,type MatchOptions,type MatchRow} from '#project-admin/api/football-matches';
import EventManager from './event-manager.vue';
import CommentaryManager from './commentary-manager.vue';
import StatisticManager from './statistic-manager.vue';
const rows=ref<MatchRow[]>([]),options=ref<MatchOptions>({}),loading=ref(false),saving=ref(false),statusSaving=ref(false),lineupSaving=ref(false),open=ref(false),detailsOpen=ref(false),editing=ref<MatchRow>(),selected=ref<MatchRow>(); const status=ref<string>(); const form=reactive<Record<string,any>>({}),score=reactive<Record<string,any>>({type:'regular',home_score:0,away_score:0,is_confirmed:false}),period=reactive<Record<string,any>>({type:'first_half',sequence:1}),statusForm=reactive({status:'',reason:''}),lineupForm=reactive<Record<string,any>>({team_id:undefined,status:'predicted',formation:'',reason:'',players:[]}); const {hasAccessByCodes}=useAccess(); const can=(x:string)=>hasAccessByCodes(['*'])||hasAccessByCodes([`football.match.${x}`]);
const transitions:Record<string,string[]>={scheduled:['live','postponed','cancelled'],live:['halftime','finished','suspended'],halftime:['live','suspended'],postponed:['scheduled','cancelled'],suspended:['live','scheduled','finished','cancelled'],finished:[],cancelled:[]};
const allowedStatuses=computed(()=>selected.value?transitions[selected.value.status]??[]:[]);
const columns=computed(()=>[{dataIndex:'id',title:'ID',width:70},{key:'teams',title:`${$t('footballMatches.fields.home')} / ${$t('footballMatches.fields.away')}`,width:560},{dataIndex:'kickoff_at',title:$t('footballMatches.fields.kickoff'),width:300},{dataIndex:'status',title:$t('footballMatches.fields.status'),width:140},{dataIndex:'revision',title:$t('businessUi.revision'),width:90},{key:'actions',title:$t('footballMatches.actions'),width:220, fixed: 'right' }]); const names=(key:string,id:number)=>options.value[key]?.find((x:any)=>x.id===id)?.name??`#${id}`;
async function load(){loading.value=true;try{rows.value=(await getMatches({status:status.value,per_page:100})).data;}finally{loading.value=false;}} function show(row?:MatchRow){editing.value=row;Object.keys(form).forEach(k=>delete form[k]);Object.assign(form,row??{leg:1});open.value=true;} async function submit(){
  if (saving.value) return;
saving.value=true;try{const payload=Object.fromEntries(Object.entries(form).filter(([k])=>!['id','status','revision','created_at','updated_at'].includes(k)));editing.value?await updateMatch(editing.value.id,payload):await createMatch(payload);message.success($t('footballMatches.messages.saved'));open.value=false;await load();}catch{message.error($t('footballMatches.messages.failed'));}finally{saving.value=false;}} async function refreshSelected(){if(!selected.value)return;selected.value=(await getMatch(selected.value.id)).data;await load();} async function showDetails(row:MatchRow){selected.value=(await getMatch(row.id)).data;statusForm.status='';statusForm.reason='';Object.assign(lineupForm,{team_id:undefined,status:'predicted',formation:'',reason:'',players:[]});detailsOpen.value=true;} function prepareLineup(teamId:number){const current=selected.value?.lineups?.find((x:any)=>x.team_id===teamId);Object.assign(lineupForm,{team_id:teamId,status:current?.status??'predicted',formation:current?.formation??'',reason:'',players:(current?.players??[]).map((x:any)=>({...x}))});} function addLineupPlayer(){lineupForm.players.push({player_id:undefined,role:'substitute',position:'unknown',jersey_number:null,sort_order:lineupForm.players.length+1});} function removeLineupPlayer(index:number){lineupForm.players.splice(index,1);lineupForm.players.forEach((x:any,i:number)=>x.sort_order=i+1);} async function submitLineup(){
  if (lineupSaving.value) return;
if(!selected.value||!lineupForm.team_id||lineupForm.reason.trim().length<3){message.error($t('footballMatches.messages.lineupRequired'));return;} lineupSaving.value=true;try{await saveLineup(selected.value.id,lineupForm.team_id,{status:lineupForm.status,formation:lineupForm.formation||null,expected_revision:selected.value.revision,reason:lineupForm.reason.trim(),players:lineupForm.players});selected.value=(await getMatch(selected.value.id)).data;prepareLineup(lineupForm.team_id);await load();message.success($t('footballMatches.messages.lineupSaved'));}catch{message.error($t('footballMatches.messages.lineupFailed'));}finally{lineupSaving.value=false;}} async function submitStatus(){
  if (statusSaving.value) return;
if(!selected.value||!statusForm.status||statusForm.reason.trim().length<3){message.error($t('footballMatches.messages.statusRequired'));return;} statusSaving.value=true;try{selected.value=(await changeMatchStatus(selected.value.id,{status:statusForm.status,expected_revision:selected.value.revision,reason:statusForm.reason.trim()})).data;statusForm.status='';statusForm.reason='';await load();message.success($t('footballMatches.messages.statusChanged'));}catch{message.error($t('footballMatches.messages.statusFailed'));}finally{statusSaving.value=false;}} async function submitScore(){
 if (saving.value) return;
 saving.value = true;
 try { if(!selected.value)return;try{await saveScore(selected.value.id,score);selected.value=(await getMatch(selected.value.id)).data;await load();message.success($t('footballMatches.messages.saved'));}catch{message.error($t('footballMatches.messages.failed'));} } finally { saving.value = false; }
} async function submitPeriod(){
 if (saving.value) return;
 saving.value = true;
 try { if(!selected.value)return;try{await addPeriod(selected.value.id,period);selected.value=(await getMatch(selected.value.id)).data;message.success($t('footballMatches.messages.saved'));}catch{message.error($t('footballMatches.messages.failed'));} } finally { saving.value = false; }
} async function archive(row:MatchRow){
 if (saving.value) return;
 saving.value = true;
 try { try{await archiveMatch(row.id);await load();message.success($t('footballMatches.messages.archived'));}catch{message.error($t('footballMatches.messages.failed'));} } finally { saving.value = false; }
} onMounted(async()=>{options.value=await getMatchOptions();await load();});
const showFilters = ref(true);
const { setTableRef, tableScrollY, updateTableScrollY } = useAdminTableScrollY();
const matchTableScrollY = computed(() => tableScrollY.value + 64);
watch(showFilters, updateTableScrollY, { flush: 'post' });

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; status.value = undefined; searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('footballMatches.scores'), icon: 'lucide:eye', visible: Boolean(true), danger: false, onClick: () => showDetails(record), buttonProps: {"type": "link"} },
    { key: 'action-1', label: $t('footballMatches.edit'), icon: 'lucide:pencil', visible: Boolean(can('update')), danger: false, onClick: () => show(record), buttonProps: {"type": "link"} },
    { key: 'action-2', label: $t('footballMatches.archive'), icon: 'lucide:archive', visible: Boolean(can('archive')), danger: true, onClick: () => archive(record), confirmTitle: $t('footballMatches.confirmArchive'), buttonProps: {"type": "link"} },
  ];
}
</script>
<template>
  <Page :title="$t('footballMatches.title')" :description="$t('footballMatches.description')">
    <div class="matches-content">
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
          <PermissionButton icon="lucide:plus" v-if="can('create')" type="primary" @click="show()">
            {{ $t('footballMatches.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('footballMatches.fields.status')">
          <Select v-model:value="status" allow-clear>
            <SelectOption
              v-for="x in [
                'scheduled',
                'live',
                'halftime',
                'finished',
                'postponed',
                'cancelled',
                'suspended',
              ]"
              :key="x"
              :value="x"
            >
              {{ x }}
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
          :scroll="{ x: 1380, y: matchTableScrollY }"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record, text }">
            <template v-if="column.key === 'teams'">
              <span class="matches-cell-text">
                {{ names('teams', record.home_team_id) }} — {{ names('teams', record.away_team_id) }}
              </span>
            </template>
            <template v-else-if="column.dataIndex === 'kickoff_at'">
              <span class="matches-cell-text">{{ text }}</span>
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
    </div>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="open"
      :confirm-loading="saving"
      :title="$t(editing ? 'footballMatches.edit' : 'footballMatches.create')"
      width="720px"
      @ok="submit"
    >
      <Form layout="vertical">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <FormItem :label="$t('footballMatches.fields.competition')" required>
            <Select v-model:value="form.competition_id">
              <SelectOption v-for="x in options.competitions" :key="x.id" :value="x.id">
                {{ x.name }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.season')" required>
            <Select v-model:value="form.season_id">
              <SelectOption v-for="x in options.seasons" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.home')" required>
            <Select v-model:value="form.home_team_id">
              <SelectOption v-for="x in options.teams" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.away')" required>
            <Select v-model:value="form.away_team_id">
              <SelectOption v-for="x in options.teams" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.stage')">
            <Select v-model:value="form.stage_id" allow-clear>
              <SelectOption v-for="x in options.stages" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.round')">
            <Select v-model:value="form.round_id" allow-clear>
              <SelectOption v-for="x in options.rounds" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.venue')">
            <Select v-model:value="form.venue_id" allow-clear>
              <SelectOption v-for="x in options.venues" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.kickoff')" required>
            <Input v-model:value="form.kickoff_at" type="datetime-local" />
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.leg')">
            <InputNumber v-model:value="form.leg" class="w-full" :min="1" :max="9" />
          </FormItem>
          <FormItem :label="$t('footballMatches.fields.originalName')">
            <Input v-model:value="form.original_name" />
          </FormItem>
        </div>
      </Form>
    </Modal>
    <Modal
      :style="{ maxWidth: 'calc(100vw - 32px)', top: '24px' }"
      :body-style="{ maxHeight: 'calc(100vh - 200px)', overflowY: 'auto' }"
      wrap-class-name="business-modal"
      v-model:open="detailsOpen"
      :footer="null"
      :title="$t('footballMatches.scores')"
      width="1100px"
    >
      <template v-if="selected">
        <p>
          <b>{{ names('teams', selected.home_team_id) }} — {{ names('teams', selected.away_team_id) }}</b>
          · {{ selected.status }} · {{ $t('businessUi.revision') }} {{ selected.revision }}
        </p>
        <Card v-if="can('status')" size="small" :title="$t('footballMatches.statusChange')" class="mb-3">
          <Space wrap direction="vertical" class="w-full">
            <Select
              v-model:value="statusForm.status"
              :placeholder="$t('footballMatches.fields.targetStatus')"
              class="w-full"
            >
              <SelectOption v-for="x in allowedStatuses" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
            <Input
              v-model:value="statusForm.reason"
              :placeholder="$t('footballMatches.fields.statusReason')"
              :maxlength="500"
            />
            <PermissionButton
              icon="lucide:save"
              type="primary"
              :loading="statusSaving"
              :disabled="allowedStatuses.length === 0"
              @click="submitStatus"
            >
              {{ $t('footballMatches.changeStatus') }}
            </PermissionButton>
            <span v-if="allowedStatuses.length === 0" class="text-gray-500">
              {{ $t('footballMatches.terminalStatus') }}
            </span>
          </Space>
        </Card>
        <Card v-if="can('lineup')" size="small" :title="$t('footballMatches.lineups')" class="mb-3">
          <Space wrap direction="vertical" class="w-full">
            <Space wrap>
              <Select
                :value="lineupForm.team_id"
                style="width: 200px"
                :placeholder="$t('footballMatches.fields.lineupTeam')"
                @change="prepareLineup"
              >
                <SelectOption :value="selected.home_team_id">
                  {{ names('teams', selected.home_team_id) }}
                </SelectOption>
                <SelectOption :value="selected.away_team_id">
                  {{ names('teams', selected.away_team_id) }}
                </SelectOption>
              </Select>
              <Select v-model:value="lineupForm.status" style="width: 140px">
                <SelectOption value="predicted">predicted</SelectOption>
                <SelectOption value="confirmed">confirmed</SelectOption>
              </Select>
              <Input
                v-model:value="lineupForm.formation"
                :placeholder="$t('footballMatches.fields.formation')"
                style="width: 140px"
              />
            </Space>
            <Space v-for="(entry, index) in lineupForm.players" :key="index" wrap>
              <Select
                v-model:value="entry.player_id"
                show-search
                option-filter-prop="label"
                style="width: 190px"
              >
                <SelectOption v-for="x in options.players" :key="x.id" :value="x.id" :label="x.name">
                  {{ x.name }}
                </SelectOption>
              </Select>
              <Select v-model:value="entry.role" style="width: 120px">
                <SelectOption value="starter">starter</SelectOption>
                <SelectOption value="substitute">substitute</SelectOption>
              </Select>
              <Select v-model:value="entry.position" style="width: 130px">
                <SelectOption
                  v-for="x in ['goalkeeper', 'defender', 'midfielder', 'forward', 'unknown']"
                  :key="x"
                  :value="x"
                >
                  {{ x }}
                </SelectOption>
              </Select>
              <InputNumber
                v-model:value="entry.jersey_number"
                :min="0"
                :max="999"
                :placeholder="$t('footballMatches.fields.jersey')"
              />
              <PermissionButton icon="lucide:trash-2" danger @click="removeLineupPlayer(index)">
                {{ $t('footballMatches.remove') }}
              </PermissionButton>
            </Space>
            <PermissionButton icon="lucide:plus" @click="addLineupPlayer">
              {{ $t('footballMatches.addPlayer') }}
            </PermissionButton>
            <Input
              v-model:value="lineupForm.reason"
              :placeholder="$t('footballMatches.fields.lineupReason')"
              :maxlength="500"
            />
            <PermissionButton icon="lucide:save" type="primary" :loading="lineupSaving" @click="submitLineup">
              {{ $t('footballMatches.saveLineup') }}
            </PermissionButton>
          </Space>
        </Card>
        <Card size="small" :title="$t('footballMatches.scores')" class="mb-3">
          <Space wrap>
            <Select v-model:value="score.type" style="width: 150px">
              <SelectOption v-for="x in options.score_types" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
            <InputNumber v-model:value="score.home_score" :min="0" />
            <span>:</span>
            <InputNumber v-model:value="score.away_score" :min="0" />
            <Switch v-model:checked="score.is_confirmed" />
            <PermissionButton
              :loading="saving"
              icon="lucide:save"
              v-if="can('score')"
              type="primary"
              @click="submitScore"
            >
              {{ $t('footballMatches.save') }}
            </PermissionButton>
          </Space>
          <div class="mt-3">
            <Tag v-for="x in selected.scores" :key="x.id">
              {{ x.type }} {{ x.home_score }} : {{ x.away_score }}
            </Tag>
          </div>
        </Card>
        <Card size="small" :title="$t('footballMatches.periods')">
          <Space wrap>
            <Select v-model:value="period.type" style="width: 180px">
              <SelectOption v-for="x in options.period_types" :key="x" :value="x">{{ x }}</SelectOption>
            </Select>
            <InputNumber v-model:value="period.sequence" :min="1" />
            <PermissionButton :loading="saving" icon="lucide:save" v-if="can('score')" @click="submitPeriod">
              {{ $t('footballMatches.add') }}
            </PermissionButton>
          </Space>
          <div class="mt-3">
            <Tag v-for="x in selected.periods" :key="x.id">
              {{ x.sequence }}. {{ x.type }} ({{ x.status }})
            </Tag>
          </div>
        </Card>
        <EventManager :match="selected" :options="options" @changed="refreshSelected" />
        <CommentaryManager :match="selected" :options="options" @changed="refreshSelected" />
        <StatisticManager :match="selected" :options="options" @changed="refreshSelected" />
      </template>
    </Modal>
  </Page>
</template>

<style scoped>
.matches-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.matches-content > :deep(.admin-list-toolbar),
.matches-content > :deep(.admin-list-search-panel) {
  margin-bottom: 0;
}

.matches-cell-text {
  white-space: normal;
  word-break: normal;
  overflow-wrap: anywhere;
}
</style>
