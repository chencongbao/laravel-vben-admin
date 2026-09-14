<script setup lang="ts">
import type { VbenFormSchema } from '#/adapter/form';
import type { Recordable } from '@vben/types';

import { computed, onMounted, ref } from 'vue';

import { ProfileBaseSetting } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { message } from 'ant-design-vue';

import { getUserInfoApi, updateProfileApi } from '#/api';
import { $t } from '#/locales';

const profileRef = ref();
const userStore = useUserStore();
const formSchema = computed((): VbenFormSchema[] => [
  {
    component: 'Input',
    componentProps: {
      placeholder: $t('profile.basic.namePlaceholder'),
    },
    fieldName: 'name',
    label: $t('profile.basic.name'),
  },
]);
onMounted(async () => {
  const user = await getUserInfoApi();
  await profileRef.value.getFormApi().setValues({ name: user.realName });
});
async function submit(values: Recordable<any>) {
  await updateProfileApi({ name: values.name });
  const userInfo = await getUserInfoApi();
  userStore.setUserInfo(userInfo);
  message.success($t('profile.basic.updated'));
}
</script>
<template>
  <section class="profile-section">
    <div class="profile-section-heading">
      <h2>{{ $t('profile.basic.title') }}</h2>
      <p>{{ $t('profile.basic.nameDescription') }}</p>
    </div>
    <ProfileBaseSetting
      ref="profileRef"
      :form-schema="formSchema"
      @submit="submit"
    />
  </section>
</template>

<style scoped>
.profile-section-heading {
  margin-bottom: 28px;
}

.profile-section-heading h2 {
  margin: 0;
  color: hsl(var(--foreground));
  font-size: 20px;
  font-weight: 600;
  line-height: 28px;
}

.profile-section-heading p {
  margin: 6px 0 0;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  line-height: 20px;
}
</style>
