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
