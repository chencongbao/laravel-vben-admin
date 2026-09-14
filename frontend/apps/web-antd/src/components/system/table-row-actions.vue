<script lang="ts" setup>
import { computed } from 'vue';

import { useAccess } from '@vben/access';
import { IconifyIcon } from '@vben/icons';

import { Button, Dropdown, Menu, MenuItem, Tooltip } from 'ant-design-vue';

import PermissionButton from './permission-button.vue';

export interface TableRowAction {
  danger?: boolean;
  disabled?: boolean;
  icon: string;
  key: string;
  label: string;
  permission?: string | string[];
}

const props = withDefaults(defineProps<{
  actions: TableRowAction[];
  maxDirect?: number;
}>(), { maxDirect: 3 });
const emit = defineEmits<{ action: [key: string] }>();
const { hasAccessByCodes } = useAccess();
const allowedActions = computed(() => props.actions.filter((action) => {
  if (!action.permission) return true;
  return hasAccessByCodes(Array.isArray(action.permission) ? action.permission : [action.permission]);
}));
const directActions = computed(() => allowedActions.value.slice(0, props.maxDirect));
const overflowActions = computed(() => allowedActions.value.slice(props.maxDirect));
</script>

<template>
  <div class="admin-table-row-actions">
    <div class="admin-table-row-actions__direct">
      <PermissionButton
        v-for="action in directActions"
        :key="action.key"
        :danger="action.danger"
        :disabled="action.disabled"
        :icon="action.icon"
        icon-only
        :tooltip="action.label"
        type="text"
        @click="emit('action', action.key)"
      />
    </div>
    <Dropdown v-if="overflowActions.length" placement="bottomRight" trigger="click">
      <Tooltip title="更多操作"><Button aria-label="更多操作" class="admin-table-row-actions__more" type="text"><IconifyIcon icon="lucide:ellipsis" /></Button></Tooltip>
      <template #overlay>
        <Menu @click="({ key }) => emit('action', String(key))">
          <MenuItem v-for="action in overflowActions" :key="action.key" :danger="action.danger" :disabled="action.disabled">
            <IconifyIcon :icon="action.icon" class="mr-2" />{{ action.label }}
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
    <Dropdown v-if="allowedActions.length" class="admin-table-row-actions__mobile" placement="bottomRight" trigger="click">
      <Tooltip title="更多操作"><Button aria-label="更多操作" class="admin-table-row-actions__more" type="text"><IconifyIcon icon="lucide:ellipsis" /></Button></Tooltip>
      <template #overlay>
        <Menu @click="({ key }) => emit('action', String(key))">
          <MenuItem v-for="action in allowedActions" :key="action.key" :danger="action.danger" :disabled="action.disabled">
            <IconifyIcon :icon="action.icon" class="mr-2" />{{ action.label }}
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
  </div>
</template>

<style scoped>
.admin-table-row-actions, .admin-table-row-actions__direct { display: inline-flex; align-items: center; gap: 2px; }
.admin-table-row-actions__more { width: 32px; height: 32px; padding: 0; border-radius: 0; }
.admin-table-row-actions__mobile { display: none; }
@media (max-width: 640px) { .admin-table-row-actions__direct, .admin-table-row-actions > :not(.admin-table-row-actions__mobile) { display: none; } .admin-table-row-actions__mobile { display: inline-flex; } }
</style>
