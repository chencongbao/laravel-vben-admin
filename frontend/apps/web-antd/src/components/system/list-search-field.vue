<script lang="ts" setup>
defineOptions({ name: 'AdminListSearchField' });

defineProps<{
  label: string;
}>();
</script>

<template>
  <div class="admin-list-search-field">
    <span class="admin-list-search-field__label">{{ label }}</span>
    <div class="admin-list-search-field__control"><slot /></div>
  </div>
</template>

<style scoped>
.admin-list-search-field {
  display: flex;
  min-width: 0;
  height: 32px;
  box-sizing: border-box;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--background));
  transition: border-color 0.2s, box-shadow 0.2s;
}

.admin-list-search-field__label {
  display: flex;
  min-width: 88px;
  height: 100%;
  box-sizing: border-box;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  border-right: 1px solid hsl(var(--border));
  color: hsl(var(--foreground));
  font-weight: 600;
  white-space: nowrap;
}

.admin-list-search-field__control {
  min-width: 0;
  height: 100%;
  flex: 1;
  overflow: hidden;
}

.admin-list-search-field__control :deep(.ant-input),
.admin-list-search-field__control :deep(.ant-input-affix-wrapper),
.admin-list-search-field__control :deep(.ant-input-number),
.admin-list-search-field__control :deep(.ant-picker),
.admin-list-search-field__control :deep(.ant-select-selector) {
  height: 30px !important;
  min-height: 30px !important;
  box-sizing: border-box !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}

.admin-list-search-field__control :deep(.ant-select),
.admin-list-search-field__control :deep(.ant-input-number),
.admin-list-search-field__control :deep(.ant-picker) {
  width: 100%;
  height: 100%;
}

.admin-list-search-field:focus-within .admin-list-search-field__label {
  color: hsl(var(--primary));
}

.admin-list-search-field:focus-within {
  border-color: hsl(var(--primary));
  box-shadow: 0 0 0 2px hsl(var(--primary) / 10%);
}

.admin-list-search-field:focus-within .admin-list-search-field__label {
  border-right-color: hsl(var(--primary));
}

@media (max-width: 640px) {
  .admin-list-search-field__label {
    min-width: 80px;
    padding: 0 8px;
  }
}
</style>
