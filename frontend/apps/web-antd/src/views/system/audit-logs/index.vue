<script lang="ts" setup>
import LogPage from '#/components/system/log-page.vue';
import { $t } from '#/locales';
const columns = [
  { dataIndex: 'id', title: $t('common.fields.id') }, { dataIndex: 'action', title: $t('system.auditFields.action') },
  { dataIndex: 'actor_id', title: $t('system.auditFields.actorId') }, { dataIndex: 'subject_type', title: $t('system.auditFields.subjectType') },
  { dataIndex: 'subject_id', title: $t('system.auditFields.subjectId') }, { dataIndex: 'ip_address', title: $t('system.auditFields.ipAddress') },
  { dataIndex: 'method', title: $t('system.auditFields.method') }, { dataIndex: 'path', title: $t('system.auditFields.path') },
  { dataIndex: 'created_at', title: $t('common.fields.createdAt') },
];
const filters = [
  { key: 'action', label: $t('system.auditFields.actionCode') },
  { key: 'actor_id', label: $t('system.auditFields.actorId') },
];
</script>
<template><LogPage :columns="columns" :description="$t('system.auditLogsDescription')" :filters="filters" path="/system/audit-logs" permission="system.audit.view" :title="$t('system.auditLogs')" /></template>
