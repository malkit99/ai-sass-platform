<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth/auth'
import { useBrandingStore } from '@/stores/branding/branding'
import AppButton from '@/components/AppButton.vue'

const { t } = useI18n()

const schema = toTypedSchema(
  yup.object({
    email: yup.string().required(t('auth.emailRequired')).email(t('auth.emailInvalid')),
    password: yup.string().required(t('auth.passwordRequired')),
  }),
)

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: { email: 'admin@example.com', password: '' },
})

const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')

const error = ref('')
const loading = ref(false)
const stayInSigned = ref(false)

const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

const submit = handleSubmit(async (values) => {
  error.value = ''
  loading.value = true
  try {
    await auth.login(values.email, values.password)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message ?? t('auth.loginFailed')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="login-page">
    <div class="login-hero d-none d-md-flex">
      <img src="/images/login-image.jpg" alt="" class="hero-image" />
    </div>

    <div class="login-form-panel">
      <div class="login-form-inner">
        <v-avatar v-if="branding.logoUrl" :image="branding.logoUrl" size="40" class="mb-4" />
        <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.welcomeBack') }}</h1>
        <p class="text-body-1 text-medium-emphasis mb-8">{{ t('auth.signInSubtitle', { product: branding.productName }) }}</p>

        <v-alert v-if="error" type="error" density="compact" class="mb-4">{{ error }}</v-alert>

        <v-form @submit.prevent="submit">
          <div class="text-caption font-weight-medium text-medium-emphasis mb-1" style="letter-spacing: 0.5px">
            {{ t('auth.emailOrUsername') }}
          </div>
          <v-text-field
            v-model="email"
            v-bind="emailAttrs"
            variant="solo-filled"
            flat
            type="email"
            placeholder="you@example.com"
            prepend-inner-icon="mdi-email-outline"
            :error-messages="errors.email"
            class="mb-4"
          />

          <div class="d-flex align-center justify-space-between mb-1">
            <span class="text-caption font-weight-medium text-medium-emphasis" style="letter-spacing: 0.5px">{{ t('auth.password') }}</span>
            <a href="#" class="text-caption text-primary text-decoration-none">{{ t('auth.forgotPassword') }}</a>
          </div>
          <v-text-field
            v-model="password"
            v-bind="passwordAttrs"
            variant="solo-filled"
            flat
            type="password"
            placeholder="Enter your password"
            prepend-inner-icon="mdi-lock-outline"
            :error-messages="errors.password"
            class="mb-2"
          />

          <v-checkbox v-model="stayInSigned" :label="t('auth.staySignedIn')" density="compact" hide-details class="mb-4" />

          <AppButton type="submit" block size="large" class="login-submit-btn" :loading="loading">
            {{ t('auth.signIn') }}
          </AppButton>
        </v-form>

        <p class="text-center text-body-2 text-medium-emphasis mt-8">
          {{ t('auth.noAccount') }} <a href="#" class="text-primary font-weight-medium text-decoration-none">{{ t('auth.registerNow') }}</a>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100%;
  display: flex;
}

.login-hero {
  position: relative;
  flex: 1 1 50%;
  background: #eef2ff;
  align-items: center;
  justify-content: center;
  padding: 48px;
}

.hero-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.login-form-panel {
  flex: 1 1 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
}

.login-form-inner {
  width: 100%;
  max-width: 420px;
}

.login-submit-btn {
  background: linear-gradient(90deg, #5b4fdb 0%, #3aa0e8 100%) !important;
  color: white !important;
}

:global(.v-theme--dark) .login-hero {
  background: #1a1d3a;
}
</style>
