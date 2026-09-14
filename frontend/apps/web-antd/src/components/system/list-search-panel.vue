<script lang="ts" setup>
defineOptions({ name: 'AdminListSearchPanel' });
</script>

<template>
  <div class="admin-list-search-panel">
    <div class="admin-list-search-panel__fields"><slot /></div>
    <div class="admin-list-search-panel__actions"><slot name="actions" /></div>
  </div>
</template>

<style scoped>
.admin-list-search-panel { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 12px; padding: 12px 16px; border: 1px solid hsl(var(--border)); background: hsl(var(--muted) / 35%); }
.admin-list-search-panel__fields { display: grid; min-width: 0; flex: 1; grid-template-columns: repeat(auto-fit, minmax(180px, 240px)); gap: 12px; }
.admin-list-search-panel__actions { display: flex; flex-shrink: 0; gap: 8px; }
@media (max-width: 640px) { .admin-list-search-panel { align-items: stretch; flex-direction: column; } .admin-list-search-panel__fields { grid-template-columns: 1fr; } }
</style>
