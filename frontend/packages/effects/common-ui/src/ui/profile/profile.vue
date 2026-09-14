<script setup lang="ts">
import type { Props } from './types';

import { preferences } from '@vben-core/preferences';
import {
  Card,
  Separator,
  Tabs,
  TabsList,
  TabsTrigger,
  VbenAvatar,
} from '@vben-core/shadcn-ui';

import { Page } from '../../components';

defineOptions({
  name: 'ProfileUI',
});

withDefaults(defineProps<Props>(), {
  avatarActionLabel: 'Edit avatar',
  title: '关于项目',
  tabs: () => [],
});

const tabsValue = defineModel<string>('modelValue');
const emit = defineEmits<{ avatarClick: [] }>();
</script>
<template>
  <Page auto-content-height>
    <div class="flex size-full flex-col gap-4 lg:flex-row">
      <Card class="w-full flex-none overflow-hidden border lg:w-64">
        <div class="flex-col-center gap-3 px-6 py-8">
          <button
            :aria-label="avatarActionLabel"
            class="group relative rounded-full"
            type="button"
            @click="emit('avatarClick')"
          >
            <VbenAvatar
              :src="userInfo?.avatar ?? preferences.app.defaultAvatar"
              class="size-24 rounded-full transition group-hover:brightness-90"
            />
            <span
              class="absolute inset-x-1 bottom-1 rounded-full bg-black/60 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100"
            >
              {{ avatarActionLabel }}
            </span>
          </button>
          <span class="text-lg font-semibold">
            {{ userInfo?.realName ?? '' }}
          </span>
          <span class="text-sm text-foreground/80">
            {{ userInfo?.username ?? '' }}
          </span>
        </div>
        <Separator />
        <Tabs v-model="tabsValue" orientation="vertical" class="p-4">
          <TabsList
            class="grid h-auto w-full grid-cols-1 gap-1 bg-transparent p-0"
          >
            <TabsTrigger
              v-for="tab in tabs"
              :key="tab.value"
              :value="tab.value"
              class="h-11 justify-start rounded-lg px-4 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground"
            >
              {{ tab.label }}
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </Card>
      <Card class="min-w-0 flex-auto border p-5 sm:p-8">
        <slot name="content"></slot>
      </Card>
    </div>
  </Page>
</template>
