<script lang="ts" setup>
import type { CaptchaPoint, VbenFormSchema } from '@vben/common-ui';
import { computed, onMounted, ref } from 'vue';

import { AuthenticationLogin, PointSelectionCaptcha, z } from '@vben/common-ui';
import { $t } from '@vben/locales';
import { useClipboard } from '@vueuse/core';

import { Button, Input, message, Modal } from 'ant-design-vue';

import { getLoginCaptchaApi } from '#/api';
import { useAuthStore } from '#/store';

defineOptions({ name: 'Login' });

const authStore = useAuthStore();
const { copy } = useClipboard({ legacy: true });
const captchaRequired = ref(false);
const captchaPoints = ref<CaptchaPoint[]>([]);
const captchaKey = ref('');
const captchaImage = ref('');
const captchaHint = ref('');
const captchaUsername = ref('');
const showRememberMe = ref(true);
const twoFactorChallenge = ref<null | {
  challenge_token: string;
  qr_code?: string;
  secret?: string;
  setup_required?: boolean;
}>(null);
const twoFactorCode = ref('');

async function loadLoginSettings() {
  try {
    const response = await fetch('/api/admin/application', {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    const data = (await response.json()) as { login_remember_me?: boolean };
    showRememberMe.value = data.login_remember_me !== false;
  } catch {
    showRememberMe.value = true;
  }
}

async function loadCaptcha(username: string) {
  if (!username) return;
  const result = await getLoginCaptchaApi(username);
  captchaPoints.value = [];
  captchaKey.value = result.captcha_key;
  captchaImage.value = result.captcha_image;
  captchaHint.value = result.captcha_hint;
  captchaUsername.value = username;
}

function handleCaptchaClick(point: CaptchaPoint) {
  const normalizedPoint = {
    i: Number(point.i),
    t: Number(point.t),
    x: Math.round(Number(point.x)),
    y: Math.round(Number(point.y)),
  };

  if (normalizedPoint.i === 0) {
    captchaPoints.value = [normalizedPoint];
  } else if (normalizedPoint.i <= 2) {
    captchaPoints.value = [...captchaPoints.value, normalizedPoint];
  } else {
    captchaPoints.value = [];
    message.warning($t('authentication.captchaClickTip'));
    return;
  }

  if (captchaPoints.value.length === 3) {
    message.success($t('authentication.captchaCompleted'));
  }
}

async function handleLogin(params: Record<string, any>) {
  if (captchaRequired.value) {
    if (captchaUsername.value !== params.username) {
      await loadCaptcha(params.username);
      message.warning($t('authentication.captchaClickTip'));
      return;
    }
    if (captchaPoints.value.length !== 3) {
      message.warning($t('authentication.captchaClickTip'));
      return;
    }
  }

  try {
    const loginParams = { ...params };
    if (captchaRequired.value) {
      loginParams.captcha = captchaPoints.value;
      loginParams.captcha_key = captchaKey.value;
    }
    const result = await authStore.authLogin(loginParams);
    if (result.twoFactor?.challenge_token) {
      twoFactorChallenge.value = {
        challenge_token: result.twoFactor.challenge_token,
        qr_code: result.twoFactor.qr_code,
        secret: result.twoFactor.secret,
        setup_required: result.twoFactor.setup_required,
      };
      twoFactorCode.value = '';
    }
  } catch (error: any) {
    const responseData =
      error?.response?.data ?? error?.data ?? error?.response ?? error;
    if (responseData?.captcha_required) {
      captchaRequired.value = true;
      await loadCaptcha(params.username);
    }
  }
}

async function confirmTwoFactor() {
  if (!twoFactorChallenge.value || !/^\d{6}$/.test(twoFactorCode.value)) {
    message.warning($t('authentication.twoFactorCodeTip'));
    return;
  }

  await authStore.completeTwoFactorLogin({
    challenge_token: twoFactorChallenge.value.challenge_token,
    code: twoFactorCode.value,
  });
  twoFactorChallenge.value = null;
}

async function copyTwoFactorSecret() {
  const secret = twoFactorChallenge.value?.secret;
  if (!secret) return;
  await copy(secret);
  message.success($t('authentication.twoFactorSecretCopied'));
}

const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      component: 'VbenInput',
      componentProps: {
        placeholder: $t('authentication.usernameTip'),
      },
      fieldName: 'username',
      label: $t('authentication.username'),
      rules: z.string().min(1, { message: $t('authentication.usernameTip') }),
    },
    {
      component: 'VbenInputPassword',
      componentProps: {
        placeholder: $t('authentication.password'),
      },
      fieldName: 'password',
      label: $t('authentication.password'),
      rules: z.string().min(1, { message: $t('authentication.passwordTip') }),
    },
  ];
});

