<script lang="ts" setup>
import type { NotificationItem } from '@vben/layouts';
import type { AdminNotificationRecord } from '#/api/core/notification';

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { AuthenticationLoginExpiredModal } from '@vben/common-ui';
import {
  BasicLayout,
  LockScreen,
  Notification,
  UserDropdown,
} from '@vben/layouts';
import { preferences, updatePreferences } from '@vben/preferences';
import { useAccessStore, useUserStore } from '@vben/stores';

import { $t } from '#/locales';
import { formatBeijingDateTime } from '#/utils/datetime';
import {
  clearNotificationsApi,
  getLatestNotificationsApi,
  getNotificationUnreadCountApi,
  hideNotificationApi,
  markAllNotificationsReadApi,
  markNotificationReadApi,
} from '#/api/core/notification';
import { useAdminIdentity } from '#/composables/use-admin-identity';
import { getAdminAppearance, LOGIN_THEME_COLORS } from '#/preferences';
import { useAuthStore } from '#/store';
import LoginForm from '#/views/_core/authentication/login.vue';

const notifications = ref<NotificationItem[]>([]);
const unreadCount = ref(0);
let notificationTimer: number | undefined;

function translateNotification(
  key?: null | string,
  fallback = '',
  parameters: Record<string, unknown> = {},
) {
  if (!key) return fallback;
  const translated = $t(key, parameters as Record<string, string | number>);
  return translated === key ? fallback : translated;
}

function toNotificationItem(item: AdminNotificationRecord): NotificationItem {
  return {
    id: item.id,
    date: formatBeijingDateTime(item.published_at),
    icon:
      item.icon ||
      `lucide:${item.severity === 'error' ? 'circle-alert' : 'bell'}`,
    isRead: item.is_read,
    link: item.link || undefined,
    message: translateNotification(
      item.message_key,
      item.message || '',
      item.parameters,
    ),
    title: translateNotification(
      item.title_key,
      item.title || $t('notification.defaultTitle'),
      item.parameters,
    ),
  };
}

async function loadNotifications() {
  const [latest, unread] = await Promise.all([
    getLatestNotificationsApi(),
    getNotificationUnreadCountApi(),
  ]);
  notifications.value = latest.data.map(toNotificationItem);
  unreadCount.value = unread.count;
}

const router = useRouter();
const userStore = useUserStore();
const authStore = useAuthStore();
const accessStore = useAccessStore();
const { displayName, roleName } = useAdminIdentity();
const adminAppearance = getAdminAppearance();
const themeModeStorageKey = 'laravel-vben-admin:theme-mode';
const storedThemeMode = localStorage.getItem(themeModeStorageKey);
const preferredThemeMode = ['light', 'dark', 'auto'].includes(
  storedThemeMode || '',
)
  ? (storedThemeMode as 'auto' | 'dark' | 'light')
  : adminAppearance.mode;
updatePreferences({
  app: { layout: adminAppearance.layout },
  theme: {
    builtinType: adminAppearance.theme,
    colorPrimary:
      LOGIN_THEME_COLORS[adminAppearance.theme] || LOGIN_THEME_COLORS.default,
    mode: preferredThemeMode,
  },
});
watch(
  () => preferences.theme.mode,
  (mode) => localStorage.setItem(themeModeStorageKey, mode),
  { flush: 'sync' },
);
const showDot = computed(() => unreadCount.value > 0);

const menus = computed(() => [
  {
    handler: () => {
      router.push({ name: 'Profile' });
    },
    icon: 'lucide:user',
    text: $t('auth.profile'),
  },
]);

const avatar = computed(() => {
  return userStore.userInfo?.avatar ?? preferences.app.defaultAvatar;
});

async function handleLogout() {
  await authStore.logout(false);
}

async function handleNoticeClear() {
  await clearNotificationsApi();
  await loadNotifications();
}

async function markRead(id: number | string) {
  await markNotificationReadApi(String(id));
  await loadNotifications();
}

async function remove(id: number | string) {
  await hideNotificationApi(String(id));
  await loadNotifications();
}

async function handleMakeAll() {
  await markAllNotificationsReadApi();
  await loadNotifications();
}

const viewAll = () => router.push({ name: 'Notifications' });

const handleClick = (item: NotificationItem) => {
  if (!item.isRead) void markRead(item.id);
  // 如果通知项有链接，点击时跳转
  if (item.link) {
    navigateTo(item.link, item.query, item.state);
  }
};

function navigateTo(
  link: string,
  query?: Record<string, any>,
  state?: Record<string, any>,
) {
  if (link.startsWith('https://')) {
    // 外部链接，在新标签页打开
    window.open(link, '_blank', 'noopener,noreferrer');
  } else {
    // 内部路由链接，支持 query 参数和 state
    router.push({
      path: link,
      query: query || {},
      state,
    });
  }
}

onMounted(() => {
  void loadNotifications();
  notificationTimer = window.setInterval(() => {
    if (document.visibilityState === 'visible') void loadNotifications();
  }, 60_000);
});
onBeforeUnmount(() => {
  if (notificationTimer) window.clearInterval(notificationTimer);
});
</script>

<template>
  <BasicLayout @clear-preferences-and-logout="handleLogout">
    <template #user-dropdown>
      <UserDropdown
        :avatar
        :menus
        :description="displayName"
        :text="roleName"
        @logout="handleLogout"
        @clear-preferences-and-logout="handleLogout"
      />
    </template>
    <template #notification>
      <Notification
        :dot="showDot"
        :notifications="notifications"
        :unread-count="unreadCount"
        @clear="handleNoticeClear"
        @read="(item) => item.id && markRead(item.id)"
        @remove="(item) => item.id && remove(item.id)"
        @make-all="handleMakeAll"
        @on-click="handleClick"
        @view-all="viewAll"
      />
    </template>
    <template #extra>
      <AuthenticationLoginExpiredModal
        v-model:open="accessStore.loginExpired"
        :avatar
      >
        <LoginForm />
      </AuthenticationLoginExpiredModal>
    </template>
    <template #lock-screen>
      <LockScreen :avatar @to-login="handleLogout" />
    </template>
  </BasicLayout>
</template>
