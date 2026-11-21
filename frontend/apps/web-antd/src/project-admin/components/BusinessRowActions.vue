<script lang="ts" setup>
import { computed, nextTick, ref, watch, type ComponentPublicInstance } from 'vue';
import { IconifyIcon } from '@vben/icons';
import { Button, Dropdown, Menu, MenuItem, Modal, Tooltip } from 'ant-design-vue';
import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';

export interface BusinessRowAction {
  key: string;
  label: string;
  icon: string;
  visible?: boolean;
  disabled?: boolean;
  loading?: boolean;
  danger?: boolean;
  onClick: () => unknown | Promise<unknown>;
  confirmTitle?: string;
  buttonProps?: { size?: 'small' | 'middle' | 'large'; type?: 'default' | 'dashed' | 'link' | 'primary' | 'text' };
}

const props = defineProps<{ actions: BusinessRowAction[] }>();
const actions = computed(() => props.actions.filter((action) => action.visible !== false));
const open = ref(false);
const pending = ref(false);
const confirming = ref(false);
const trigger = ref<InstanceType<typeof Button>>();
const menuItemElements = new Map<string, HTMLElement>();
const tooltipOpen = ref(false);
const focusRequested = ref(false);
const busy = computed(() => pending.value || confirming.value);

watch([open, busy], () => {
  if (open.value || busy.value) tooltipOpen.value = false;
  if (busy.value) open.value = false;
  if (!open.value) focusRequested.value = false;
});

function changeOpen(value: boolean) {
  open.value = value && !busy.value;
}

function changeTooltip(value: boolean) {
  tooltipOpen.value = value && !open.value && !busy.value;
}

async function focusFirstAvailable() {
  await nextTick();
  if (!open.value || !focusRequested.value) return;
  const firstAction = actions.value.find((action) => !blocked(action));
  if (!firstAction) {
    focusRequested.value = false;
    trigger.value?.$el?.focus();
    return;
  }
  const first = menuItemElements.get(firstAction.key)?.closest<HTMLElement>('[role="menuitem"]');
  // The native item ref is stable even when Ant's Menu root is a fragment.
  if (!first || !first.getClientRects().length) return;
  first.focus();
  focusRequested.value = false;
}

function setMenuItemElement(key: string, element: Element | ComponentPublicInstance | null) {
  if (element instanceof HTMLElement) {
    menuItemElements.set(key, element);
    if (focusRequested.value) requestAnimationFrame(() => { void focusFirstAvailable(); });
  } else {
    menuItemElements.delete(key);
  }
}

function openFromKeyboard() {
  if (busy.value) return;
  changeOpen(true);
  focusRequested.value = true;
  requestAnimationFrame(() => { void focusFirstAvailable(); });
}

function blocked(action: BusinessRowAction) {
  return pending.value || confirming.value || action.disabled || action.loading;
}

async function execute(key: string) {
  const action = actions.value.find((item) => item.key === key);
  if (!action || blocked(action)) return;
  pending.value = true;
  try {
    await action.onClick();
  } finally {
    pending.value = false;
  }
}

async function select(action: BusinessRowAction) {
  if (blocked(action)) return;
  open.value = false;
  await nextTick();
  if (!actions.value.some((item) => item.key === action.key) || blocked(action)) return;
  if (!action.confirmTitle) {
    return execute(action.key);
  }
  confirming.value = true;
  let submitted = false;
  Modal.confirm({
    title: action.confirmTitle,
    okText: $t('businessUi.confirm'),
    cancelText: $t('businessUi.cancel'),
    okButtonProps: { danger: action.danger },
    onCancel: () => { confirming.value = false; },
    async onOk() {
      if (submitted) return;
      const current = actions.value.find((item) => item.key === action.key);
      if (!current || current.disabled || current.loading || pending.value) {
        confirming.value = false;
        return;
      }
      submitted = true;
      confirming.value = false;
      return execute(action.key);
    },
    afterClose: () => { confirming.value = false; },
  });
}

function closeMenu() {
  open.value = false;
  trigger.value?.$el?.focus();
}

function onItemKeydown(event: KeyboardEvent, action: BusinessRowAction) {
  if (event.key === ' ') {
    event.preventDefault();
    event.stopPropagation();
    void select(action);
  }
}

function onMenuKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    closeMenu();
  } else if (event.key === 'Tab') {
    open.value = false;
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    closeMenu();
  } else if (event.key === 'Tab') {
    open.value = false;
  } else if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    event.stopPropagation();
    if (busy.value) return;
    if (open.value && event.key !== 'ArrowDown') closeMenu();
    else openFromKeyboard();
  }
}
</script>

