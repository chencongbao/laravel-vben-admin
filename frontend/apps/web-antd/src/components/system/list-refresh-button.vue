<script lang="ts" setup>
import { ref } from 'vue';

import { useRefresh } from '@vben/hooks';

import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';

defineOptions({ name: 'AdminListRefreshButton' });

const props = withDefaults(defineProps<{
  loading?: boolean;
}>(), {
  loading: false,
});

const refreshing = ref(false);
const { refresh: refreshPage } = useRefresh();

async function refresh() {
  if (refreshing.value || props.loading) return;
  refreshing.value = true;
  try {
    await refreshPage();
  } finally {
    refreshing.value = false;
  }
}
</script>

<template>
  <PermissionButton icon="lucide:refresh-cw" :loading="loading || refreshing" @click="refresh">
    {{ $t('common.actions.refresh') }}
  </PermissionButton>
</template>
