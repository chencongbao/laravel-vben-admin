import { computed, onScopeDispose, ref } from 'vue';
import { useRouter } from 'vue-router';

import { preferences } from '@vben/preferences';

function useContentSpinner() {
  const spinning = ref(false);
  const startTime = ref(0);
  const router = useRouter();
  const minShowTime = 500; // 最小显示时间
  const enableLoading = computed(() => preferences.transition.loading);
  let stopTimer: ReturnType<typeof setTimeout> | undefined;

  // 结束加载动画
  const onEnd = () => {
    clearTimeout(stopTimer);
    const processTime = performance.now() - startTime.value;
    if (processTime < minShowTime) {
      stopTimer = setTimeout(() => {
        spinning.value = false;
      }, minShowTime - processTime);
    } else {
      spinning.value = false;
    }
  };

  // 路由前置守卫
  const removeBeforeEach = router.beforeEach((to) => {
    if (to.meta.loaded || !enableLoading.value || to.meta.iframeSrc) {
      return true;
    }
    clearTimeout(stopTimer);
    startTime.value = performance.now();
    spinning.value = true;
    return true;
  });

  // 路由后置守卫
  const removeAfterEach = router.afterEach(() => {
    // 通用路由守卫会先把目标路由标记为 loaded，不能再使用
    // to.meta.loaded 判断是否收尾，否则首次进入页面时遮罩会永久保留。
    if (spinning.value) {
      onEnd();
    }
    return true;
  });

  const removeError = router.onError(() => {
    if (spinning.value) {
      onEnd();
    }
  });

  onScopeDispose(() => {
    clearTimeout(stopTimer);
    removeBeforeEach();
    removeAfterEach();
    removeError();
  });

  return { spinning };
}

export { useContentSpinner };
