<script setup lang="ts">
import { computed, ref } from 'vue';

import { Profile } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { Modal } from 'ant-design-vue';

import { $t } from '#/locales';

import ProfileAvatarSetting from './avatar-setting.vue';
import ProfileBase from './base-setting.vue';
import ProfilePasswordSetting from './password-setting.vue';
import ProfileSecuritySetting from './security-setting.vue';

const userStore = useUserStore();

const tabsValue = ref<string>('basic');
const avatarSettingOpen = ref(false);

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
  <Profile
    v-model:model-value="tabsValue"
    :avatar-action-label="$t('profile.basic.editAvatar')"
    :title="$t('profile.title')"
    :user-info="userStore.userInfo"
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
</template>
