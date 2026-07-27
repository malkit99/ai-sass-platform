<script setup>
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <v-container>
    <v-card class="pa-6">
      <p>Logged in as <strong>{{ auth.user?.email }}</strong>.</p>
      <p class="text-medium-emphasis">This is the starting point for the CRM dashboard. CRM and WhatsApp modules are coming in Phase 1.</p>
    </v-card>
  </v-container>
</template>
