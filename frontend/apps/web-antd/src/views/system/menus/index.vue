<script lang="ts" setup>
import ResourcePage from '#/components/system/resource-page.vue';

const fields = [
  { key: 'id', label: 'ID' },
  { key: 'code', label: '菜单编码', required: true },
  { key: 'parent_code', label: '上级编码' },
  { key: 'title', label: '标题', required: true },
  { key: 'type', label: '类型', required: true },
  { key: 'route_name', label: '路由名' },
  { key: 'route_path', label: '路由路径' },
  { key: 'view_key', label: '视图标识' },
  { key: 'permission_code', label: '权限编码' },
  { key: 'icon', label: '图标' },
  { key: 'sort', label: '排序', type: 'number' as const },
  { key: 'is_active', label: '状态', type: 'boolean' as const },
  { key: 'is_hidden', label: '隐藏', type: 'boolean' as const },
];
</script>

<template>
  <ResourcePage collection-key="menus" :fields="fields" path="/system/menus" title="菜单管理" />
</template>
