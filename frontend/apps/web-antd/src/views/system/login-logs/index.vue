<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';

import { getCollection } from '#/api/system';
import LogPage from '#/components/system/log-page.vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';

import { Descriptions, DescriptionsItem, Modal, Tag } from 'ant-design-vue';

interface Operator {
  id: number;
  name?: null | string;
  username: string;
}
interface LoginLog {
  client_type?: null | string;
  created_at?: null | string;
  failure_code?: null | string;
  id: number;
  ip_address?: null | string;
  succeeded: boolean;
  user_agent?: null | string;
  username: string;
}

const operators = ref<Operator[]>([]);
const detail = ref<LoginLog>();
const detailOpen = ref(false);
const failureReasonKeys: Record<string, string> = {
  ACCOUNT_DISABLED: 'system.loginLog.failureReasons.accountDisabled',
  CAPTCHA_INVALID: 'system.loginLog.failureReasons.captchaInvalid',
  INVALID_CREDENTIALS: 'system.loginLog.failureReasons.invalidCredentials',
  LOGIN_IP_BLOCKED: 'system.loginLog.failureReasons.ipBlocked',
  LOGIN_IP_NOT_ALLOWED: 'system.loginLog.failureReasons.ipNotAllowed',
  TWO_FACTOR_CHALLENGE_INVALID:
    'system.loginLog.failureReasons.twoFactorChallengeInvalid',
  TWO_FACTOR_CODE_INVALID: 'system.loginLog.failureReasons.twoFactorInvalid',
  TWO_FACTOR_INVALID: 'system.loginLog.failureReasons.twoFactorInvalid',
};
const operatorOptions = computed(() =>
  operators.value.map((operator) => ({
    label: operator.name
      ? `${operator.name}（${operator.username}）`
      : operator.username,
    value: operator.username,
  })),
);

const columns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id') },
  { dataIndex: 'username', title: $t('system.loginLog.fields.username') },
  { dataIndex: 'ip_address', title: $t('system.loginLog.fields.ipAddress') },
  { dataIndex: 'succeeded', title: $t('system.loginLog.fields.result') },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
  {
    dataIndex: 'action_button',
    fixed: 'right',
    title: $t('common.fields.actions'),
    width: 72,
  },
]);
const filters = computed(() => [
  {
    key: 'username',
    label: $t('system.loginLog.filters.operator'),
    options: operatorOptions.value,
  },
  {
    key: 'succeeded',
    label: $t('system.loginLog.filters.result'),
    options: [
      { label: $t('system.loginLog.states.succeeded'), value: '1' },
      { label: $t('system.loginLog.states.failed'), value: '0' },
    ],
  },
]);
onMounted(async () => {
  const result = await getCollection<{ operators: Operator[] }>(
    '/system/login-logs/operators',
  );
  operators.value = result.operators;
});

function showDetail(record: Record<string, any>) {
  detail.value = record as LoginLog;
  detailOpen.value = true;
}

function failureReasonLabel(record: LoginLog): string {
  if (record.succeeded || !record.failure_code) {
    return '-';
  }

  return $t(
    failureReasonKeys[record.failure_code] ??
      'system.loginLog.failureReasons.unknown',
  );
}
</script>

<template>
  <LogPage
    :columns="columns"
    :description="$t('system.loginLogsDescription')"
    :filters="filters"
    path="/system/login-logs"
    permission="system.login-log.view"
    :title="$t('system.loginLogs')"
  >
    <template #row-actions="{ record }">
      <PermissionButton
        icon="lucide:eye"
        icon-only
        permission="system.login-log.view"
        :tooltip="$t('system.loginLog.actions.detail')"
        type="text"
        @click="showDetail(record)"
      />
    </template>
  </LogPage>

  <Modal
    v-model:open="detailOpen"
    :footer="null"
    :title="$t('system.loginLog.detail.title', { id: detail?.id ?? '' })"
    :width="760"
  >
    <Descriptions v-if="detail" bordered :column="2" size="small">
      <DescriptionsItem :label="$t('common.fields.id')">{{
        detail.id
      }}</DescriptionsItem>
      <DescriptionsItem :label="$t('system.loginLog.fields.username')">{{
        detail.username
      }}</DescriptionsItem>
      <DescriptionsItem :label="$t('system.loginLog.fields.ipAddress')">{{
        detail.ip_address || '-'
      }}</DescriptionsItem>
      <DescriptionsItem :label="$t('system.loginLog.fields.result')">
        <Tag :color="detail.succeeded ? 'green' : 'red'">
          {{
            detail.succeeded
              ? $t('system.loginLog.states.succeeded')
              : $t('system.loginLog.states.failed')
          }}
        </Tag>
      </DescriptionsItem>
      <DescriptionsItem
        :label="$t('system.loginLog.fields.failureReason')"
        :span="2"
      >
        {{ failureReasonLabel(detail) }}
      </DescriptionsItem>
      <DescriptionsItem :label="$t('common.fields.createdAt')">{{
        formatBeijingDateTime(detail.created_at)
      }}</DescriptionsItem>
      <DescriptionsItem :label="$t('system.loginLog.fields.accessDevice')">{{
        detail.client_type || '-'
      }}</DescriptionsItem>
      <DescriptionsItem
        :label="$t('system.loginLog.fields.userAgent')"
        :span="2"
      >
        <span class="break-all">{{ detail.user_agent || '-' }}</span>
      </DescriptionsItem>
    </Descriptions>
  </Modal>
</template>
