<script lang="ts" setup>
import LogPage from '#/components/system/log-page.vue';
const columns = [
  { dataIndex: 'id', title: 'ID' }, { dataIndex: 'username', title: '用户名' },
  { dataIndex: 'ip_address', title: 'IP 地址' }, { dataIndex: 'succeeded', title: '结果' },
  { dataIndex: 'failure_code', title: '失败代码' }, { dataIndex: 'created_at', title: '时间' },
];
const filters = [
  { key: 'username', label: '用户名' },
  { key: 'succeeded', label: '登录结果', options: [{ label: '成功', value: '1' }, { label: '失败', value: '0' }] },
];
</script>
<template><LogPage :columns="columns" :filters="filters" path="/system/login-logs" title="登录日志" /></template>
