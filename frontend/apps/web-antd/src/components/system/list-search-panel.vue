<script lang="ts" setup>
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref } from 'vue';

import { Card } from 'ant-design-vue';

import PermissionButton from '#/components/system/permission-button.vue';
import { $t } from '#/locales';

defineOptions({ name: 'AdminListSearchPanel' });

const fieldsElement = ref<HTMLElement>();
const expanded = ref(false);
const hasMore = ref(false);
const collapsedHeight = ref(0);
let resizeObserver: ResizeObserver | undefined;

function measureRows() {
  const container = fieldsElement.value;
  const fields = container
    ? [...container.children] as HTMLElement[]
    : [];
  const firstField = fields[0];
  if (!container || !firstField) {
    hasMore.value = false;
    collapsedHeight.value = 0;
    return;
  }

  const firstRowTop = firstField.offsetTop;
  const firstRowFields = fields.filter(
    (field) => Math.abs(field.offsetTop - firstRowTop) <= 1,
  );
  collapsedHeight.value = Math.max(
    ...firstRowFields.map((field) => field.offsetHeight),
  );
  hasMore.value = fields.some(
    (field) => field.offsetTop > firstRowTop + 1,
  );
  if (!hasMore.value) expanded.value = false;
}

function toggleExpanded() {
  expanded.value = !expanded.value;
}

onMounted(() => {
  void nextTick(measureRows);
  resizeObserver = new ResizeObserver(measureRows);
  if (fieldsElement.value) resizeObserver.observe(fieldsElement.value);
});

onUpdated(() => void nextTick(measureRows));
onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
  <Card :body-style="{ padding: '16px' }" class="admin-list-search-panel">
    <div class="admin-list-search-panel__content">
      <div
        ref="fieldsElement"
        class="admin-list-search-panel__fields"
        :class="{ 'is-collapsed': hasMore && !expanded }"
        :style="hasMore && !expanded ? { maxHeight: `${collapsedHeight}px` } : undefined"
      >
        <slot />
      </div>
      <div class="admin-list-search-panel__actions">
        <PermissionButton
          v-if="hasMore"
          :icon="expanded ? 'lucide:chevron-up' : 'lucide:chevron-down'"
          @click="toggleExpanded"
        >
          {{ expanded ? $t('common.actions.collapse') : $t('common.actions.expand') }}
        </PermissionButton>
        <slot name="actions" />
      </div>
    </div>
  </Card>
</template>

<style scoped>
.admin-list-search-panel {
  margin-bottom: 12px;
  border-radius: 0;
  background: hsl(var(--card));
  box-shadow: 0 1px 2px hsl(var(--foreground) / 4%);
}

.admin-list-search-panel__content {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  justify-content: flex-start;
}

.admin-list-search-panel__fields {
  display: flex;
  min-width: 0;
  max-width: calc(100% - 176px);
  flex: 0 1 auto;
  flex-wrap: wrap;
  gap: 12px;
}

.admin-list-search-panel__fields :slotted(*) {
  width: min(320px, 100%);
  flex: 0 0 320px;
}

.admin-list-search-panel__fields.is-collapsed {
  overflow: hidden;
}

.admin-list-search-panel__actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.admin-list-search-panel :deep(.ant-input),
.admin-list-search-panel :deep(.ant-input-affix-wrapper),
.admin-list-search-panel :deep(.ant-input-number),
.admin-list-search-panel :deep(.ant-picker),
.admin-list-search-panel :deep(.ant-select-selector) {
  border-radius: 0 !important;
}

@media (max-width: 768px) {
  .admin-list-search-panel__content {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-list-search-panel__fields {
    width: 100%;
    max-width: none;
  }

  .admin-list-search-panel__fields :slotted(*) {
    width: 100%;
    flex-basis: 100%;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .admin-list-search-panel__fields :slotted(*) {
    width: 260px;
    flex-basis: 260px;
  }
}
</style>
