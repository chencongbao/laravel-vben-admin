<script setup lang="ts">
import type {
  RcFile,
  UploadRequestOption,
} from 'ant-design-vue/es/vc-upload/interface';

import { onMounted, ref } from 'vue';

import { useUserStore } from '@vben/stores';

import { Avatar, Button, message, Spin, Upload } from 'ant-design-vue';

import {
  getDefaultAvatarsApi,
  getUserInfoApi,
  updateProfileApi,
  uploadAvatarApi,
} from '#/api';
import { $t } from '#/locales';

const emit = defineEmits<{ saved: [] }>();
const userStore = useUserStore();
const avatarOptions = ref<{ id: string; url: string }[]>([]);
const avatarLoading = ref(false);
const currentAvatar = ref('');
const selectedAvatar = ref('');
const allowedAvatarTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
const maxAvatarSize = 2 * 1024 * 1024;

onMounted(async () => {
  const [user, defaults] = await Promise.all([
    getUserInfoApi(),
    getDefaultAvatarsApi(),
  ]);
  avatarOptions.value = defaults.avatars;
  currentAvatar.value = user.avatar;
  selectedAvatar.value =
    defaults.avatars.find((item) => item.url === user.avatar)?.id || user.avatar;
});

function selectAvatar(item: { id: string; url: string }) {
  selectedAvatar.value = item.id;
  currentAvatar.value = item.url;
}

function beforeAvatarUpload(file: RcFile) {
  if (!allowedAvatarTypes.has(file.type)) {
    message.error($t('page.profile.basic.avatarTypeError'));
    return false;
  }
  if (file.size > maxAvatarSize) {
    message.error($t('page.profile.basic.avatarSizeError'));
    return false;
  }
  return true;
}

async function saveAvatar() {
  avatarLoading.value = true;
  try {
    const currentUser = await getUserInfoApi();
    await updateProfileApi({
      avatar: selectedAvatar.value || null,
      name: currentUser.realName,
    });
    const userInfo = await getUserInfoApi();
    userStore.setUserInfo(userInfo);
    message.success($t('page.profile.basic.avatarUpdated'));
    emit('saved');
  } finally {
    avatarLoading.value = false;
  }
}

async function uploadAvatar(options: UploadRequestOption) {
  avatarLoading.value = true;
  try {
    const result = await uploadAvatarApi(options.file as File);
    const userInfo = await getUserInfoApi();
    currentAvatar.value = userInfo.avatar || result.avatar;
    selectedAvatar.value = result.avatar;
    userStore.setUserInfo(userInfo);
    options.onSuccess?.(result);
    message.success($t('page.profile.basic.avatarUploaded'));
    emit('saved');
  } catch (error) {
    options.onError?.(error as Error);
  } finally {
    avatarLoading.value = false;
  }
}
</script>

<template>
  <Spin :spinning="avatarLoading">
    <div class="avatar-preview">
      <Avatar :size="88" :src="currentAvatar" />
      <div>
        <div class="avatar-label">{{ $t('page.profile.basic.avatar') }}</div>
        <div class="avatar-help">{{ $t('page.profile.basic.avatarHelp') }}</div>
        <Upload
          accept="image/jpeg,image/png,image/webp"
          :before-upload="beforeAvatarUpload"
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

    <div class="avatar-actions">
      <Button type="primary" :loading="avatarLoading" @click="saveAvatar">
        {{ $t('page.profile.basic.saveAvatar') }}
      </Button>
    </div>
  </Spin>
</template>

<style scoped>
.avatar-preview {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 8px 4px 20px;
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
  margin-bottom: 10px;
  color: hsl(var(--muted-foreground));
  font-size: 13px;
}
.avatar-options {
  display: grid;
  grid-template-columns: repeat(10, minmax(0, 1fr));
  gap: 10px;
}
.avatar-option {
  display: inline-flex;
  justify-content: center;
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
.avatar-actions {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}
@media (max-width: 640px) {
  .avatar-preview {
    align-items: flex-start;
  }
  .avatar-options {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}
</style>
