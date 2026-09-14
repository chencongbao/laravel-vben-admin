<script lang="ts" setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { useRefresh } from '@vben/hooks';
import { IconifyIcon } from '@vben/icons';
import { Select, Switch } from 'ant-design-vue';

import { $t } from '#/locales';

defineOptions({ name: 'AdminAutoRefresh' });

const props = withDefaults(defineProps<{
  defaultSeconds?: number;
  loading?: boolean;
  storageKey: string;
}>(), {
  defaultSeconds: 60,
  loading: false,
});

const intervals = computed(() => [15, 30, 60, 120, 300].map((value) => ({
  label: $t('common.time.seconds', { count: value }),
  value,
})));
const enabledKey = `vben:auto-refresh:${props.storageKey}:enabled`;
const secondsKey = `vben:auto-refresh:${props.storageKey}:seconds`;
const enabled = ref(localStorage.getItem(enabledKey) === '1');
const seconds = ref(Number(localStorage.getItem(secondsKey)) || props.defaultSeconds);
const remaining = ref(seconds.value);
const refreshing = ref(false);
const { refresh: refreshPage } = useRefresh();
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
  timer = setInterval(async () => {
    if (document.hidden || props.loading || refreshing.value) return;
    remaining.value -= 1;
    if (remaining.value > 0) return;
    remaining.value = seconds.value;
    refreshing.value = true;
    try {
      await refreshPage();
    } finally {
      refreshing.value = false;
    }
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
    <span>{{ $t('common.actions.autoRefresh') }}</span>
    <Switch v-model:checked="enabled" size="small" />
    <Select v-if="enabled" v-model:value="seconds" :options="intervals" size="small" />
    <span v-if="enabled" class="admin-auto-refresh__status">{{ status }}</span>
  </div>
</template>
