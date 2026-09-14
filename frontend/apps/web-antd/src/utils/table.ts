import { nextTick, onActivated, onBeforeUnmount, onMounted, ref } from 'vue';

const MIN_TABLE_BODY_HEIGHT = 240;
const TABLE_FOOTER_SPACE = 80;

/** 根据数据区在当前视口中的实际位置计算剩余高度，保留分页和底部间距。 */
export function useAdminTableScrollY() {
  const tableRef = ref<any>();
  const tableScrollY = ref(MIN_TABLE_BODY_HEIGHT);
  let resizeObserver: ResizeObserver | undefined;
  let mutationObserver: MutationObserver | undefined;
  let frameId: number | undefined;

  const updateTableScrollY = () => {
    if (frameId !== undefined) cancelAnimationFrame(frameId);
    frameId = requestAnimationFrame(() => {
      const root = tableRef.value?.$el ?? tableRef.value;
      const body = root?.querySelector?.('.ant-table-body') as HTMLElement | null;
      if (!body) return;
      tableScrollY.value = Math.max(
        MIN_TABLE_BODY_HEIGHT,
        Math.floor(window.innerHeight - body.getBoundingClientRect().top - TABLE_FOOTER_SPACE),
      );
      requestAnimationFrame(() => {
        root.classList.toggle(
          'admin-data-table--scrollable-y',
          body.scrollHeight > body.clientHeight + 1,
        );
      });
    });
  };

  const setTableRef = (value: any) => {
    tableRef.value = value;
    if (value) updateTableScrollY();
  };

  onMounted(async () => {
    await nextTick();
    updateTableScrollY();
    window.addEventListener('resize', updateTableScrollY);
    const root = tableRef.value?.$el ?? tableRef.value;
    if (root?.parentElement && typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(updateTableScrollY);
      resizeObserver.observe(root.parentElement);
      const body = root.querySelector?.('.ant-table-body') as HTMLElement | null;
      if (body) resizeObserver.observe(body);
    }
    const body = root?.querySelector?.('.ant-table-body') as HTMLElement | null;
    if (body && typeof MutationObserver !== 'undefined') {
      mutationObserver = new MutationObserver(updateTableScrollY);
      mutationObserver.observe(body, { childList: true, subtree: true });
    }
  });

  onActivated(updateTableScrollY);
  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateTableScrollY);
    resizeObserver?.disconnect();
    mutationObserver?.disconnect();
    if (frameId !== undefined) cancelAnimationFrame(frameId);
  });

  return { setTableRef, tableScrollY, updateTableScrollY };
}
