<script setup lang="ts">
import type { Recordable } from '@vben/types';
import type { VbenFormSchema } from '#/adapter/form';

import { computed } from 'vue';

import { ProfilePasswordSetting, z } from '@vben/common-ui';

import { message } from 'ant-design-vue';

import { updatePasswordApi } from '#/api';
import { $t } from '#/locales';

const formSchema = computed((): VbenFormSchema[] => [
  {
    component: 'VbenInputPassword',
    componentProps: {
      placeholder: $t('profile.password.currentPlaceholder'),
    },
    fieldName: 'currentPassword',
    label: $t('profile.password.current'),
    rules: z
      .string()
      .min(1, { message: $t('profile.password.currentRequired') }),
  },
  {
    component: 'VbenInputPassword',
    componentProps: {
      passwordStrength: true,
      placeholder: $t('profile.password.newPlaceholder'),
    },
    fieldName: 'newPassword',
    label: $t('profile.password.new'),
    rules: z
      .string()
      .min(1, { message: $t('profile.password.newRequired') })
      .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12,}$/, {
        message: $t('profile.password.requirements'),
      }),
  },
  {
    component: 'VbenInputPassword',
    componentProps: {
      passwordStrength: true,
      placeholder: $t('profile.password.confirmPlaceholder'),
    },
    fieldName: 'confirmPassword',
    label: $t('profile.password.confirm'),
    dependencies: {
      rules(values) {
        return z
          .string({
            required_error: $t('profile.password.confirmRequired'),
          })
          .min(1, {
            message: $t('profile.password.confirmRequired'),
          })
          .refine((value) => value === values.newPassword, {
            message: $t('profile.password.mismatch'),
          });
      },
      triggerFields: ['newPassword'],
    },
  },
]);
async function submit(values: Recordable<any>) {
  await updatePasswordApi({
    current_password: values.currentPassword,
    password: values.newPassword,
    password_confirmation: values.confirmPassword,
  });
  message.success($t('profile.password.updated'));
}
</script>
<template>
  <section class="profile-section">
    <div class="profile-section-heading">
      <h2>{{ $t('profile.password.title') }}</h2>
      <p>{{ $t('profile.password.description') }}</p>
    </div>
    <ProfilePasswordSetting :form-schema="formSchema" @submit="submit" />
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