<template>
  <span v-if="actions.length" class="business-row-actions" @click.stop @keydown.esc.stop="open = false">
    <PermissionButton
      v-if="actions.length === 1"
      v-bind="actions[0]!.buttonProps"
      :icon="actions[0]!.icon"
      :danger="actions[0]!.danger"
      :disabled="!!blocked(actions[0]!)"
      :loading="actions[0]!.loading || pending"
      @click="select(actions[0]!)"
    >{{ actions[0]!.label }}</PermissionButton>
    <Dropdown
      v-else
      :open="open"
      :trigger="['click']"
      placement="bottomRight"
      overlay-class-name="business-row-actions-overlay"
      @open-change="changeOpen"
    >
      <Tooltip
        :title="$t('businessUi.moreActions')"
        :open="tooltipOpen"
        @open-change="changeTooltip"
      >
        <Button
          ref="trigger"
          type="text"
          class="business-row-actions-trigger"
          :class="{ 'business-row-actions-trigger--open': open }"
          :loading="pending"
          :disabled="busy"
          :aria-busy="busy"
          :aria-label="$t('businessUi.moreActions')"
          aria-haspopup="menu"
          :aria-expanded="open"
          @keydown="onKeydown"
        >
          <template #icon><IconifyIcon icon="lucide:ellipsis" /></template>
        </Button>
      </Tooltip>
      <template #overlay>
        <Menu
          class="business-row-actions-menu"
          @keydown="onMenuKeydown"
          @click="({ domEvent }) => domEvent.stopPropagation()"
        >
          <MenuItem
            v-for="(action, index) in actions"
            :key="action.key"
            class="business-row-actions-menu-item"
            :class="{ 'business-row-actions-segment-start': index > 0 && !!action.danger !== !!actions[index - 1]!.danger }"
            :danger="action.danger"
            :disabled="!!blocked(action)"
            :aria-busy="action.loading || pending"
            @keydown="onItemKeydown($event, action)"
            @click="select(action)"
          >
            <span
              :ref="(element) => setMenuItemElement(action.key, element)"
              class="business-row-actions-item"
            ><IconifyIcon :icon="action.icon" />{{ action.label }}</span>
          </MenuItem>
        </Menu>
      </template>
    </Dropdown>
  </span>
</template>

<style scoped>
.business-row-actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.business-row-actions-trigger.ant-btn {
  display: inline-flex;
  width: 32px;
  height: 32px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 8px !important;
  color: hsl(var(--muted-foreground));
  background: color-mix(in srgb, hsl(var(--muted)) 65%, transparent);
  box-shadow: none;
  transition: background 150ms, color 150ms;
}

.business-row-actions-trigger.ant-btn:not(:disabled):hover,
.business-row-actions-trigger.ant-btn.business-row-actions-trigger--open {
  color: hsl(var(--primary));
  background: color-mix(in srgb, hsl(var(--primary)) 12%, hsl(var(--background)));
}

.business-row-actions-trigger.ant-btn:not(:disabled):active {
  background: color-mix(in srgb, hsl(var(--primary)) 18%, hsl(var(--background)));
}

.business-row-actions-trigger.ant-btn:focus:not(:focus-visible) {
  outline: none;
  box-shadow: none;
}

.business-row-actions-trigger.ant-btn:focus-visible {
  outline: 2px solid hsl(var(--primary));
  outline-offset: 2px;
}

.business-row-actions-trigger.ant-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.business-row-actions-trigger :deep(svg) {
  width: 18px;
  height: 18px;
}
</style>

<style>
.business-row-actions-overlay .business-row-actions-menu.ant-dropdown-menu {
  min-width: 180px;
  max-width: calc(100vw - 24px);
  padding: 6px;
  border: 1px solid hsl(var(--border));
  border-radius: 8px;
  color: hsl(var(--popover-foreground));
  background: hsl(var(--popover));
  box-shadow: 0 6px 24px rgb(0 0 0 / 12%), 0 2px 6px rgb(0 0 0 / 6%);
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item {
  min-height: 36px;
  padding: 8px 10px;
  border-radius: 6px;
  color: hsl(var(--popover-foreground));
  font-size: 14px;
  line-height: 20px;
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item:not(.ant-dropdown-menu-item-disabled):hover,
.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item-active:not(.ant-dropdown-menu-item-disabled) {
  background: hsl(var(--muted));
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item:focus-visible {
  outline: 2px solid hsl(var(--primary));
  outline-offset: -2px;
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item-danger {
  color: hsl(var(--destructive));
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item-danger:not(.ant-dropdown-menu-item-disabled):hover,
.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item-danger.ant-dropdown-menu-item-active:not(.ant-dropdown-menu-item-disabled) {
  background: color-mix(in srgb, hsl(var(--destructive)) 12%, hsl(var(--popover)));
}

.business-row-actions-overlay .business-row-actions-menu-item.ant-dropdown-menu-item-disabled {
  color: hsl(var(--muted-foreground));
  opacity: 0.55;
}

.business-row-actions-overlay .business-row-actions-segment-start {
  position: relative;
  margin-top: 5px;
}

.business-row-actions-overlay .business-row-actions-segment-start::before {
  position: absolute;
  top: -3px;
  right: 4px;
  left: 4px;
  border-top: 1px solid hsl(var(--border));
  content: '';
}

.business-row-actions-overlay .business-row-actions-item {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: normal;
  overflow-wrap: anywhere;
}

.business-row-actions-overlay .business-row-actions-item svg {
  width: 16px;
  min-width: 16px;
  height: 16px;
}
</style>
