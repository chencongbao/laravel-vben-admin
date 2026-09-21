<script lang="ts" setup>
import type { SettingItem } from '#/api/system';
import type {
  RcFile,
  UploadRequestOption,
} from 'ant-design-vue/es/vc-upload/interface';

import { computed, onMounted, ref } from 'vue';

import { useAccess } from '@vben/access';
import { Page } from '@vben/common-ui';
import { IconifyIcon } from '@vben/icons';
import { updatePreferences } from '@vben/preferences';

import {
  Button,
  Card,
  Form,
  FormItem,
  Input,
  InputNumber,
  message,
  Radio,
  RadioGroup,
  Switch,
  Upload,
} from 'ant-design-vue';

import {
  getCollection,
  resetSystemLogo,
  updateSettings,
  uploadSystemLogo,
} from '#/api/system';
import { $t } from '#/locales';
import { DEFAULT_ADMIN_LOGO } from '#/preferences';
import { setAdminDefaultPageSize } from '#/utils/pagination';

const loading = ref(false);
const saving = ref(false);
const logoUploading = ref(false);
const settings = ref<SettingItem[]>([]);
const { hasAccessByCodes } = useAccess();
const canUpdateSettings = computed(() =>
  hasAccessByCodes(['system.setting.update']),
);

const settingMeta: Record<
  string,
  { description: string; label: string; placeholder: string }
> = {
  'system.name': {
    description: 'system.settingsForm.fields.systemName.description',
    label: 'system.settingsForm.fields.systemName.label',
    placeholder: 'system.settingsForm.fields.systemName.placeholder',
  },
  'system.logo': {
    description: 'system.settingsForm.fields.systemLogo.description',
    label: 'system.settingsForm.fields.systemLogo.label',
    placeholder: '',
  },
  'system.page_size': {
    description: 'system.settingsForm.fields.pageSize.description',
    label: 'system.settingsForm.fields.pageSize.label',
    placeholder: 'system.settingsForm.fields.pageSize.placeholder',
  },
  'system.password_strength': {
    description: 'system.settingsForm.fields.passwordStrength.description',
    label: 'system.settingsForm.fields.passwordStrength.label',
    placeholder: 'system.settingsForm.fields.passwordStrength.placeholder',
  },
  'system.login_remember_me': {
    description: 'system.settingsForm.fields.loginRememberMe.description',
    label: 'system.settingsForm.fields.loginRememberMe.label',
    placeholder: '',
  },
  'system.login_description': {
    description: 'system.settingsForm.fields.loginDescription.description',
    label: 'system.settingsForm.fields.loginDescription.label',
    placeholder: 'system.settingsForm.fields.loginDescription.placeholder',
  },
};

function translate(key: string | undefined, fallback: string) {
  return key ? $t(key) : fallback;
}

function settingText(
  key: string,
  field: 'description' | 'label' | 'placeholder',
) {
  return settingMeta[key]?.[field];
}

async function load() {
  loading.value = true;
  try {
    const result = await getCollection<{ settings: SettingItem[] }>(
      '/system/settings',
    );
    settings.value = result.settings;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const result = await updateSettings(
      settings.value
        .filter(({ type }) => type !== 'asset')
        .map(({ key, value }) => ({ key, value })),
    );
    settings.value = result.settings;
    const systemName = settings.value.find(
      ({ key }) => key === 'system.name',
    )?.value;
    if (typeof systemName === 'string' && systemName) {
      updatePreferences({ app: { name: systemName } });
    }
    setAdminDefaultPageSize(
      settings.value.find(({ key }) => key === 'system.page_size')?.value,
    );
    message.success($t('system.settingsForm.messages.saved'));
  } finally {
    saving.value = false;
  }
}

async function beforeLogoUpload(file: RcFile) {
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    message.error($t('system.settingsForm.messages.logoTypeInvalid'));
    return false;
  }
  if (file.size > 2 * 1024 * 1024) {
    message.error($t('system.settingsForm.messages.logoSizeInvalid'));
    return false;
  }

  const dimensionsValid = await new Promise<boolean>((resolve) => {
    const image = new Image();
    const objectUrl = URL.createObjectURL(file);
    image.addEventListener(
      'load',
      () => {
        URL.revokeObjectURL(objectUrl);
        resolve(
          image.width >= 64 &&
            image.height >= 64 &&
            image.width <= 4096 &&
            image.height <= 4096,
        );
      },
      { once: true },
    );
    image.addEventListener(
      'error',
      () => {
        URL.revokeObjectURL(objectUrl);
        resolve(false);
      },
      { once: true },
    );
    image.src = objectUrl;
  });
  if (!dimensionsValid) {
    message.error($t('system.settingsForm.messages.logoDimensionsInvalid'));
    return false;
  }
  return true;
}

async function uploadLogo(options: UploadRequestOption) {
  logoUploading.value = true;
  try {
    const result = await uploadSystemLogo(options.file as File);
    const setting = settings.value.find(({ key }) => key === 'system.logo');
    if (setting) setting.value = result.logo;
    updatePreferences({
      logo: { source: result.logo, sourceDark: result.logo },
    });
    options.onSuccess?.(result);
    message.success($t('system.settingsForm.messages.logoUploaded'));
  } catch (error) {
    options.onError?.(error as Error);
  } finally {
    logoUploading.value = false;
  }
}

