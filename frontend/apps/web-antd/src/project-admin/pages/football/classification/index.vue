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
import { Card, Form, FormItem, Input, InputNumber, Modal, Popconfirm, Select, SelectOption, Switch, Table, Tabs, TabPane, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { archiveClassificationRow, createClassificationRow, getClassificationOptions, getClassificationRows, updateClassificationRow, type ClassificationOptions, type ClassificationResource, type ClassificationRow } from '#project-admin/api/football';

const resources: ClassificationResource[] = ['regions', 'countries', 'competitions', 'seasons', 'stages', 'groups', 'rounds'];
const active = ref<ClassificationResource>('regions');
const rows = ref<ClassificationRow[]>([]);
const options = ref<ClassificationOptions>({ competition_types: [], competitions: [], countries: [], genders: [], groups: [], regions: [], seasons: [], stages: [] });
const loading = ref(false); const saving = ref(false); const open = ref(false); const editing = ref<ClassificationRow>(); const keyword = ref('');
const { hasAccessByCodes } = useAccess();
const canCreate = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.classification.create']));
const canUpdate = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.classification.update']));
const canArchive = computed(() => hasAccessByCodes(['*']) || hasAccessByCodes(['football.classification.archive']));
const form = reactive<Record<string, any>>({});
const columns = computed(() => [
  { dataIndex: 'id', key: 'id', title: 'ID', width: 80 },
  { dataIndex: 'name_en', key: 'name_en', title: $t('football.fields.nameEn') },
  { dataIndex: secondaryField.value, key: 'secondary', title: $t(`football.fields.${secondaryField.value}`) },
  { dataIndex: 'updated_at', key: 'updated_at', title: $t('football.fields.updatedAt') },
  { key: 'actions', title: $t('football.actions.actions'), width: 150 , fixed: 'right' },
]);
const secondaryField = computed(() => ({ regions: 'code', countries: 'iso2', competitions: 'type', seasons: 'competition_id', stages: 'season_id', groups: 'stage_id', rounds: 'stage_id' })[active.value]);
const hasOriginal = computed(() => ['regions', 'countries', 'competitions', 'seasons'].includes(active.value));

function resetForm(row?: ClassificationRow) {
  Object.keys(form).forEach((key) => delete form[key]);
  Object.assign(form, { is_active: true, is_current: false, sort: 0, type: active.value === 'competitions' ? 'league' : '', gender: 'male', ...row });
}
async function load() { loading.value = true; try { rows.value = (await getClassificationRows(active.value, { keyword: keyword.value || undefined, per_page: 100 })).data; } catch { message.error($t('football.messages.loadFailed')); } finally { loading.value = false; } }
async function refreshOptions() { options.value = await getClassificationOptions(); }
async function changeResource() { keyword.value = ''; await load(); }
function showCreate() { editing.value = undefined; resetForm(); open.value = true; }
function showEdit(row: ClassificationRow) { editing.value = row; resetForm(row); open.value = true; }
async function save() {
  if (saving.value) return;

  if (!form.name_en?.trim()) return void message.warning($t('football.messages.required'));
  saving.value = true;
  try {
    const payload = Object.fromEntries(Object.entries(form).filter(([key]) => !['id', 'created_at', 'updated_at'].includes(key)));
    editing.value ? await updateClassificationRow(active.value, editing.value.id, payload) : await createClassificationRow(active.value, payload);
    message.success($t('football.messages.saved')); open.value = false; await Promise.all([load(), refreshOptions()]);
  } catch { message.error($t('football.messages.saveFailed')); } finally { saving.value = false; }
}
async function archive(row: ClassificationRow) {
 if (saving.value) return;
 saving.value = true;
 try {  try { await archiveClassificationRow(active.value, row.id); message.success($t('football.messages.archived')); await Promise.all([load(), refreshOptions()]); } catch { message.error($t('football.messages.archiveFailed')); }  } finally { saving.value = false; }
}
onMounted(async () => { await refreshOptions(); await load(); });
const showFilters = ref(true);
const { setTableRef, tableScrollY } = useAdminTableScrollY();

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; keyword.value = ''; searchFilters(); }

function rowActions(record: any): BusinessRowAction[] {
  return [
    { key: 'action-0', label: $t('football.actions.edit'), icon: 'lucide:pencil', visible: Boolean(canUpdate.value), danger: false, onClick: () => showEdit(record), buttonProps: {"type": "link", "size": "small"} },
    { key: 'action-1', label: $t('football.actions.archive'), icon: 'lucide:archive', visible: Boolean(canArchive.value), danger: true, onClick: () => archive(record), confirmTitle: $t('football.prompts.archive'), buttonProps: {"type": "link", "size": "small"} },
  ];
}
</script>

