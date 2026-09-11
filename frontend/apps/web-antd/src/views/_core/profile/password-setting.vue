<script setup lang="ts">
import type { Recordable } from '@vben/types';
import type { VbenFormSchema } from '#/adapter/form';
import { computed } from 'vue';
import { ProfilePasswordSetting, z } from '@vben/common-ui';
import { message } from 'ant-design-vue';
import { updatePasswordApi } from '#/api';

const formSchema = computed((): VbenFormSchema[] => [
  { component: 'VbenInputPassword', componentProps: { placeholder: '请输入当前密码' }, fieldName: 'currentPassword', label: '当前密码' },
  { component: 'VbenInputPassword', componentProps: { passwordStrength: true, placeholder: '至少 12 位，包含大小写字母和数字' }, fieldName: 'newPassword', label: '新密码' },
  {
    component: 'VbenInputPassword', componentProps: { passwordStrength: true, placeholder: '请再次输入新密码' }, fieldName: 'confirmPassword', label: '确认密码',
    dependencies: {
      rules(values) {
        return z.string({ required_error: '请再次输入新密码' }).min(1, { message: '请再次输入新密码' }).refine((value) => value === values.newPassword, { message: '两次输入的密码不一致' });
      },
      triggerFields: ['newPassword'],
    },
  },
]);
async function submit(values: Recordable<any>) {
  await updatePasswordApi({ current_password: values.currentPassword, password: values.newPassword, password_confirmation: values.confirmPassword });
  message.success('密码已修改，其他设备的登录令牌已撤销');
}
</script>
<template><ProfilePasswordSetting class="w-1/2" :form-schema="formSchema" @submit="submit" /></template>
