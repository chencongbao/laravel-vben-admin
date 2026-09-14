import { computed } from 'vue';

import { useUserStore } from '@vben/stores';

import { $t } from '#/locales';

export function useAdminIdentity() {
  const userStore = useUserStore();

  const displayName = computed(() => {
    const realName = userStore.userInfo?.realName?.trim();
    return realName || userStore.userInfo?.username || '';
  });

  const roleName = computed(() => {
    const roles = userStore.userInfo?.roles || [];
    if (roles.includes('administrator')) {
      return $t('auth.roles.administrator');
    }
    if (roles.includes('manager')) {
      return $t('auth.roles.manager');
    }

    return userStore.userInfo?.desc || $t('auth.roles.unassigned');
  });

  return {
    displayName,
    roleName,
  };
}
