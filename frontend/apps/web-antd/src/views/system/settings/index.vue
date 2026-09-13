<script lang="ts" setup>
import { onMounted, ref } from 'vue';

import { Page } from '@vben/common-ui';
import { updatePreferences } from '@vben/preferences';

import { Button, Card, Form, FormItem, Input, InputNumber, message, Switch } from 'ant-design-vue';

import { getCollection, type SettingItem, updateSettings } from '#/api/system';
import { $t } from '#/locales';

const loading = ref(false);
const saving = ref(false);
const settings = ref<SettingItem[]>([]);

const settingMeta: Record<string, { description: string; label: string; placeholder: string }> = {
  'system.name': {
    description: 'page.system.settingsForm.fields.systemName.description',
    label: 'page.system.settingsForm.fields.systemName.label',
    placeholder: 'page.system.settingsForm.fields.systemName.placeholder',
  },
  'system.page_size': {
    description: 'page.system.settingsForm.fields.pageSize.description',
    label: 'page.system.settingsForm.fields.pageSize.label',
    placeholder: 'page.system.settingsForm.fields.pageSize.placeholder',
  },
};

function translate(key: string | undefined, fallback: string) {
  return key ? $t(key) : fallback;
}

function settingText(key: string, field: 'description' | 'label' | 'placeholder') {
  return settingMeta[key]?.[field];
}

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
  saving.value = true;
  try {
    const result = await updateSettings(settings.value.map(({ key, value }) => ({ key, value })));
    settings.value = result.settings;
    const systemName = settings.value.find(({ key }) => key === 'system.name')?.value;
    if (typeof systemName === 'string' && systemName) {
      updatePreferences({ app: { name: systemName } });
    }
    message.success($t('page.system.settingsForm.messages.saved'));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <Page>
    <Card :bordered="false" :loading="loading" class="settings-card">
      <Form layout="vertical">
        <div class="settings-list">
          <div v-for="item in settings" :key="item.key" class="settings-row">
            <div class="settings-copy">
              <label :for="`setting-${item.key}`" class="settings-label">
                {{ translate(settingText(item.key, 'label'), item.key) }}
              </label>
              <p v-if="settingText(item.key, 'description')" class="settings-description">
                {{ $t(settingText(item.key, 'description')!) }}
              </p>
            </div>

            <FormItem class="settings-control">
              <Switch
                v-if="item.type === 'boolean'"
                :id="`setting-${item.key}`"
                v-model:checked="item.value"
              />
              <InputNumber
                v-else-if="item.type === 'integer'"
                :id="`setting-${item.key}`"
                v-model:value="item.value"
                :max="item.key === 'system.page_size' ? 100 : undefined"
                :min="item.key === 'system.page_size' ? 10 : undefined"
                :placeholder="translate(settingText(item.key, 'placeholder'), '')"
                class="w-full"
              />
              <Input
                v-else
                :id="`setting-${item.key}`"
                v-model:value="item.value"
                :placeholder="translate(settingText(item.key, 'placeholder'), '')"
              />
            </FormItem>
          </div>
        </div>

        <div class="settings-actions">
          <Button
            v-access:code="'system.setting.update'"
            :loading="saving"
            size="large"
            type="primary"
            @click="save"
          >
            {{ $t('page.system.settingsForm.actions.save') }}
          </Button>
        </div>
      </Form>
    </Card>
  </Page>
</template>

<style scoped>
.settings-card {
  margin: 0 auto;
  max-width: 960px;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: 12px;
  box-shadow: 0 8px 28px rgb(15 23 42 / 5%);
}

.settings-list {
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: 10px;
}

.settings-row {
  display: grid;
  grid-template-columns: minmax(220px, 0.85fr) minmax(320px, 1.15fr);
  gap: 40px;
  align-items: center;
  padding: 24px;
  background: hsl(var(--background));
}

.settings-row + .settings-row {
  border-top: 1px solid hsl(var(--border));
}

.settings-copy {
  min-width: 0;
}

.settings-label {
  display: block;
  margin-bottom: 6px;
  color: hsl(var(--foreground));
  font-size: 14px;
  font-weight: 600;
  line-height: 22px;
}

.settings-description {
  margin: 0;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  line-height: 20px;
}

.settings-control {
  width: 100%;
  margin-bottom: 0;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: 20px;
}

.settings-actions :deep(.ant-btn) {
  min-width: 112px;
  border-radius: 8px;
  font-weight: 500;
}

.settings-control :deep(.ant-input),
.settings-control :deep(.ant-input-number) {
  border-radius: 8px;
}

@media (max-width: 767px) {
  .settings-row {
    grid-template-columns: 1fr;
    gap: 14px;
    padding: 20px;
  }
}
</style>
