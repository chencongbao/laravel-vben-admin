<script lang="ts" setup>
import { computed } from 'vue';

import { useAccess } from '@vben/access';
import { IconifyIcon } from '@vben/icons';

import { Dropdown, Menu, MenuItem } from 'ant-design-vue';

import { $t } from '#/locales';

import PermissionButton from './permission-button.vue';

export interface TableBatchAction {
  danger?: boolean;
  disabled?: boolean;
  icon: string;
  key: string;
  label: string;
  permission?: string | string[];
}

const props = withDefaults(defineProps<{
  actions: TableBatchAction[];
  label?: string;
  selectedCount: number;
}>(), {
  label: undefined,
});

const emit = defineEmits<{
  action: [key: string];
  clear: [];
}>();

const { hasAccessByCodes } = useAccess();
const allowedActions = computed(() => props.actions.filter((action) => {
  if (!action.permission) return true;
  const permissions = Array.isArray(action.permission)
    ? action.permission
    : [action.permission];
  return hasAccessByCodes(permissions);
}));
const buttonText = computed(() => props.selectedCount > 0
  ? `${props.label || $t('common.actions.batch')} (${props.selectedCount})`
  : props.label || $t('common.actions.batch'));

function handleMenuClick(key: string | number) {
  if (key === '__clear__') emit('clear');
  else emit('action', String(key));
}
</script>

<template>
  <div v-if="allowedActions.length" class="admin-table-batch-actions">
    <span v-if="selectedCount > 0" class="admin-table-batch-actions__count">
      {{ $t('common.selection.selectedCount', { count: selectedCount }) }}
    </span>
    <Dropdown placement="bottomRight" trigger="click">
      <PermissionButton
        :disabled="selectedCount === 0"
        icon="lucide:list-checks"
      >
        {{ buttonText }}
      </PermissionButton>
      <template #overlay>
        <Menu @click="({ key }) => handleMenuClick(key)">
          <MenuItem
            v-for="action in allowedActions"
            :key="action.key"
            :danger="action.danger"
            :disabled="action.disabled"
          >
            <IconifyIcon :icon="action.icon" class="mr-2" />
            {{ action.label }}
          </MenuItem>
          <MenuItem key="__clear__">
            <IconifyIcon icon="lucide:x" class="mr-2" />
            {{ $t('common.actions.clearSelection') }}
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
  </div>
</template>

<style scoped>
.admin-table-batch-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.admin-table-batch-actions__count {
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  white-space: nowrap;
}
</style>
