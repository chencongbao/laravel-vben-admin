<script lang="ts" setup>
import type { Key } from 'ant-design-vue/es/_util/type';

import { computed, watch } from 'vue';

import { $t } from '@vben/locales';

import { Checkbox, Tree } from 'ant-design-vue';

interface AccessTreeNode {
  children?: AccessTreeNode[];
  key: Key;
  title: string;
}

const props = withDefaults(defineProps<{
  checkStrictly?: boolean;
  disabled?: boolean;
  includeAncestors?: boolean;
  treeData: AccessTreeNode[];
}>(), {
  checkStrictly: false,
  disabled: false,
  includeAncestors: false,
});

const checkedKeys = defineModel<Key[]>('checkedKeys', { default: () => [] });
const expandedKeys = defineModel<Key[]>('expandedKeys', { default: () => [] });

function collectKeys(nodes: AccessTreeNode[], groupsOnly = false) {
  const keys: Key[] = [];
  const visit = (items: AccessTreeNode[]) => items.forEach((item) => {
    if (!groupsOnly || item.children?.length) keys.push(item.key);
    if (item.children?.length) visit(item.children);
  });
  visit(nodes);
  return keys;
}

const allKeys = computed(() => collectKeys(props.treeData));
const groupKeys = computed(() => collectKeys(props.treeData, true));
const parentByKey = computed(() => {
  const parents = new Map<Key, Key>();
  const visit = (nodes: AccessTreeNode[], parent?: Key) => nodes.forEach((node) => {
    if (parent !== undefined) parents.set(node.key, parent);
    if (node.children?.length) visit(node.children, node.key);
  });
  visit(props.treeData);
  return parents;
});
const descendantsByKey = computed(() => {
  const descendants = new Map<Key, Key[]>();
  const visit = (node: AccessTreeNode): Key[] => {
    const keys = (node.children ?? []).flatMap((child) => [child.key, ...visit(child)]);
    descendants.set(node.key, keys);
    return keys;
  };
  props.treeData.forEach((node) => visit(node));
  return descendants;
});

function normalizeWithAncestors(keys: Key[]) {
  const selected = new Set(keys);
  for (const key of keys) {
    let parent = parentByKey.value.get(key);
    while (parent !== undefined) {
      selected.add(parent);
      parent = parentByKey.value.get(parent);
    }
  }
  return allKeys.value.filter((key) => selected.has(key));
}
const allChecked = computed({
  get: () => allKeys.value.length > 0 && allKeys.value.every((key) => checkedKeys.value.includes(key)),
  set: (checked: boolean) => { checkedKeys.value = checked ? [...allKeys.value] : []; },
});
const indeterminate = computed(() => {
  const count = allKeys.value.filter((key) => checkedKeys.value.includes(key)).length;
  return count > 0 && count < allKeys.value.length;
});
const allExpanded = computed({
  get: () => groupKeys.value.length > 0 && groupKeys.value.every((key) => expandedKeys.value.includes(key)),
  set: (expanded: boolean) => { expandedKeys.value = expanded ? [...groupKeys.value] : []; },
});

function handleCheck(keys: Key[] | { checked: Key[] }) {
  const nextKeys = Array.isArray(keys) ? keys : keys.checked;
  if (!props.includeAncestors) {
    checkedKeys.value = nextKeys;
    return;
  }

  const previous = new Set(checkedKeys.value);
  const next = new Set(nextKeys);
  const added = nextKeys.filter((key) => !previous.has(key));
  const removed = checkedKeys.value.filter((key) => !next.has(key));
  for (const key of added) descendantsByKey.value.get(key)?.forEach((childKey) => next.add(childKey));
  for (const key of removed) {
    next.delete(key);
    descendantsByKey.value.get(key)?.forEach((childKey) => next.delete(childKey));
  }
  groupKeys.value.forEach((key) => next.delete(key));
  checkedKeys.value = normalizeWithAncestors([...next]);
}

watch([() => props.treeData, checkedKeys], () => {
  if (!props.includeAncestors) return;
  const normalized = normalizeWithAncestors(checkedKeys.value);
  if (normalized.length !== checkedKeys.value.length || normalized.some((key, index) => key !== checkedKeys.value[index])) {
    checkedKeys.value = normalized;
  }
}, { deep: true, immediate: true });
</script>

<template>
  <div class="admin-access-tree-selector">
    <div class="admin-access-tree-selector__actions">
      <Checkbox v-model:checked="allChecked" :disabled="disabled" :indeterminate="indeterminate">{{ $t('system.accessTree.selectAll') }}</Checkbox>
      <Checkbox v-model:checked="allExpanded">{{ $t('system.accessTree.expandAll') }}</Checkbox>
    </div>
    <Tree
      :checked-keys="checkedKeys"
      :check-strictly="checkStrictly || includeAncestors"
      checkable
      :disabled="disabled"
      :expanded-keys="expandedKeys"
      :show-line="{ showLeafIcon: false }"
      :tree-data="treeData"
      @check="handleCheck"
      @update:expanded-keys="expandedKeys = $event"
    >
      <template #switcherIcon="{ expanded }">
        <span class="admin-access-tree-selector__switcher" aria-hidden="true">{{ expanded ? '−' : '+' }}</span>
      </template>
    </Tree>
  </div>
</template>
