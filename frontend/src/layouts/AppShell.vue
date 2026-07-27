<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

// Module list per .claude/build-plan/01-feature-inventory.md — most are
// placeholders until their phase in 06-roadmap.md is built. Only Dashboard
// is real right now.
const modules = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard', route: 'dashboard', enabled: true },
  { title: 'Whatsapp', icon: 'mdi-whatsapp', enabled: false },
  { title: 'CallerDesk', icon: 'mdi-phone', enabled: false },
  { title: 'Email Marketing', icon: 'mdi-email-outline', enabled: false },
  { title: 'Live Chat', icon: 'mdi-chat-outline', enabled: false },
  { title: 'Support System', icon: 'mdi-headset', enabled: false },
  { title: 'Commerce', icon: 'mdi-storefront-outline', enabled: false },
  { title: 'Social Media', icon: 'mdi-share-variant-outline', enabled: false },
  { title: 'Meta Cloud API', icon: 'mdi-whatsapp', enabled: false },
  { title: 'CRM', icon: 'mdi-view-column-outline', enabled: false },
  { title: 'Omnichannel', icon: 'mdi-forum-outline', enabled: false },
  { title: 'Number Warmer', icon: 'mdi-fire', enabled: false },
  { title: 'CRM Pipelines', icon: 'mdi-view-column', enabled: false },
  { title: 'AI Appointments', icon: 'mdi-calendar-clock', enabled: false },
  { title: 'Chat Agents', icon: 'mdi-account-voice', enabled: false },
  { title: 'Social Lead Master', icon: 'mdi-magnet', enabled: false },
  { title: 'Account Manager', icon: 'mdi-account-multiple-outline', enabled: false },
  { title: 'File Manager', icon: 'mdi-folder-outline', enabled: false },
  { title: 'Tools', icon: 'mdi-tools', enabled: false },
  { title: 'Invoices', icon: 'mdi-receipt-text-outline', enabled: false },
  { title: 'Google Sheets', icon: 'mdi-google-spreadsheet', enabled: false },
  { title: 'WooCommerce', icon: 'mdi-wordpress', enabled: false },
  { title: 'AI Chatbot', icon: 'mdi-robot-outline', enabled: false },
  { title: 'API & Automation', icon: 'mdi-lightning-bolt-outline', enabled: false },
]

const drawer = ref(true)
const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-navigation-drawer v-model="drawer">
    <v-list-item :title="branding.productName" class="font-weight-bold py-4" />
    <v-divider />
    <v-list density="compact" nav>
      <v-list-item
        v-for="item in modules"
        :key="item.title"
        :prepend-icon="item.icon"
        :title="item.title"
        :to="item.enabled ? { name: item.route } : undefined"
        :disabled="!item.enabled"
        :subtitle="item.enabled ? undefined : 'Coming soon'"
      />
    </v-list>
  </v-navigation-drawer>

  <v-app-bar>
    <v-app-bar-nav-icon @click="drawer = !drawer" />
    <v-app-bar-title>{{ branding.productName }}</v-app-bar-title>
    <v-spacer />
    <span class="mr-4">{{ auth.user?.name }}</span>
    <v-btn variant="text" @click="logout">Logout</v-btn>
  </v-app-bar>

  <v-main>
    <router-view />
  </v-main>
</template>
