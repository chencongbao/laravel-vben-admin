<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';

import { AuthPageLayout } from '@vben/layouts';
import { preferences } from '@vben/preferences';

import { $t } from '#/locales';

const appName = computed(() => preferences.app.name);
const logo = computed(() => preferences.logo.source);
const logoDark = computed(() => preferences.logo.sourceDark);
const loginDescription = ref('');
const pageDescription = computed(
  () => loginDescription.value || $t('authentication.pageDesc'),
);

async function loadLoginDescription() {
  try {
    const response = await fetch('/api/admin/application', {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    const data = (await response.json()) as {
      login_description?: string;
    };
    loginDescription.value = data.login_description ?? '';
  } catch {
    loginDescription.value = '';
  }
}

onMounted(loadLoginDescription);
</script>

<template>
  <AuthPageLayout
    :app-name="appName"
    :logo="logo"
    :logo-dark="logoDark"
    :page-description="pageDescription"
    :page-title="appName"
    :toolbar-list="['language', 'theme']"
  >
    <!-- 自定义工具栏 -->
    <!-- <template #toolbar></template> -->
  </AuthPageLayout>
</template>
