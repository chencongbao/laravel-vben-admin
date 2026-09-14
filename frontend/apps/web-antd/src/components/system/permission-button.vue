<script lang="ts" setup>
import { computed } from 'vue';

import { useAccess } from '@vben/access';
import { IconifyIcon } from '@vben/icons';

import { Button, Tooltip } from 'ant-design-vue';

defineOptions({ inheritAttrs: false, name: 'PermissionButton' });

const props = withDefaults(defineProps<{
  danger?: boolean;
  icon: string;
  iconOnly?: boolean;
  loading?: boolean;
  permission?: string | string[];
  tooltip?: string;
  type?: 'default' | 'dashed' | 'link' | 'primary' | 'text';
}>(), {
  danger: false,
  iconOnly: false,
  loading: false,
  permission: undefined,
  type: 'default',
});

defineEmits<{ click: [event: MouseEvent] }>();

const { hasAccessByCodes } = useAccess();
const visible = computed(() => {
  if (!props.permission) return true;
  const permissions = Array.isArray(props.permission)
    ? props.permission
    : [props.permission];
  return hasAccessByCodes(permissions);
});
</script>

<template>
  <Tooltip v-if="visible" :title="tooltip">
    <Button
      v-bind="$attrs"
      :aria-label="tooltip"
      :class="['permission-button', { 'permission-button--icon-only': iconOnly }]"
      :danger="danger"
      :loading="loading"
      :type="type"
      @click="$emit('click', $event)"
    >
      <template #icon><IconifyIcon :icon="icon" /></template>
      <slot v-if="!iconOnly" />
    </Button>
  </Tooltip>
</template>

<style scoped>
.permission-button {
  border-radius: 0;
}

.permission-button--icon-only {
  width: 32px;
  height: 32px;
  padding: 0;
}
</style>
