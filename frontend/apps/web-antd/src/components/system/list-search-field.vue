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
