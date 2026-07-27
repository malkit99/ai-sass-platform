<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-app-bar color="primary">
    <v-app-bar-title>Dashboard</v-app-bar-title>
    <v-spacer />
    <span class="mr-4">{{ auth.user?.name }}</span>
    <v-btn variant="text" @click="logout">Logout</v-btn>
  </v-app-bar>
  <v-container>
    <v-card class="pa-6">
      <p>Logged in as <strong>{{ auth.user?.email }}</strong>.</p>
      <p class="text-medium-emphasis">This is the starting point for the CRM dashboard.</p>
    </v-card>
  </v-container>
</template>
