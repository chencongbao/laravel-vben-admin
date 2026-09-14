<script lang="ts" setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { IconifyIcon } from '@vben/icons';
import { Select, Switch } from 'ant-design-vue';

defineOptions({ name: 'AdminAutoRefresh' });

const props = withDefaults(defineProps<{
  defaultSeconds?: number;
  loading?: boolean;
  storageKey: string;
}>(), {
  defaultSeconds: 60,
  loading: false,
});

const emit = defineEmits<{ refresh: [] }>();
const intervals = [15, 30, 60, 120, 300].map((value) => ({ label: `${value} 秒`, value }));
const enabledKey = `vben:auto-refresh:${props.storageKey}:enabled`;
const secondsKey = `vben:auto-refresh:${props.storageKey}:seconds`;
const enabled = ref(localStorage.getItem(enabledKey) === '1');
const seconds = ref(Number(localStorage.getItem(secondsKey)) || props.defaultSeconds);
const remaining = ref(seconds.value);
let timer: ReturnType<typeof setInterval> | undefined;

const status = computed(() => enabled.value ? `${remaining.value}s` : '');

function stop() {
  if (timer) clearInterval(timer);
  timer = undefined;
}

function start() {
  stop();
  remaining.value = seconds.value;
  if (!enabled.value) return;
  timer = setInterval(() => {
    if (document.hidden || props.loading) return;
    remaining.value -= 1;
    if (remaining.value > 0) return;
    remaining.value = seconds.value;
    emit('refresh');
  }, 1000);
}

watch(enabled, (value) => {
  localStorage.setItem(enabledKey, value ? '1' : '0');
  start();
});
watch(seconds, (value) => {
  localStorage.setItem(secondsKey, String(value));
  start();
});
onBeforeUnmount(stop);
start();
</script>

<template>
  <div class="admin-auto-refresh">
    <IconifyIcon icon="lucide:clock-3" />
    <span>自动刷新</span>
    <Switch v-model:checked="enabled" size="small" />
    <Select v-if="enabled" v-model:value="seconds" :options="intervals" size="small" />
    <span v-if="enabled" class="admin-auto-refresh__status">{{ status }}</span>
  </div>
</template>

<style scoped>
.admin-auto-refresh { display: inline-flex; height: 32px; align-items: center; gap: 6px; padding: 0 8px; border: 1px solid hsl(var(--border)); background: hsl(var(--background)); white-space: nowrap; }
.admin-auto-refresh__status { min-width: 28px; color: hsl(var(--muted-foreground)); text-align: right; font-variant-numeric: tabular-nums; }
</style>
