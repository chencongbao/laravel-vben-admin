<script lang="ts" setup>
import { computed } from 'vue';

import LogPage from '#/components/system/log-page.vue';
import { $t } from '#/locales';

const columns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id') },
  { dataIndex: 'username', title: $t('system.loginLog.fields.username') },
  { dataIndex: 'ip_address', title: $t('system.loginLog.fields.ipAddress') },
  { dataIndex: 'succeeded', title: $t('system.loginLog.fields.result') },
  { dataIndex: 'client_type', title: $t('system.loginLog.fields.accessDevice') },
  { dataIndex: 'user_agent', ellipsis: true, title: $t('system.loginLog.fields.userAgent'), width: 360 },
  { dataIndex: 'failure_code', title: $t('system.loginLog.fields.failureCode') },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
]);
const filters = computed(() => [
  { key: 'username', label: $t('system.loginLog.fields.username') },
  {
    key: 'succeeded',
    label: $t('system.loginLog.filters.result'),
    options: [
      { label: $t('system.loginLog.states.succeeded'), value: '1' },
      { label: $t('system.loginLog.states.failed'), value: '0' },
    ],
  },
]);
</script>
<template><LogPage :columns="columns" :description="$t('system.loginLogsDescription')" :filters="filters" path="/system/login-logs" permission="system.login-log.view" :title="$t('system.loginLogs')" /></template>
