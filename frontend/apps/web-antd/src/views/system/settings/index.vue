<script lang="ts" setup>
import { onMounted, ref } from 'vue';

import { Page } from '@vben/common-ui';

import { Button, Card, Form, FormItem, Input, InputNumber, message, Switch } from 'ant-design-vue';

import { getCollection, type SettingItem, updateSettings } from '#/api/system';

const loading = ref(false);
const settings = ref<SettingItem[]>([]);

async function load() {
  loading.value = true;
  try {
    const result = await getCollection<{ settings: SettingItem[] }>('/system/settings');
    settings.value = result.settings;
  } finally {
    loading.value = false;
  }
}

async function save() {
  loading.value = true;
  try {
    const result = await updateSettings(settings.value.map(({ key, value }) => ({ key, value })));
    settings.value = result.settings;
    message.success('系统设置已保存');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <Page description="设置项由 Laravel 配置注册，前端不会自行创建未知配置。" title="系统设置">
    <Card :loading="loading">
      <Form layout="vertical">
        <FormItem v-for="item in settings" :key="item.key" :label="item.key">
          <Switch v-if="item.type === 'boolean'" v-model:checked="item.value" />
          <InputNumber v-else-if="item.type === 'integer'" v-model:value="item.value" class="w-full" />
          <Input v-else v-model:value="item.value" />
        </FormItem>
        <Button type="primary" @click="save">保存设置</Button>
      </Form>
    </Card>
  </Page>
</template>
