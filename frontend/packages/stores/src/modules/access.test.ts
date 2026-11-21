import { createPinia, setActivePinia } from 'pinia';
import { createPersistedState } from 'pinia-plugin-persistedstate';
import { beforeEach, describe, expect, it } from 'vitest';
import { createApp, nextTick } from 'vue';

import { useAccessStore } from './access';

describe('useAccessStore', () => {
  it('restores the lock marker without restoring the lock password', async () => {
    sessionStorage.removeItem('core-access');
    function newStore() {
      const pinia = createPinia().use(createPersistedState());
      createApp({}).use(pinia);
      return useAccessStore(pinia);
    }

    const store = newStore();
    store.setAccessToken('example-token');
    await nextTick();
    store.lockScreen('temporary-password');

    const restored = newStore();
    expect(restored.accessToken).toBe('example-token');
    expect(restored.isLockScreen).toBe(true);
    expect(restored.lockScreenPassword).toBeUndefined();
    expect(sessionStorage.getItem('core-access')).not.toContain(
      'temporary-password',
    );
    sessionStorage.removeItem('core-access');
  });

  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('updates accessMenus state', () => {
    const store = useAccessStore();
    expect(store.accessMenus).toEqual([]);
    store.setAccessMenus([{ name: 'Dashboard', path: '/dashboard' }]);
    expect(store.accessMenus).toEqual([
      { name: 'Dashboard', path: '/dashboard' },
    ]);
  });

  it('updates accessToken state correctly', () => {
    const store = useAccessStore();
    expect(store.accessToken).toBeNull(); // 初始状态
    store.setAccessToken('abc123');
    expect(store.accessToken).toBe('abc123');
  });

  it('returns the correct accessToken', () => {
    const store = useAccessStore();
    store.setAccessToken('xyz789');
    expect(store.accessToken).toBe('xyz789');
  });

  // 测试设置空的访问菜单列表
  it('handles empty accessMenus correctly', () => {
    const store = useAccessStore();
    store.setAccessMenus([]);
    expect(store.accessMenus).toEqual([]);
  });

  // 测试设置空的访问路由列表
  it('handles empty accessRoutes correctly', () => {
    const store = useAccessStore();
    store.setAccessRoutes([]);
    expect(store.accessRoutes).toEqual([]);
  });
});
