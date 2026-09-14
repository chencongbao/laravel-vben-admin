<script lang="ts" setup>
import LogPage from '#/components/system/log-page.vue';
import { $t } from '#/locales';
const columns = [
  { dataIndex: 'id', title: $t('common.fields.id') }, { dataIndex: 'username', title: '用户名' },
  { dataIndex: 'ip_address', title: 'IP 地址' }, { dataIndex: 'succeeded', title: '结果' },
  { dataIndex: 'client_type', title: '客户端类型' },
  { dataIndex: 'failure_code', title: '失败代码' }, { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
];
const filters = [
  { key: 'username', label: '用户名' },
  { key: 'succeeded', label: '登录结果', options: [{ label: '成功', value: '1' }, { label: '失败', value: '0' }] },
];
</script>
<template><LogPage :columns="columns" :description="$t('system.loginLogsDescription')" :filters="filters" path="/system/login-logs" permission="system.login-log.view" :title="$t('system.loginLogs')" /></template>
