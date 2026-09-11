<script lang="ts" setup>
import ResourcePage from '#/components/system/resource-page.vue';

const fields = [
  { key: 'id', label: 'ID' },
  { key: 'code', label: '权限编码', required: true },
  { key: 'name', label: '权限名称', required: true },
  { key: 'is_active', label: '状态', type: 'boolean' as const },
  { key: 'is_sensitive', label: '敏感权限', type: 'boolean' as const },
  { key: 'is_system', label: '系统权限' },
];
</script>

<template>
  <ResourcePage :fields="fields" path="/system/permissions" title="权限管理" />
</template>
