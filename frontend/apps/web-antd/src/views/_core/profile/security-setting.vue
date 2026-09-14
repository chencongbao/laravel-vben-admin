<script setup lang="ts">
import type { AdminSession } from '#/api';

import { computed, onMounted, ref } from 'vue';

import {
  Button,
  Card,
  Input,
  message,
  Modal,
  Popconfirm,
  Space,
  Switch,
  Table,
  Tag,
} from 'ant-design-vue';

import {
  disableTwoFactorApi,
  enableTwoFactorApi,
  getSessionsApi,
  getTwoFactorStatusApi,
  revokeOtherSessionsApi,
  revokeSessionApi,
} from '#/api';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';

const loading = ref(false);
const sessions = ref<AdminSession[]>([]);
const twoFactor = ref({ confirmed: false, enabled: false });
const twoFactorCode = ref('');
const twoFactorLoading = ref(false);
const disableModalOpen = ref(false);
const columns = computed(() => [
  { dataIndex: 'client_type', title: $t('page.profile.security.clientType') },
  { dataIndex: 'ip_address', title: $t('page.profile.security.ipAddress') },
  { dataIndex: 'created_at', title: $t('page.profile.security.signedInAt') },
  { dataIndex: 'last_used_at', title: $t('page.profile.security.lastUsedAt') },
  { dataIndex: 'current', title: $t('page.profile.security.session') },
  { dataIndex: 'action', title: $t('page.profile.security.actions') },
]);

function clientTypeLabel(type: AdminSession['client_type']) {
  return $t(`page.profile.security.clientTypes.${type}`);
}

async function load() {
  loading.value = true;
  try {
    const [sessionResult, twoFactorResult] = await Promise.all([
      getSessionsApi(),
      getTwoFactorStatusApi(),
    ]);
    sessions.value = sessionResult.sessions;
    twoFactor.value = twoFactorResult.two_factor;
  } finally {
    loading.value = false;
  }
}

async function toggleTwoFactor(enabled: boolean) {
  if (!enabled && twoFactor.value.confirmed) {
    twoFactorCode.value = '';
    disableModalOpen.value = true;
    return;
  }

  twoFactorLoading.value = true;
  try {
    if (enabled) {
      await enableTwoFactorApi();
      message.success($t('page.profile.security.twoFactorEnabledPending'));
    } else {
      await disableTwoFactorApi();
      message.success($t('page.profile.security.twoFactorDisabled'));
    }
    twoFactor.value = (await getTwoFactorStatusApi()).two_factor;
  } finally {
    twoFactorLoading.value = false;
  }
}

async function confirmDisableTwoFactor() {
  if (!/^\d{6}$/.test(twoFactorCode.value)) {
    message.warning($t('page.profile.security.twoFactorCodeTip'));
    return;
  }

  twoFactorLoading.value = true;
  try {
    await disableTwoFactorApi(twoFactorCode.value);
    twoFactor.value = (await getTwoFactorStatusApi()).two_factor;
    disableModalOpen.value = false;
    message.success($t('page.profile.security.twoFactorDisabled'));
  } finally {
    twoFactorLoading.value = false;
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
      <template #title>{{ $t('page.profile.security.twoFactorTitle') }}</template>
      <template #extra>
        <Switch
          :checked="twoFactor.enabled"
          :loading="twoFactorLoading"
          @change="(checked) => toggleTwoFactor(Boolean(checked))"
        />
      </template>
      <div class="two-factor-setting">
        <Tag :color="twoFactor.confirmed ? 'green' : twoFactor.enabled ? 'orange' : 'default'">
          {{
            $t(
              twoFactor.confirmed
                ? 'page.profile.security.twoFactorActive'
                : twoFactor.enabled
                  ? 'page.profile.security.twoFactorPending'
                  : 'page.profile.security.twoFactorInactive',
            )
          }}
        </Tag>
        <p>{{ $t('page.profile.security.twoFactorDescription') }}</p>
      </div>
    </Card>

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
        bordered
        class="admin-data-table"
        :columns="columns"
        :data-source="sessions"
        :loading="loading"
        :pagination="{ pageSize: 8, showSizeChanger: false }"
        row-key="id"
        :scroll="{ x: 760 }"
      >
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'client_type'" color="blue">
            {{ clientTypeLabel(text) }}
          </Tag>
          <Tag
            v-else-if="column.dataIndex === 'current'"
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
            {{ text ? formatBeijingDateTime(text) : $t('page.profile.security.neverUsed') }}
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

    <Modal
      v-model:open="disableModalOpen"
      :confirm-loading="twoFactorLoading"
      :title="$t('page.profile.security.twoFactorDisableTitle')"
      :ok-text="$t('page.profile.security.twoFactorDisableConfirm')"
      @ok="confirmDisableTwoFactor"
    >
      <p>{{ $t('page.profile.security.twoFactorDisableDescription') }}</p>
      <Input
        v-model:value="twoFactorCode"
        :maxlength="6"
        :placeholder="$t('page.profile.security.twoFactorCodePlaceholder')"
        inputmode="numeric"
        @press-enter="confirmDisableTwoFactor"
      />
    </Modal>

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
.two-factor-setting {
  display: flex;
  align-items: center;
  gap: 12px;
}
.two-factor-setting p {
  margin: 0;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
}
</style>
