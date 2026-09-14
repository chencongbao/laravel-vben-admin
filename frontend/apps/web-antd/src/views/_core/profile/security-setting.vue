<script setup lang="ts">
import type { AdminSession } from '#/api';

import { computed, onMounted, ref } from 'vue';

import { preferences } from '@vben/preferences';

import {
  Button,
  Card,
  message,
  Popconfirm,
  Space,
  Table,
  Tag,
} from 'ant-design-vue';

import {
  getSessionsApi,
  revokeOtherSessionsApi,
  revokeSessionApi,
} from '#/api';
import { $t } from '#/locales';

const loading = ref(false);
const sessions = ref<AdminSession[]>([]);
const columns = computed(() => [
  { dataIndex: 'ip_address', title: $t('page.profile.security.ipAddress') },
  { dataIndex: 'created_at', title: $t('page.profile.security.signedInAt') },
  { dataIndex: 'last_used_at', title: $t('page.profile.security.lastUsedAt') },
  { dataIndex: 'current', title: $t('page.profile.security.session') },
  { dataIndex: 'action', title: $t('page.profile.security.actions') },
]);

function formatBeijingTime(value?: null | string) {
  if (!value) return $t('page.profile.security.neverUsed');

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('zh-CN', {
    day: '2-digit',
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    month: '2-digit',
    second: '2-digit',
    timeZone: preferences.app.timezone,
    year: 'numeric',
  })
    .format(date)
    .replaceAll('/', '-');
}

async function load() {
  loading.value = true;
  try {
    sessions.value = (await getSessionsApi()).sessions;
  } finally {
    loading.value = false;
  }
}

async function revoke(session: Record<string, any>) {
  await revokeSessionApi(Number(session.id));
  message.success($t('page.profile.security.revoked'));
  await load();
}

async function revokeOthers() {
  const result = await revokeOtherSessionsApi();
  message.success(
    $t('page.profile.security.revokedOthers', {
      count: result.revoked_count,
    }),
  );
  await load();
}

onMounted(load);
</script>

<template>
  <section class="profile-section">
    <div class="profile-section-heading">
      <h2>{{ $t('page.profile.security.title') }}</h2>
      <p>{{ $t('page.profile.security.description') }}</p>
    </div>

    <Card :bordered="false" class="security-card">
      <template #title>{{
        $t('page.profile.security.sessionsTitle')
      }}</template>
      <template #extra>
        <Popconfirm
          :title="$t('page.profile.security.revokeOthersConfirm')"
          @confirm="revokeOthers"
        >
          <Button danger>{{ $t('page.profile.security.revokeOthers') }}</Button>
        </Popconfirm>
      </template>

      <Table
        :columns="columns"
        :data-source="sessions"
        :loading="loading"
        :pagination="{ pageSize: 8, showSizeChanger: false }"
        row-key="id"
        :scroll="{ x: 760 }"
      >
        <template #bodyCell="{ column, record, text }">
          <Tag
            v-if="column.dataIndex === 'current'"
            :color="text ? 'green' : 'default'"
          >
            {{
              text
                ? $t('page.profile.security.currentSession')
                : $t('page.profile.security.otherSession')
            }}
          </Tag>
          <span
            v-else-if="
              column.dataIndex === 'created_at' ||
              column.dataIndex === 'last_used_at'
            "
            class="session-time"
          >
            {{ formatBeijingTime(text) }}
          </span>
          <span
            v-else-if="column.dataIndex === 'ip_address'"
            class="session-ip"
          >
            {{ text || $t('page.profile.security.unknownIp') }}
          </span>
          <Space v-else-if="column.dataIndex === 'action'">
            <Tag v-if="record.current" color="green">
              {{ $t('page.profile.security.protected') }}
            </Tag>
            <Popconfirm
              v-else
              :title="$t('page.profile.security.revokeConfirm')"
              @confirm="revoke(record)"
            >
              <Button danger size="small" type="link">
                {{ $t('page.profile.security.revoke') }}
              </Button>
            </Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>

    <Card :bordered="false" class="security-card security-note">
      <template #title>{{ $t('page.profile.security.notesTitle') }}</template>
      <p>{{ $t('page.profile.security.passwordNote') }}</p>
      <p>{{ $t('page.profile.security.mfaNote') }}</p>
    </Card>
  </section>
</template>

<style scoped>
.profile-section-heading {
  margin-bottom: 24px;
}
.profile-section-heading h2 {
  margin: 0;
  color: hsl(var(--foreground));
  font-size: 20px;
  font-weight: 600;
  line-height: 28px;
}
.profile-section-heading p,
.security-note p {
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  line-height: 20px;
}
.profile-section-heading p {
  margin: 6px 0 0;
}
.security-card {
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: 10px;
  box-shadow: none;
}
.security-card + .security-card {
  margin-top: 20px;
}
.security-note p {
  margin: 0;
}
.security-note p + p {
  margin-top: 8px;
}
.session-ip,
.session-time {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
