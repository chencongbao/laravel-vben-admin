<script setup lang="ts">
import type { VbenFormSchema } from '#/adapter/form';
import type { Recordable } from '@vben/types';

import { computed, onMounted, ref } from 'vue';

import { ProfileBaseSetting } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { Avatar, Button, message, Spin, Upload } from 'ant-design-vue';
import type { UploadRequestOption } from 'ant-design-vue/es/vc-upload/interface';

import {
  getDefaultAvatarsApi,
  getUserInfoApi,
  updateProfileApi,
  uploadAvatarApi,
} from '#/api';
import { $t } from '#/locales';

const profileRef = ref();
const userStore = useUserStore();
const avatarOptions = ref<{ id: string; url: string }[]>([]);
const avatarLoading = ref(false);
const currentAvatar = ref('');
const selectedAvatar = ref('');
const formSchema = computed((): VbenFormSchema[] => [
  {
    component: 'Input',
    componentProps: {
      placeholder: $t('page.profile.basic.namePlaceholder'),
    },
    fieldName: 'name',
    label: $t('page.profile.basic.name'),
  },
]);
onMounted(async () => {
  const [user, defaults] = await Promise.all([
    getUserInfoApi(),
    getDefaultAvatarsApi(),
  ]);
  avatarOptions.value = defaults.avatars;
  currentAvatar.value = user.avatar;
  selectedAvatar.value =
    defaults.avatars.find((item) => item.url === user.avatar)?.id ||
    user.avatar;
  await profileRef.value.getFormApi().setValues({ name: user.realName });
});
async function submit(values: Recordable<any>) {
  await updateProfileApi({
    avatar: selectedAvatar.value || null,
    name: values.name,
  });
  userStore.setUserInfo(await getUserInfoApi());
  currentAvatar.value = userStore.userInfo?.avatar || '';
  message.success($t('page.profile.basic.updated'));
}

function selectAvatar(item: { id: string; url: string }) {
  selectedAvatar.value = item.id;
  currentAvatar.value = item.url;
}

async function uploadAvatar(options: UploadRequestOption) {
  avatarLoading.value = true;
  try {
    const result = await uploadAvatarApi(options.file as File);
    currentAvatar.value = result.avatar;
    selectedAvatar.value = result.avatar;
    userStore.setUserInfo(await getUserInfoApi());
    options.onSuccess?.(result);
    message.success($t('page.profile.basic.avatarUploaded'));
  } catch (error) {
    options.onError?.(error as Error);
  } finally {
    avatarLoading.value = false;
  }
}
</script>
<template>
  <section class="profile-section">
    <div class="profile-section-heading">
      <h2>{{ $t('page.profile.basic.title') }}</h2>
      <p>{{ $t('page.profile.basic.description') }}</p>
    </div>
    <div class="avatar-setting">
      <div class="avatar-preview">
        <Spin :spinning="avatarLoading">
          <Avatar :size="88" :src="currentAvatar" />
        </Spin>
        <div>
          <div class="avatar-label">{{ $t('page.profile.basic.avatar') }}</div>
          <div class="avatar-help">
            {{ $t('page.profile.basic.avatarHelp') }}
          </div>
          <Upload
            accept="image/jpeg,image/png,image/webp"
            :custom-request="uploadAvatar"
            :show-upload-list="false"
          >
            <Button :loading="avatarLoading" class="avatar-upload-button">
              {{ $t('page.profile.basic.uploadAvatar') }}
            </Button>
          </Upload>
        </div>
      </div>
      <div class="avatar-options-label">
        {{ $t('page.profile.basic.defaultAvatars') }}
      </div>
      <div class="avatar-options">
        <button
          v-for="item in avatarOptions"
          :key="item.id"
          :aria-label="$t('page.profile.basic.selectAvatar')"
          class="avatar-option"
          :class="{ selected: selectedAvatar === item.id }"
          type="button"
          @click="selectAvatar(item)"
        >
          <Avatar :size="52" :src="item.url" />
        </button>
      </div>
    </div>
    <ProfileBaseSetting
      ref="profileRef"
      :form-schema="formSchema"
      @submit="submit"
    />
  </section>
</template>

<style scoped>
.profile-section-heading {
  margin-bottom: 28px;
}

.profile-section-heading h2 {
  margin: 0;
  color: hsl(var(--foreground));
  font-size: 20px;
  font-weight: 600;
  line-height: 28px;
}

.profile-section-heading p {
  margin: 6px 0 0;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
  line-height: 20px;
}
.avatar-setting {
  max-width: 672px;
  margin-bottom: 28px;
  padding: 20px;
  border: 1px solid hsl(var(--border));
  border-radius: 12px;
  background: hsl(var(--card));
}
.avatar-preview {
  display: flex;
  align-items: center;
  gap: 20px;
}
.avatar-label {
  color: hsl(var(--foreground));
  font-size: 15px;
  font-weight: 600;
}
.avatar-help {
  margin-top: 4px;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
}
.avatar-upload-button {
  margin-top: 12px;
}
.avatar-options-label {
  margin-top: 20px;
  margin-bottom: 10px;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
}
.avatar-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.avatar-option {
  display: inline-flex;
  padding: 3px;
  cursor: pointer;
  border: 2px solid transparent;
  border-radius: 50%;
  background: transparent;
  transition:
    border-color 0.2s,
    transform 0.2s;
}
.avatar-option:hover {
  transform: translateY(-2px);
}
.avatar-option.selected {
  border-color: hsl(var(--primary));
}
@media (max-width: 640px) {
  .avatar-preview {
    align-items: flex-start;
  }
}
</style>