<template>
  <Page :description="$t('football.classification.description')" :title="$t('football.classification.title')">
    <div>
      <Tabs v-model:active-key="active" @change="changeResource">
        <TabPane v-for="resource in resources" :key="resource" :tab="$t(`football.resources.${resource}`)" />
      </Tabs>
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
          <PermissionButton icon="lucide:plus" v-if="canCreate" type="primary" @click="showCreate">
            {{ $t('football.actions.create') }}
          </PermissionButton>
        </template>
      </ListToolbar>
      <ListSearchPanel v-show="showFilters">
        <ListSearchField :label="$t('football.placeholders.keyword')">
          <Input
            v-model:value="keyword"
            :placeholder="$t('football.placeholders.keyword')"
            allow-clear
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
          :scroll="{ x: 1100, y: tableScrollY }"
          bordered
          :columns="columns"
          :data-source="rows"
          :loading="loading"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'secondary'">{{ record[secondaryField] ?? '-' }}</template>
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
      :title="$t(editing ? 'football.form.editTitle' : 'football.form.createTitle')"
      @ok="save"
    >
      <Form layout="vertical">
        <FormItem :label="$t('football.fields.nameEn')" required>
          <Input v-model:value="form.name_en" />
        </FormItem>
        <template v-if="active === 'regions'">
          <FormItem :label="$t('football.fields.code')" required>
            <Input v-model:value="form.code" />
          </FormItem>
          <FormItem :label="$t('football.fields.parent_id')">
            <Select v-model:value="form.parent_id" allow-clear>
              <SelectOption v-for="x in options.regions" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
        </template>
        <template v-if="active === 'countries'">
          <FormItem :label="$t('football.fields.iso2')" required>
            <Input v-model:value="form.iso2" :maxlength="2" />
          </FormItem>
          <FormItem :label="$t('football.fields.iso3')">
            <Input v-model:value="form.iso3" :maxlength="3" />
          </FormItem>
          <FormItem :label="$t('football.fields.region_id')">
            <Select v-model:value="form.region_id" allow-clear>
              <SelectOption v-for="x in options.regions" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
        </template>
        <template v-if="active === 'competitions'">
          <FormItem :label="$t('football.fields.code')"><Input v-model:value="form.code" /></FormItem>
          <FormItem :label="$t('football.fields.country_id')">
            <Select v-model:value="form.country_id" allow-clear>
              <SelectOption v-for="x in options.countries" :key="x.id" :value="x.id">
                {{ x.name }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.region_id')">
            <Select v-model:value="form.region_id" allow-clear>
              <SelectOption v-for="x in options.regions" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.type')" required>
            <Select v-model:value="form.type">
              <SelectOption v-for="x in options.competition_types" :key="x" :value="x">
                {{ $t(`football.types.${x}`) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.gender')" required>
            <Select v-model:value="form.gender">
              <SelectOption v-for="x in options.genders" :key="x" :value="x">
                {{ $t(`football.genders.${x}`) }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.level')">
            <InputNumber v-model:value="form.level" class="w-full" :min="1" />
          </FormItem>
        </template>
        <template v-if="active === 'seasons'">
          <FormItem :label="$t('football.fields.competition_id')" required>
            <Select v-model:value="form.competition_id">
              <SelectOption v-for="x in options.competitions" :key="x.id" :value="x.id">
                {{ x.name }}
              </SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.starts_on')">
            <Input v-model:value="form.starts_on" type="date" />
          </FormItem>
          <FormItem :label="$t('football.fields.ends_on')">
            <Input v-model:value="form.ends_on" type="date" />
          </FormItem>
          <FormItem :label="$t('football.fields.is_current')">
            <Switch v-model:checked="form.is_current" />
          </FormItem>
        </template>
        <template v-if="active === 'stages'">
          <FormItem :label="$t('football.fields.season_id')" required>
            <Select v-model:value="form.season_id">
              <SelectOption v-for="x in options.seasons" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.type')"><Input v-model:value="form.type" /></FormItem>
          <FormItem :label="$t('football.fields.sort')">
            <InputNumber v-model:value="form.sort" class="w-full" :min="0" />
          </FormItem>
        </template>
        <template v-if="active === 'groups'">
          <FormItem :label="$t('football.fields.stage_id')" required>
            <Select v-model:value="form.stage_id">
              <SelectOption v-for="x in options.stages" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.sort')">
            <InputNumber v-model:value="form.sort" class="w-full" :min="0" />
          </FormItem>
        </template>
        <template v-if="active === 'rounds'">
          <FormItem :label="$t('football.fields.stage_id')" required>
            <Select v-model:value="form.stage_id">
              <SelectOption v-for="x in options.stages" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.competition_group_id')">
            <Select v-model:value="form.competition_group_id" allow-clear>
              <SelectOption v-for="x in options.groups" :key="x.id" :value="x.id">{{ x.name }}</SelectOption>
            </Select>
          </FormItem>
          <FormItem :label="$t('football.fields.sequence')">
            <InputNumber v-model:value="form.sequence" class="w-full" :min="0" />
          </FormItem>
          <FormItem :label="$t('football.fields.starts_at')">
            <Input v-model:value="form.starts_at" type="datetime-local" />
          </FormItem>
          <FormItem :label="$t('football.fields.ends_at')">
            <Input v-model:value="form.ends_at" type="datetime-local" />
          </FormItem>
        </template>
        <template v-if="hasOriginal">
          <FormItem :label="$t('football.fields.original_name')">
            <Input v-model:value="form.original_name" />
          </FormItem>
          <FormItem :label="$t('football.fields.original_locale')">
            <Input v-model:value="form.original_locale" :placeholder="$t('football.placeholders.locale')" />
          </FormItem>
        </template>
        <FormItem
          v-if="['regions', 'countries', 'competitions'].includes(active)"
          :label="$t('football.fields.is_active')"
        >
          <Switch v-model:checked="form.is_active" />
        </FormItem>
      </Form>
    </Modal>
  </Page>
</template>
