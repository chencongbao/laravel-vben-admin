<script lang="ts" setup>
import { onMounted, ref } from 'vue';

import { Page } from '@vben/common-ui';
import { Card, Tag } from 'ant-design-vue';

import { $t } from '#/locales';
import { getDemoApi } from '#project-admin/api/demo';

const ready = ref(false);

onMounted(async () => {
  const response = await getDemoApi();
  ready.value = response.status === 'ok';
});
</script>

<template>
  <Page :description="$t('demo.description')" :title="$t('demo.title')">
    <Card :title="$t('demo.apiTitle')">
      <Tag :color="ready ? 'success' : 'processing'">
        {{ ready ? $t('demo.apiReady') : $t('demo.apiLoading') }}
      </Tag>
    </Card>
  </Page>
</template>
