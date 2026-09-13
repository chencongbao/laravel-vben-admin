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
  title: '关于项目',
  tabs: () => [],
});

const tabsValue = defineModel<string>('modelValue');
</script>
<template>
  <Page auto-content-height>
    <div class="flex size-full flex-col gap-4 lg:flex-row">
      <Card class="w-full flex-none overflow-hidden border lg:w-64">
        <div class="flex-col-center gap-3 px-6 py-8">
          <VbenAvatar
            :src="userInfo?.avatar ?? preferences.app.defaultAvatar"
            class="size-24 ring-4 ring-primary/10"
          />
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
