<script lang="ts" setup>
import ResourcePage from '#/components/system/resource-page.vue';

const fields = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: '用户名' },
  { key: 'ip_address', label: 'IP 地址' },
  { key: 'succeeded', label: '结果' },
  { key: 'failure_code', label: '失败代码' },
  { key: 'created_at', label: '时间' },
];
</script>

<template>
  <ResourcePage :fields="fields" path="/system/login-logs" read-only title="登录日志" />
</template>
