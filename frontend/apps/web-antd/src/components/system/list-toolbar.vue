<script lang="ts" setup>
defineOptions({ name: 'AdminListToolbar' });
</script>

<template>
  <div class="admin-list-toolbar">
    <div class="admin-list-toolbar__left"><slot name="left" /></div>
    <div class="admin-list-toolbar__right"><slot name="right" /></div>
  </div>
</template>
