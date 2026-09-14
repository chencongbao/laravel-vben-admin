<script setup lang="ts">
import { computed, ref } from 'vue';

import { Profile } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { Modal } from 'ant-design-vue';

import { $t } from '#/locales';
import { useAdminIdentity } from '#/composables/use-admin-identity';

import ProfileAvatarSetting from './avatar-setting.vue';
import ProfileBase from './base-setting.vue';
import ProfilePasswordSetting from './password-setting.vue';
import ProfileSecuritySetting from './security-setting.vue';

const userStore = useUserStore();
const { displayName, roleName } = useAdminIdentity();

const tabsValue = ref<string>('basic');
const avatarSettingOpen = ref(false);

const profileUserInfo = computed(() => {
  if (!userStore.userInfo) {
    return null;
  }

  return {
    ...userStore.userInfo,
    realName: roleName.value,
    username: displayName.value,
  };
});

const tabs = computed(() => [
  {
    label: $t('profile.tabs.basic'),
    value: 'basic',
  },
  {
    label: $t('profile.tabs.security'),
    value: 'security',
  },
  {
    label: $t('profile.tabs.password'),
    value: 'password',
  },
]);
</script>
<template>
  <div class="size-full">
    <Profile
      v-model:model-value="tabsValue"
      :avatar-action-label="$t('profile.basic.editAvatar')"
      :title="$t('profile.title')"
      :user-info="profileUserInfo"
      :tabs="tabs"
      @avatar-click="avatarSettingOpen = true"
    >
      <template #content>
        <ProfileBase v-if="tabsValue === 'basic'" />
        <ProfileSecuritySetting v-if="tabsValue === 'security'" />
        <ProfilePasswordSetting v-if="tabsValue === 'password'" />
      </template>
    </Profile>
    <Modal
      v-model:open="avatarSettingOpen"
      :footer="null"
      :title="$t('profile.basic.avatarSetting')"
      width="720px"
    >
      <ProfileAvatarSetting @saved="avatarSettingOpen = false" />
    </Modal>
  </div>
</template>
