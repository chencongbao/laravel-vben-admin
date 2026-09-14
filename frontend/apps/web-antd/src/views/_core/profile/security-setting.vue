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
  { dataIndex: 'client_type', title: $t('profile.security.clientType') },
  { dataIndex: 'ip_address', title: $t('profile.security.ipAddress') },
  { dataIndex: 'created_at', title: $t('profile.security.signedInAt') },
  { dataIndex: 'last_used_at', title: $t('profile.security.lastUsedAt') },
  { dataIndex: 'current', title: $t('profile.security.session') },
  { dataIndex: 'action', title: $t('profile.security.actions') },
]);

function clientTypeLabel(type: AdminSession['client_type']) {
  return $t(`profile.security.clientTypes.${type}`);
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
      message.success($t('profile.security.twoFactorEnabledPending'));
    } else {
      await disableTwoFactorApi();
      message.success($t('profile.security.twoFactorDisabled'));
    }
    twoFactor.value = (await getTwoFactorStatusApi()).two_factor;
  } finally {
    twoFactorLoading.value = false;
  }
}

async function confirmDisableTwoFactor() {
  if (!/^\d{6}$/.test(twoFactorCode.value)) {
    message.warning($t('profile.security.twoFactorCodeTip'));
    return;
  }

  twoFactorLoading.value = true;
  try {
    await disableTwoFactorApi(twoFactorCode.value);
    twoFactor.value = (await getTwoFactorStatusApi()).two_factor;
    disableModalOpen.value = false;
    message.success($t('profile.security.twoFactorDisabled'));
  } finally {
    twoFactorLoading.value = false;
  }
}

async function revoke(session: Record<string, any>) {
  await revokeSessionApi(Number(session.id));
  message.success($t('profile.security.revoked'));
  await load();
}

async function revokeOthers() {
  const result = await revokeOtherSessionsApi();
  message.success(
    $t('profile.security.revokedOthers', {
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
      <h2>{{ $t('profile.security.title') }}</h2>
      <p>{{ $t('profile.security.description') }}</p>
    </div>

    <Card :bordered="false" class="security-card">
      <template #title>{{ $t('profile.security.twoFactorTitle') }}</template>
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
                ? 'profile.security.twoFactorActive'
                : twoFactor.enabled
                  ? 'profile.security.twoFactorPending'
                  : 'profile.security.twoFactorInactive',
            )
          }}
        </Tag>
        <p>{{ $t('profile.security.twoFactorDescription') }}</p>
      </div>
    </Card>

    <Card :bordered="false" class="security-card">
      <template #title>{{
        $t('profile.security.sessionsTitle')
      }}</template>
      <template #extra>
        <Popconfirm
          :title="$t('profile.security.revokeOthersConfirm')"
          @confirm="revokeOthers"
        >
          <Button danger>{{ $t('profile.security.revokeOthers') }}</Button>
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
                ? $t('profile.security.currentSession')
                : $t('profile.security.otherSession')
            }}
          </Tag>
          <span
            v-else-if="
              column.dataIndex === 'created_at' ||
              column.dataIndex === 'last_used_at'
            "
            class="session-time"
          >
            {{ text ? formatBeijingDateTime(text) : $t('profile.security.neverUsed') }}
          </span>
          <span
            v-else-if="column.dataIndex === 'ip_address'"
            class="session-ip"
          >
            {{ text || $t('profile.security.unknownIp') }}
          </span>
          <Space v-else-if="column.dataIndex === 'action'">
            <Tag v-if="record.current" color="green">
              {{ $t('profile.security.protected') }}
            </Tag>
            <Popconfirm
              v-else
              :title="$t('profile.security.revokeConfirm')"
              @confirm="revoke(record)"
            >
              <Button danger size="small" type="link">
                {{ $t('profile.security.revoke') }}
              </Button>
            </Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>

    <Modal
      v-model:open="disableModalOpen"
      :confirm-loading="twoFactorLoading"
      :title="$t('profile.security.twoFactorDisableTitle')"
      :ok-text="$t('profile.security.twoFactorDisableConfirm')"
      @ok="confirmDisableTwoFactor"
    >
      <p>{{ $t('profile.security.twoFactorDisableDescription') }}</p>
      <Input
        v-model:value="twoFactorCode"
        :maxlength="6"
        :placeholder="$t('profile.security.twoFactorCodePlaceholder')"
        inputmode="numeric"
        @press-enter="confirmDisableTwoFactor"
      />
    </Modal>

    <Card :bordered="false" class="security-card security-note">
      <template #title>{{ $t('profile.security.notesTitle') }}</template>
      <p>{{ $t('profile.security.passwordNote') }}</p>
      <p>{{ $t('profile.security.mfaNote') }}</p>
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
  border-radius: var(--radius);
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
