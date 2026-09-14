const FALLBACK_PAGE_SIZE = 20;
const COMMON_PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

let defaultPageSize = FALLBACK_PAGE_SIZE;

function normalizePageSize(value: unknown) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed >= 10 && parsed <= 100
    ? parsed
    : FALLBACK_PAGE_SIZE;
}

export function setAdminDefaultPageSize(value: unknown) {
  defaultPageSize = normalizePageSize(value);
}

export function createAdminPagination() {
  const pageSize = defaultPageSize;
  return {
    current: 1,
    pageSize,
    pageSizeOptions: [...new Set([...COMMON_PAGE_SIZE_OPTIONS, pageSize])].sort(
      (left, right) => left - right,
    ),
    showSizeChanger: true,
    total: 0,
  };
}
