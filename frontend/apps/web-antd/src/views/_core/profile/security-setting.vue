<script setup lang="ts">
import { computed } from 'vue';
import { ProfileSecuritySetting } from '@vben/common-ui';
const formSchema = computed(() => [
  { description: '修改密码需要验证当前密码；密码至少 12 位，并包含大小写字母和数字。', fieldName: 'accountPassword', label: '账户密码', value: true },
  { description: '修改密码成功后，除当前会话外的其他 Sanctum 登录令牌会被撤销。', fieldName: 'sessionSecurity', label: '会话安全', value: true },
  { description: 'MFA 尚未在基础包中实现，后续需要单独设计恢复码和强制策略。', fieldName: 'securityMfa', label: 'MFA 设备', value: false },
]);
</script>
<template><ProfileSecuritySetting :form-schema="formSchema" /></template>