async function resetLogo(item: SettingItem) {
  logoUploading.value = true;
  try {
    await resetSystemLogo();
    item.value = null;
    updatePreferences({
      logo: { source: DEFAULT_ADMIN_LOGO, sourceDark: DEFAULT_ADMIN_LOGO },
    });
    message.success($t('system.settingsForm.messages.logoReset'));
  } finally {
    logoUploading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <Page
    :description="$t('system.settingsDescription')"
    :title="$t('system.settings')"
  >
    <Card :bordered="false" :loading="loading" class="settings-card">
      <Form layout="vertical">
        <div class="settings-list">
          <div v-for="item in settings" :key="item.key" class="settings-row">
            <div class="settings-copy">
              <label :for="`setting-${item.key}`" class="settings-label">
                {{ translate(settingText(item.key, 'label'), item.key) }}
              </label>
              <p
                v-if="settingText(item.key, 'description')"
                class="settings-description"
              >
                {{ $t(settingText(item.key, 'description')!) }}
              </p>
            </div>

            <FormItem class="settings-control">
              <div v-if="item.type === 'asset'" class="logo-setting">
                <Upload
                  v-if="canUpdateSettings"
                  accept="image/jpeg,image/png,image/webp"
                  :before-upload="beforeLogoUpload"
                  :custom-request="uploadLogo"
                  :show-upload-list="false"
                >
                  <button
                    class="logo-preview logo-preview-button"
                    :disabled="logoUploading"
                    type="button"
                  >
                    <img
                      :alt="
                        $t('system.settingsForm.fields.systemLogo.previewAlt')
                      "
                      :src="item.value || DEFAULT_ADMIN_LOGO"
                    />
                    <span class="logo-preview-overlay">
                      <IconifyIcon icon="lucide:upload" />
                      {{ $t('system.settingsForm.actions.replaceLogo') }}
                    </span>
                  </button>
                </Upload>
                <div v-else class="logo-preview">
                  <img
                    :alt="
                      $t('system.settingsForm.fields.systemLogo.previewAlt')
                    "
                    :src="item.value || DEFAULT_ADMIN_LOGO"
                  />
                </div>
                <div v-if="canUpdateSettings" class="logo-actions">
                  <Upload
                    accept="image/jpeg,image/png,image/webp"
                    :before-upload="beforeLogoUpload"
                    :custom-request="uploadLogo"
                    :show-upload-list="false"
                  >
                    <Button :loading="logoUploading">
                      {{ $t('system.settingsForm.actions.uploadLogo') }}
                    </Button>
                  </Upload>
                  <Button
                    v-if="item.value"
                    :disabled="logoUploading"
                    @click="resetLogo(item)"
                  >
                    {{ $t('system.settingsForm.actions.useDefaultLogo') }}
                  </Button>
                </div>
                <p class="logo-requirements">
                  {{ $t('system.settingsForm.fields.systemLogo.requirements') }}
                </p>
              </div>
              <Switch
                v-else-if="item.type === 'boolean'"
                :id="`setting-${item.key}`"
                v-model:checked="item.value"
              />
              <InputNumber
                v-else-if="item.type === 'integer'"
                :id="`setting-${item.key}`"
                v-model:value="item.value"
                :max="item.key === 'system.page_size' ? 100 : undefined"
                :min="item.key === 'system.page_size' ? 10 : undefined"
                :placeholder="
                  translate(settingText(item.key, 'placeholder'), '')
                "
                class="w-full"
              />
              <RadioGroup
                v-else-if="item.key === 'system.password_strength'"
                :id="`setting-${item.key}`"
                v-model:value="item.value"
              >
                <Radio value="weak">{{
                  $t('system.settingsForm.options.passwordStrength.weak')
                }}</Radio>
                <Radio value="strong">{{
                  $t('system.settingsForm.options.passwordStrength.strong')
                }}</Radio>
              </RadioGroup>
              <Input
                v-else
                :id="`setting-${item.key}`"
                v-model:value="item.value"
                :placeholder="
                  translate(settingText(item.key, 'placeholder'), '')
                "
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
            <IconifyIcon icon="lucide:save" />
            {{ $t('common.submit') }}
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
  border-radius: var(--radius);
  box-shadow: 0 8px 28px rgb(15 23 42 / 5%);
}

.settings-list {
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
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

.logo-setting,
.logo-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-setting {
  flex-wrap: wrap;
}

.logo-setting :deep(.ant-upload-wrapper) {
  display: block;
}

.logo-preview {
  display: flex;
  width: 72px;
  height: 72px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  background: hsl(var(--background));
}

.logo-preview img {
  max-width: 56px;
  max-height: 56px;
  object-fit: contain;
}

.logo-preview-button {
  position: relative;
  padding: 0;
  cursor: pointer;
}

.logo-preview-button:disabled {
  cursor: wait;
}

.logo-preview-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 11px;
  opacity: 0;
  background: rgb(15 23 42 / 68%);
  transition: opacity 0.2s ease;
}

.logo-preview-button:hover .logo-preview-overlay,
.logo-preview-button:focus-visible .logo-preview-overlay {
  opacity: 1;
}

.logo-requirements {
  flex-basis: 100%;
  margin: 0;
  color: hsl(var(--muted-foreground));
  font-size: 12px;
  line-height: 18px;
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
  justify-content: center;
  padding-top: 20px;
}

.settings-actions :deep(.ant-btn) {
  min-width: 112px;
  border-radius: var(--radius);
  font-weight: 500;
}

.settings-control :deep(.ant-input),
.settings-control :deep(.ant-input-number) {
  border-radius: var(--radius);
}

@media (max-width: 767px) {
  .settings-row {
    grid-template-columns: 1fr;
    gap: 14px;
    padding: 20px;
  }
}
</style>
