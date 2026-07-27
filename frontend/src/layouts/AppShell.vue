<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

// Module list per .claude/build-plan/01-feature-inventory.md — most are
// placeholders until their phase in 06-roadmap.md is built. Only Dashboard
// is real right now. Icon colors loosely follow the reference app's
// multicolor sidebar style (see ai_sass_screenshot/dashboar navigation.png).
const modules = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard', color: '#1976D2', route: 'dashboard', enabled: true },
  { title: 'Whatsapp', icon: 'mdi-whatsapp', color: '#25D366', enabled: false },
  { title: 'CallerDesk', icon: 'mdi-phone', color: '#5C6BC0', enabled: false },
  { title: 'Email Marketing', icon: 'mdi-email-outline', color: '#EF5350', enabled: false },
  { title: 'Live Chat', icon: 'mdi-chat-outline', color: '#42A5F5', enabled: false },
  { title: 'Support System', icon: 'mdi-headset', color: '#8E24AA', enabled: false },
  { title: 'Commerce', icon: 'mdi-storefront-outline', color: '#43A047', enabled: false },
  { title: 'Social Media', icon: 'mdi-share-variant-outline', color: '#8E24AA', enabled: false },
  { title: 'Meta Cloud API', icon: 'mdi-whatsapp', color: '#26A69A', enabled: false },
  { title: 'CRM', icon: 'mdi-view-column-outline', color: '#1E88E5', enabled: false },
  { title: 'Omnichannel', icon: 'mdi-forum-outline', color: '#7E57C2', enabled: false },
  { title: 'Number Warmer', icon: 'mdi-fire', color: '#FB8C00', enabled: false },
  { title: 'CRM Pipelines', icon: 'mdi-view-column', color: '#1E88E5', enabled: false },
  { title: 'AI Appointments', icon: 'mdi-calendar-clock', color: '#00897B', enabled: false },
  { title: 'Chat Agents', icon: 'mdi-account-voice', color: '#5E35B1', enabled: false },
  { title: 'Social Lead Master', icon: 'mdi-magnet', color: '#D81B60', enabled: false },
  { title: 'Account Manager', icon: 'mdi-account-multiple-outline', color: '#3949AB', enabled: false },
  { title: 'File Manager', icon: 'mdi-folder-outline', color: '#6D4C41', enabled: false },
  { title: 'Tools', icon: 'mdi-tools', color: '#546E7A', enabled: false },
  { title: 'Invoices', icon: 'mdi-receipt-text-outline', color: '#E53935', enabled: false },
  { title: 'Google Sheets', icon: 'mdi-google-spreadsheet', color: '#2E7D32', enabled: false },
  { title: 'WooCommerce', icon: 'mdi-wordpress', color: '#7B1FA2', enabled: false },
  { title: 'AI Chatbot', icon: 'mdi-robot-outline', color: '#D81B60', enabled: false },
  { title: 'API & Automation', icon: 'mdi-lightning-bolt-outline', color: '#FB8C00', enabled: false },
]

const rail = ref(false)
const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-navigation-drawer :rail="rail" permanent>
    <template #prepend>
      <v-list-item class="py-4">
        <template #prepend>
          <v-avatar v-if="branding.logoUrl" :image="branding.logoUrl" size="28" />
        </template>
        <v-list-item-title class="font-weight-bold">{{ branding.productName }}</v-list-item-title>
      </v-list-item>
      <v-divider />
    </template>

    <v-list density="compact" nav active-class="bg-primary-lighten-5">
      <v-list-item
        v-for="item in modules"
        :key="item.title"
        :prepend-icon="item.icon"
        :title="item.title"
        :to="item.enabled ? { name: item.route } : undefined"
        :disabled="!item.enabled"
        :subtitle="item.enabled ? undefined : 'Coming soon'"
        :base-color="item.enabled ? item.color : undefined"
      />
    </v-list>
  </v-navigation-drawer>

  <v-app-bar>
    <v-app-bar-nav-icon @click="rail = !rail" />
    <v-app-bar-title>{{ branding.productName }}</v-app-bar-title>
    <v-spacer />
    <span class="mr-4">{{ auth.user?.name }}</span>
    <v-btn variant="text" @click="logout">Logout</v-btn>
  </v-app-bar>

  <v-main>
    <router-view />
  </v-main>
</template>

<style scoped>
:deep(.v-navigation-drawer__content) {
  scrollbar-width: thin;
}

:deep(.v-navigation-drawer__content)::-webkit-scrollbar {
  width: 6px;
}

:deep(.v-navigation-drawer__content)::-webkit-scrollbar-thumb {
  background-color: rgba(0, 0, 0, 0.2);
  border-radius: 3px;
}

:deep(.v-navigation-drawer__content)::-webkit-scrollbar-track {
  background: transparent;
}

:deep(.v-list-item__prepend) {
  margin-inline-end: 8px !important;
}

:deep(.v-list-item__prepend .v-list-item__spacer) {
  width: 8px !important;
}
</style>

