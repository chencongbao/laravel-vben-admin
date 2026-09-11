<script lang="ts" setup>
import ResourcePage from '#/components/system/resource-page.vue';

const fields = [
  { key: 'id', label: 'ID' },
  { key: 'action', label: '操作' },
  { key: 'actor_id', label: '操作人 ID' },
  { key: 'subject_type', label: '对象类型' },
  { key: 'subject_id', label: '对象 ID' },
  { key: 'ip_address', label: 'IP 地址' },
  { key: 'created_at', label: '时间' },
];
</script>

<template>
  <ResourcePage :fields="fields" path="/system/audit-logs" read-only title="审计日志" />
</template>
