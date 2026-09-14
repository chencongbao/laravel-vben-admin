<script lang="ts" setup>
import { computed, ref } from 'vue';

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
}>();

const { hasAccessByCodes } = useAccess();
const dropdownOpen = ref(false);
const allowedActions = computed(() => props.actions.filter((action) => {
  if (!action.permission) return true;
  const permissions = Array.isArray(action.permission)
    ? action.permission
    : [action.permission];
  return hasAccessByCodes(permissions);
}));
const buttonText = computed(() => props.selectedCount > 0
  ? $t('common.actions.batchWithCount', {
      count: props.selectedCount,
      label: props.label || $t('common.actions.batch'),
    })
  : props.label || $t('common.actions.batch'));

function handleMenuClick(key: string | number) {
  emit('action', String(key));
}
</script>

<template>
  <div v-if="allowedActions.length" class="admin-table-batch-actions">
    <Dropdown v-model:open="dropdownOpen" overlay-class-name="admin-table-batch-dropdown" placement="bottomLeft" trigger="click">
      <PermissionButton
        :disabled="selectedCount === 0"
        icon="lucide:list-checks"
      >
        {{ buttonText }}
        <IconifyIcon :class="['admin-table-batch-actions__chevron', { 'is-open': dropdownOpen }]" icon="lucide:chevron-down" />
      </PermissionButton>
      <template #overlay>
        <Menu @click="({ key }) => handleMenuClick(key)">
          <MenuItem
            v-for="action in allowedActions"
            :key="action.key"
            class="admin-table-batch-dropdown__item"
            :danger="action.danger"
            :disabled="action.disabled"
          >
            <IconifyIcon :icon="action.icon" class="admin-table-batch-dropdown__icon" />
            <span>{{ action.label }}</span>
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
  </div>
</template>
