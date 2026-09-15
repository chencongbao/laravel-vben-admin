<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';

import { getCollection } from '#/api/system';
import LogPage from '#/components/system/log-page.vue';
import { $t } from '#/locales';

interface Operator { id: number; name?: null | string; username: string }

const operators = ref<Operator[]>([]);
const operatorOptions = computed(() => operators.value.map((operator) => ({
  label: operator.name ? `${operator.name}（${operator.username}）` : operator.username,
  value: operator.username,
})));

const columns = computed(() => [
  { dataIndex: 'id', title: $t('common.fields.id') },
  { dataIndex: 'username', title: $t('system.loginLog.fields.username') },
  { dataIndex: 'ip_address', title: $t('system.loginLog.fields.ipAddress') },
  { dataIndex: 'succeeded', title: $t('system.loginLog.fields.result') },
  { dataIndex: 'client_type', title: $t('system.loginLog.fields.accessDevice') },
  { dataIndex: 'user_agent', ellipsis: true, title: $t('system.loginLog.fields.userAgent'), width: 360 },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
]);
const filters = computed(() => [
  { key: 'username', label: $t('system.loginLog.filters.operator'), options: operatorOptions.value },
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
  const result = await getCollection<{ operators: Operator[] }>('/system/login-logs/operators');
  operators.value = result.operators;
});
</script>
<template><LogPage :columns="columns" :description="$t('system.loginLogsDescription')" :filters="filters" path="/system/login-logs" permission="system.login-log.view" :title="$t('system.loginLogs')" /></template>
