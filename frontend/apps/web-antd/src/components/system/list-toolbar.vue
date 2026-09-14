<script lang="ts" setup>
defineOptions({ name: 'AdminListToolbar' });
</script>

<template>
  <div class="admin-list-toolbar">
    <div class="admin-list-toolbar__left"><slot name="left" /></div>
    <div class="admin-list-toolbar__right"><slot name="right" /></div>
  </div>
</template>

<style scoped>
.admin-list-toolbar { display: flex; min-height: 40px; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
.admin-list-toolbar__left, .admin-list-toolbar__right { display: flex; min-width: 0; align-items: center; flex-wrap: wrap; gap: 8px; }
.admin-list-toolbar__right { justify-content: flex-end; margin-left: auto; }
@media (max-width: 640px) { .admin-list-toolbar { align-items: stretch; flex-direction: column; } .admin-list-toolbar__right { justify-content: flex-start; margin-left: 0; } }
</style>