onMounted(loadLoginSettings);
</script>

<template>
  <AuthenticationLogin
    class="login-form"
    :form-schema="formSchema"
    :loading="authStore.loginLoading"
    :show-code-login="false"
    :show-forget-password="false"
    :show-qrcode-login="false"
    :show-remember-me="showRememberMe"
    :show-register="false"
    :show-third-party-login="false"
    @submit="handleLogin"
  >
    <template #after-form>
      <div v-if="captchaRequired" class="login-captcha mb-4 w-full">
        <PointSelectionCaptcha
          :captcha-image="captchaImage"
          :hint-text="captchaHint"
          :title="$t('authentication.captcha')"
          :height="130"
          :padding-x="10"
          :padding-y="10"
          :width="428"
          @click="handleCaptchaClick"
          @refresh="loadCaptcha(captchaUsername)"
        />
      </div>
    </template>
  </AuthenticationLogin>
  <Modal
    :closable="false"
    :open="Boolean(twoFactorChallenge)"
    :title="$t(
      twoFactorChallenge?.setup_required
        ? 'authentication.twoFactorSetupTitle'
        : 'authentication.twoFactorTitle',
    )"
  >
    <div class="two-factor-dialog">
      <template v-if="twoFactorChallenge?.setup_required">
        <p>{{ $t('authentication.twoFactorSetupDescription') }}</p>
        <img
          v-if="twoFactorChallenge.qr_code"
          :src="twoFactorChallenge.qr_code"
          :alt="$t('authentication.twoFactorQrCode')"
          class="two-factor-qr"
        />
        <div v-if="twoFactorChallenge.secret" class="two-factor-secret-row">
          <code class="two-factor-secret">
            {{ twoFactorChallenge.secret }}
          </code>
          <Button @click="copyTwoFactorSecret">
            {{ $t('authentication.twoFactorSecretCopy') }}
          </Button>
        </div>
      </template>
      <p v-else>{{ $t('authentication.twoFactorDescription') }}</p>
      <Input
        v-model:value="twoFactorCode"
        :maxlength="6"
        :placeholder="$t('authentication.twoFactorCodePlaceholder')"
        inputmode="numeric"
        @press-enter="confirmTwoFactor"
      />
    </div>
    <template #footer>
      <Button
        type="primary"
        :loading="authStore.loginLoading"
        @click="confirmTwoFactor"
      >
        {{ $t('authentication.twoFactorConfirm') }}
      </Button>
    </template>
  </Modal>
</template>

<style scoped>
.login-form {
  width: min(28rem, 100%);
}

.login-captcha :deep([role='region']) {
  max-width: 100%;
  border-color: hsl(var(--border));
  border-radius: 0.625rem;
  box-shadow: none;
}

.login-captcha :deep(#captcha-title) {
  font-size: 0.875rem;
  line-height: 2rem;
}

.login-captcha :deep(.h-10) {
  height: 2.25rem;
}
.two-factor-dialog {
  text-align: center;
}
.two-factor-dialog p {
  color: hsl(var(--muted-foreground));
}
.two-factor-qr {
  display: block;
  width: 220px;
  height: 220px;
  margin: 16px auto;
}
.two-factor-secret-row {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}
.two-factor-secret {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  padding: 8px;
  overflow-wrap: anywhere;
  border-radius: 6px;
  background: hsl(var(--muted));
}
</style>
