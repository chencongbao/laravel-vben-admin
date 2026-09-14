<script lang="ts" setup>
import { computed } from 'vue';

import { message } from 'ant-design-vue';

import { $t } from '#/locales';

import PermissionButton from './permission-button.vue';

interface ExportColumn { dataIndex: string; title: string }

const props = withDefaults(defineProps<{
  columns: ExportColumn[];
  filename?: string;
  permission?: string | string[];
  rows: Record<string, any>[];
}>(), { filename: 'export' });

const exportColumns = computed(() => props.columns.filter((column) => column.dataIndex !== 'action'));

function safeCell(value: unknown): string {
  const text = value === null || value === undefined
    ? ''
    : typeof value === 'object' ? JSON.stringify(value) : String(value);
  const escaped = /^[=+\-@]/.test(text) ? `'${text}` : text;
  return `"${escaped.replaceAll('"', '""')}"`;
}

function download() {
  if (props.rows.length === 0) return void message.warning($t('common.table.noExportData'));
  const lines = [
    exportColumns.value.map((column) => safeCell(column.title)).join(','),
    ...props.rows.map((row) => exportColumns.value.map((column) => safeCell(row[column.dataIndex])).join(',')),
  ];
  const blob = new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `${props.filename}.csv`;
  anchor.click();
  URL.revokeObjectURL(url);
}
</script>

<template>
  <PermissionButton icon="lucide:download" :permission="permission" @click="download">{{ $t('common.actions.exportCurrentPage') }}</PermissionButton>
</template>
