const BEIJING_TIME_ZONE = 'Asia/Shanghai';

function formatBeijingDateTime(value?: null | string) {
  if (!value) return '—';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  const parts = new Intl.DateTimeFormat('zh-CN', {
    day: '2-digit',
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    month: '2-digit',
    second: '2-digit',
    timeZone: BEIJING_TIME_ZONE,
    year: 'numeric',
  }).formatToParts(date);
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

  return `${values.year}-${values.month}-${values.day} ${values.hour}:${values.minute}:${values.second}`;
}

function isDateTimeField(field?: string) {
  return Boolean(field && (field.endsWith('_at') || field.endsWith('_time')));
}

export { BEIJING_TIME_ZONE, formatBeijingDateTime, isDateTimeField };
