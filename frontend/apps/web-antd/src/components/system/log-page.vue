<script lang="ts" setup>
import { onMounted, reactive, ref } from 'vue';
import { Page } from '@vben/common-ui';
import { Button, Card, Input, Select, Space, Table, Tag } from 'ant-design-vue';
import { getResource } from '#/api/system';

const props = defineProps<{
  columns: Array<{ dataIndex: string; title: string }>;
  filters: Array<{ key: string; label: string; options?: Array<{ label: string; value: number | string }> }>;
  path: string;
  title: string;
}>();
const loading = ref(false); const rows = ref<Record<string, any>[]>([]);
const values = reactive<Record<string, any>>({});
const pagination = reactive({ current: 1, pageSize: 20, total: 0 });

async function load() {
  loading.value = true;
  try {
    const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== undefined));
    const result = await getResource(props.path, { ...params, page: pagination.current, per_page: pagination.pageSize });
    rows.value = result.data; pagination.total = result.total;
  } finally { loading.value = false; }
}
function search() { pagination.current = 1; void load(); }
function reset() { Object.keys(values).forEach((key) => delete values[key]); search(); }
function changePage(page: { current?: number; pageSize?: number }) { pagination.current = page.current ?? 1; pagination.pageSize = page.pageSize ?? 20; void load(); }
onMounted(load);
</script>

<template>
  <Page :title="title">
    <Card>
      <Space class="mb-4" wrap>
        <template v-for="filter in filters" :key="filter.key">
          <Select v-if="filter.options" v-model:value="values[filter.key]" allow-clear :options="filter.options" :placeholder="filter.label" class="w-40" />
          <Input v-else v-model:value="values[filter.key]" :placeholder="filter.label" class="w-48" @press-enter="search" />
        </template>
        <Button type="primary" @click="search">查询</Button><Button @click="reset">重置</Button>
      </Space>
      <Table :columns="columns" :data-source="rows" :loading="loading" :pagination="pagination" row-key="id" @change="changePage">
        <template #bodyCell="{ column, text }">
          <Tag v-if="column.dataIndex === 'succeeded'" :color="text ? 'green' : 'red'">{{ text ? '成功' : '失败' }}</Tag>
          <code v-else-if="column.dataIndex === 'changes' || column.dataIndex === 'context'">{{ JSON.stringify(text) }}</code>
        </template>
      </Table>
    </Card>
  </Page>
</template>
