<script setup lang="ts">
import type { VbenFormSchema } from '#/adapter/form';
import type { Recordable } from '@vben/types';
import { computed, onMounted, ref } from 'vue';
import { ProfileBaseSetting } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';
import { message } from 'ant-design-vue';
import { getUserInfoApi, updateProfileApi } from '#/api';

const profileRef = ref(); const userStore = useUserStore();
const formSchema = computed((): VbenFormSchema[] => [
  { component: 'Input', fieldName: 'name', label: '姓名' },
  { component: 'Input', componentProps: { placeholder: 'https://example.com/avatar.png' }, fieldName: 'avatar', label: '头像地址' },
]);
onMounted(async () => {
  const user = await getUserInfoApi();
  await profileRef.value.getFormApi().setValues({ avatar: user.avatar, name: user.realName });
});
async function submit(values: Recordable<any>) {
  await updateProfileApi({ avatar: values.avatar || null, name: values.name });
  userStore.setUserInfo(await getUserInfoApi());
  message.success('个人资料已更新');
}
</script>
<template><ProfileBaseSetting ref="profileRef" :form-schema="formSchema" @submit="submit" /></template>
