<script setup>
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth/auth'

const auth = useAuthStore()
const { t } = useI18n()
</script>

<template>
  <v-container fluid>
    <v-card class="pa-6">
      <p>{{ t('dashboard.loggedInAs') }} <strong>{{ auth.user?.email }}</strong>.</p>
      <p class="text-medium-emphasis">{{ t('dashboard.intro') }}</p>
    </v-card>
  </v-container>
</template>
