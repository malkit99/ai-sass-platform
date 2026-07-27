<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('admin@example.com')
const password = ref('')
const error = ref('')
const loading = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card elevation="4">
          <v-card-title>Sign in</v-card-title>
          <v-card-text>
            <v-alert v-if="error" type="error" density="compact" class="mb-4">{{ error }}</v-alert>
            <v-form @submit.prevent="submit">
              <v-text-field v-model="email" label="Email" type="email" required />
              <v-text-field v-model="password" label="Password" type="password" required />
              <v-btn type="submit" color="primary" block :loading="loading">Sign in</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
