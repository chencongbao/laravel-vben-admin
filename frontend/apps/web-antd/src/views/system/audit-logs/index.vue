<script lang="ts" setup>
import LogPage from '#/components/system/log-page.vue';
const columns = [
  { dataIndex: 'id', title: 'ID' }, { dataIndex: 'action', title: '操作' },
  { dataIndex: 'actor_id', title: '操作人 ID' }, { dataIndex: 'subject_type', title: '对象类型' },
  { dataIndex: 'subject_id', title: '对象 ID' }, { dataIndex: 'ip_address', title: 'IP 地址' },
  { dataIndex: 'method', title: '请求方法' }, { dataIndex: 'path', title: '请求路径' },
  { dataIndex: 'created_at', title: '时间' },
];
const filters = [{ key: 'action', label: '操作标识' }, { key: 'actor_id', label: '操作人 ID' }];
</script>
<template><LogPage :columns="columns" :filters="filters" path="/system/audit-logs" permission="system.audit.view" title="审计日志" /></template>
