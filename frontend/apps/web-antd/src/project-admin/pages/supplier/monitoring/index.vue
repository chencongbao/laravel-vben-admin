<script lang="ts" setup>
import PermissionButton from '#/components/system/permission-button.vue';
import '#project-admin/components/business-ui.css';
import ListToolbar from '#/components/system/list-toolbar.vue';
import ListSearchPanel from '#/components/system/list-search-panel.vue';
import ListSearchField from '#/components/system/list-search-field.vue';
import { computed, onMounted, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Card, Col, Row, Select, SelectOption, Statistic, Table, Tag, message } from 'ant-design-vue';
import { $t } from '#/locales';
import { supplierMonitoringOverview } from '#project-admin/api/supplierMonitoring';

const loading = ref(false);
const windowHours = ref(24);
const qualityDays = ref(30);
const result = ref<any>({ summary: { sources: {}, runs: {}, raw_records: {}, mappings: {}, api_calls: {}, score_polling: {}, takeovers: {} }, sources: [], quality: { eligible_matches: 0, metrics: {} }, delivery_latency: { metrics: {} }, post_match_revisions: {}, conflict_quality: {} });
const columns = computed(() => [
  { dataIndex: 'name', title: $t('supplierMonitoring.fields.source') },
  { dataIndex: 'status', title: $t('supplierMonitoring.fields.status') },
  { dataIndex: 'health_status', title: $t('supplierMonitoring.fields.health') },
  { key: 'scorePolling', title: $t('supplierMonitoring.fields.scorePolling') },
  { key: 'runs', title: $t('supplierMonitoring.fields.runs') },
  { key: 'apiCalls', title: $t('supplierMonitoring.fields.apiCalls') },
  { key: 'apiLatency', title: $t('supplierMonitoring.fields.apiLatency') },
  { dataIndex: 'pending_mappings', title: $t('supplierMonitoring.fields.pendingMappings') },
  { key: 'latest', title: $t('supplierMonitoring.fields.latestRun') },
  { dataIndex: 'last_success_at', title: $t('supplierMonitoring.fields.lastSuccess') },
]);
const qualityRows = computed(() => Object.entries(result.value.quality?.metrics || {}).map(([key, value]: [string, any]) => ({ key, ...value })));
const qualityColumns = computed(() => [
  { dataIndex: 'key', title: $t('supplierMonitoring.quality.fields.group') },
  { dataIndex: 'matches', title: $t('supplierMonitoring.quality.fields.matches') },
  { key: 'rate', title: $t('supplierMonitoring.quality.fields.rate') },
]);
const latencyRows = computed(() => Object.entries(result.value.delivery_latency?.metrics || {}).map(([key, value]: [string, any]) => ({ key, ...value })));
const latencyColumns = computed(() => [
  { dataIndex: 'key', title: $t('supplierMonitoring.latency.fields.resource') },
  { dataIndex: 'samples', title: $t('supplierMonitoring.latency.fields.samples') },
  { dataIndex: 'average_ms', title: $t('supplierMonitoring.latency.fields.average') },
  { dataIndex: 'p50_ms', title: 'P50' },
  { dataIndex: 'p95_ms', title: 'P95' },
  { dataIndex: 'p99_ms', title: 'P99' },
]);

function healthColor(status: string) {
  if (status === 'healthy') return 'green';
  if (['delayed', 'degraded', 'recovering'].includes(status)) return 'orange';
  if (status === 'unavailable') return 'red';
  return 'default';
}

async function load() {
  loading.value = true;
  try {
    result.value = await supplierMonitoringOverview(windowHours.value, qualityDays.value);
  } catch {
    message.error($t('supplierMonitoring.messages.failed'));
  } finally {
    loading.value = false;
  }
}

onMounted(load);
const showFilters = ref(true);

function searchFilters() { if (loading.value) return; void load(); }
function resetSearchFilters() { if (loading.value) return; windowHours.value = 24; qualityDays.value = 30; searchFilters(); }
</script>

<template>
  <Page :title="$t('supplierMonitoring.title')" :description="$t('supplierMonitoring.description')">
    <div class="monitoring-content">
    <Card>
      <div class="monitoring-filters">
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
        <ListSearchField :label="$t('supplierMonitoring.window')">
          <Select v-model:value="windowHours" @change="load">
            <SelectOption :value="6">{{ $t('supplierMonitoring.windows.6') }}</SelectOption>
            <SelectOption :value="24">{{ $t('supplierMonitoring.windows.24') }}</SelectOption>
            <SelectOption :value="72">{{ $t('supplierMonitoring.windows.72') }}</SelectOption>
            <SelectOption :value="168">{{ $t('supplierMonitoring.windows.168') }}</SelectOption>
          </Select>
        </ListSearchField>
        <ListSearchField :label="$t('supplierMonitoring.quality.window')">
          <Select v-model:value="qualityDays" @change="load">
            <SelectOption :value="7">{{ $t('supplierMonitoring.quality.windows.7') }}</SelectOption>
            <SelectOption :value="30">{{ $t('supplierMonitoring.quality.windows.30') }}</SelectOption>
            <SelectOption :value="90">{{ $t('supplierMonitoring.quality.windows.90') }}</SelectOption>
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
      </div>
    </Card>
    <div class="monitoring-summary">
    <Row :gutter="[16, 16]">
      <Col :xs="24" :sm="12" :xl="6">
        <div>
          <Statistic
            :title="$t('supplierMonitoring.cards.activeSources')"
            :value="result.summary.sources.active || 0"
            :suffix="`/ ${result.summary.sources.total || 0}`"
          />
        </div>
      </Col>
      <Col :xs="24" :sm="12" :xl="6">
        <div>
          <Statistic
            :title="$t('supplierMonitoring.cards.completedRuns')"
            :value="result.summary.runs.completed || 0"
            :suffix="`/ ${result.summary.runs.total || 0}`"
          />
        </div>
      </Col>
      <Col :xs="24" :sm="12" :xl="6">
        <div>
          <Statistic
            :title="$t('supplierMonitoring.cards.pendingRecords')"
            :value="result.summary.raw_records.pending || 0"
          />
        </div>
      </Col>
      <Col :xs="24" :sm="12" :xl="6">
        <div>
          <Statistic
            :title="$t('supplierMonitoring.cards.pendingMappings')"
            :value="result.summary.mappings.pending || 0"
          />
        </div>
      </Col>
    </Row>
    </div>
    <Card :title="$t('supplierMonitoring.scorePolling.title')">
      <Row :gutter="[16, 16]">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.scorePolling.enabled')"
            :value="result.summary.score_polling.enabled || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.scorePolling.due')"
            :value="result.summary.score_polling.due || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.scorePolling.claimed')"
            :value="result.summary.score_polling.claimed || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.scorePolling.staleClaims')"
            :value="result.summary.score_polling.stale_claims || 0"
          />
        </Col>
      </Row>
      <p class="monitoring-note text-muted-foreground">{{ $t('supplierMonitoring.scorePolling.description') }}</p>
    </Card>
    <Card :title="$t('supplierMonitoring.apiQuality.title')">
      <Row :gutter="[16, 16]">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.apiQuality.total')"
            :value="result.summary.api_calls.total || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            v-if="result.summary.api_calls.error_rate != null"
            :title="$t('supplierMonitoring.apiQuality.errorRate')"
            :value="result.summary.api_calls.error_rate * 100"
            :precision="1"
            suffix="%"
          />
          <template v-else>
            <div class="text-sm text-muted-foreground">
              {{ $t('supplierMonitoring.apiQuality.errorRate') }}
            </div>
            <div class="mt-1 text-2xl">-</div>
          </template>
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.apiQuality.rateLimited')"
            :value="result.summary.api_calls.rate_limited || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            v-if="result.summary.api_calls.p95_duration_ms != null"
            :title="$t('supplierMonitoring.apiQuality.p95')"
            :value="result.summary.api_calls.p95_duration_ms"
            suffix="ms"
          />
          <template v-else>
            <div class="text-sm text-muted-foreground">{{ $t('supplierMonitoring.apiQuality.p95') }}</div>
            <div class="mt-1 text-2xl">-</div>
          </template>
        </Col>
      </Row>
      <p class="monitoring-note text-muted-foreground">{{ $t('supplierMonitoring.apiQuality.description') }}</p>
    </Card>
    <Card :title="$t('supplierMonitoring.takeovers.title')">
      <Row :gutter="[16, 16]">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.currentlyActive')"
            :value="result.summary.takeovers.currently_active || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.pending')"
            :value="result.summary.takeovers.pending || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.activated')"
            :value="result.summary.takeovers.activated || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.failed')"
            :value="result.summary.takeovers.failed || 0"
          />
        </Col>
      </Row>
      <Row :gutter="[16, 16]" class="monitoring-next-row">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.proposed')"
            :value="result.summary.takeovers.proposed || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.closed')"
            :value="result.summary.takeovers.closed || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.takeovers.recoveryPending')"
            :value="result.summary.takeovers.recovery_pending || 0"
          />
        </Col>
      </Row>
      <p class="monitoring-note text-muted-foreground">{{ $t('supplierMonitoring.takeovers.description') }}</p>
    </Card>
    <Card :title="$t('supplierMonitoring.quality.title')">
      <p class="monitoring-intro text-muted-foreground">
        {{ $t('supplierMonitoring.quality.description', { count: result.quality.eligible_matches || 0 }) }}
      </p>
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1100 }"
          bordered
          :columns="qualityColumns"
          :data-source="qualityRows"
          :loading="loading"
          :pagination="false"
          row-key="key"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'key'">
              {{ $t(`supplierMonitoring.quality.groups.${record.key}`) }}
            </template>
            <template v-else-if="column.key === 'rate'">
              {{ record.rate === null ? '-' : `${(record.rate * 100).toFixed(1)}%` }}
            </template>
          </template>
        </Table>
      </Card>
      <p class="monitoring-note text-muted-foreground">{{ $t('supplierMonitoring.quality.varNote') }}</p>
    </Card>
    <Card :title="$t('supplierMonitoring.latency.title')">
      <p class="monitoring-intro text-muted-foreground">{{ $t('supplierMonitoring.latency.description') }}</p>
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1100 }"
          bordered
          :columns="latencyColumns"
          :data-source="latencyRows"
          :loading="loading"
          :pagination="false"
          row-key="key"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'key'">
              {{ $t(`supplierMonitoring.latency.resources.${record.key}`) }}
            </template>
            <template v-else-if="column.dataIndex !== 'samples'">
              {{ record[column.dataIndex] === null ? '-' : `${record[column.dataIndex]} ms` }}
            </template>
          </template>
        </Table>
      </Card>
    </Card>
    <Card :title="$t('supplierMonitoring.postMatchRevisions.title')">
      <Row :gutter="[16, 16]">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.postMatchRevisions.eligible')"
            :value="result.post_match_revisions.eligible_matches || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.postMatchRevisions.revised')"
            :value="result.post_match_revisions.revised_matches || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.postMatchRevisions.revisions')"
            :value="result.post_match_revisions.revision_count || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            v-if="result.post_match_revisions.rate != null"
            :title="$t('supplierMonitoring.postMatchRevisions.rate')"
            :value="result.post_match_revisions.rate * 100"
            :precision="1"
            suffix="%"
          />
          <template v-else>
            <div class="text-sm text-muted-foreground">
              {{ $t('supplierMonitoring.postMatchRevisions.rate') }}
            </div>
            <div class="mt-1 text-2xl">-</div>
          </template>
        </Col>
      </Row>
      <p class="monitoring-note text-muted-foreground">{{ $t('supplierMonitoring.postMatchRevisions.description') }}</p>
      <p class="monitoring-note-secondary text-muted-foreground">{{ $t('supplierMonitoring.postMatchRevisions.limitations') }}</p>
    </Card>
    <Card :title="$t('supplierMonitoring.conflictQuality.title')">
      <Row :gutter="[16, 16]">
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.conflictQuality.runs')"
            :value="result.conflict_quality.runs || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.conflictQuality.pairs')"
            :value="result.conflict_quality.matched_pairs || 0"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            :title="$t('supplierMonitoring.conflictQuality.conflicts')"
            :value="result.conflict_quality.conflict_fields || 0"
            :suffix="`/ ${result.conflict_quality.comparable_fields || 0}`"
          />
        </Col>
        <Col :xs="24" :sm="12" :xl="6">
          <Statistic
            v-if="result.conflict_quality.rate != null"
            :title="$t('supplierMonitoring.conflictQuality.rate')"
            :value="result.conflict_quality.rate * 100"
            :precision="1"
            suffix="%"
          />
          <template v-else>
            <div class="text-sm text-muted-foreground">
              {{ $t('supplierMonitoring.conflictQuality.rate') }}
            </div>
            <div class="mt-1 text-2xl">-</div>
          </template>
        </Col>
      </Row>
      <p class="monitoring-note text-muted-foreground">
        {{
          $t('supplierMonitoring.conflictQuality.description', {
            count: result.conflict_quality.not_comparable_fields || 0,
          })
        }}
      </p>
    </Card>
    <Card :title="$t('supplierMonitoring.sourceTable')">
      <Card class="admin-table-card" :body-style="{ padding: '0' }">
        <Table
          class="business-data-table admin-data-table"
          :scroll="{ x: 1100 }"
          bordered
          :columns="columns"
          :data-source="result.sources"
          :loading="loading"
          :pagination="false"
          row-key="id"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'name'">
              {{ record.name }}
              <span class="text-muted-foreground">({{ record.code }})</span>
            </template>
            <template v-else-if="column.dataIndex === 'status'">
              <Tag>{{ $t(`supplierMonitoring.sourceStatuses.${record.status}`) }}</Tag>
            </template>
            <template v-else-if="column.dataIndex === 'health_status'">
              <Tag :color="healthColor(record.health_status)">
                {{ $t(`supplierMonitoring.health.${record.health_status}`) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'scorePolling'">
              <Tag
                :color="
                  record.score_polling.state === 'stale_claim'
                    ? 'red'
                    : record.score_polling.state === 'due'
                    ? 'orange'
                    : record.score_polling.enabled
                    ? 'blue'
                    : 'default'
                "
              >
                {{ $t(`supplierMonitoring.scorePolling.states.${record.score_polling.state}`) }}
              </Tag>
              <div v-if="record.score_polling.enabled" class="mt-1 text-xs text-muted-foreground">
                {{ record.score_polling.interval_seconds }}s · {{ record.score_polling.next_at || '-' }}
              </div>
            </template>
            <template v-else-if="column.key === 'runs'">
              {{ record.window_runs.completed }} / {{ record.window_runs.total }}
              <Tag v-if="record.window_runs.failed" color="red" class="ml-2">
                {{ $t('supplierMonitoring.failedCount', { count: record.window_runs.failed }) }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'apiCalls'">
              {{ record.api_calls.succeeded }} / {{ record.api_calls.total }}
              <Tag v-if="record.api_calls.failed" color="red" class="ml-2">
                {{ $t('supplierMonitoring.failedCount', { count: record.api_calls.failed }) }}
              </Tag>
              <Tag v-if="record.api_calls.rate_limited" color="orange" class="ml-2">
                429 × {{ record.api_calls.rate_limited }}
              </Tag>
            </template>
            <template v-else-if="column.key === 'apiLatency'">
              {{
                record.api_calls.average_duration_ms === null
                  ? '-'
                  : `${record.api_calls.average_duration_ms} / ${record.api_calls.p95_duration_ms} ms`
              }}
            </template>
            <template v-else-if="column.key === 'latest'">
              <template v-if="record.latest_run">
                <Tag>{{ record.latest_run.resource }}</Tag>
                {{ $t(`supplierMonitoring.runStatuses.${record.latest_run.status}`) }}
                <span v-if="record.latest_run.error_code" class="ml-2 text-red-500">
                  {{ record.latest_run.error_code }}
                </span>
              </template>
              <span v-else>-</span>
            </template>
            <template v-else-if="column.dataIndex === 'last_success_at'">
              {{ record.last_success_at || '-' }}
            </template>
          </template>
        </Table>
      </Card>
    </Card>
    </div>
  </Page>
</template>

<style scoped>
.monitoring-content,
.monitoring-filters {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.monitoring-summary {
  padding: 16px 24px;
}

.monitoring-filters > :deep(.admin-list-toolbar),
.monitoring-filters > :deep(.admin-list-search-panel) {
  margin-bottom: 0;
}

.monitoring-next-row {
  margin-top: 16px;
}

.monitoring-note {
  margin: 16px 0 0;
}

.monitoring-note-secondary {
  margin: 8px 0 0;
}

.monitoring-intro {
  margin: 0 0 16px;
}
</style>
