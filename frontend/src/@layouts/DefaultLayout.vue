<script setup>
import { ref } from 'vue'
import { useDisplay } from 'vuetify'
import { useThemeStore } from '@/stores/theme/theme'
import NavDrawer from './components/NavDrawer.vue'
import AppBarNav from './components/AppBarNav.vue'
import AppFooter from './components/AppFooter.vue'
import ThemeSettingsDrawer from './components/ThemeSettingsDrawer.vue'

const { mobile } = useDisplay()

// Desktop: permanent drawer, `rail` toggles the mini/wide variant.
// Mobile: temporary overlay drawer, `drawerOpen` toggles shown/hidden — starts
// closed so it doesn't cover the page on first load.
const rail = ref(false)
const drawerOpen = ref(!mobile.value)
const settingsOpen = ref(false)
const themeStore = useThemeStore()

function toggleNav() {
  if (mobile.value) {
    drawerOpen.value = !drawerOpen.value
  } else {
    rail.value = !rail.value
  }
}
</script>

<template>
  <NavDrawer v-model="drawerOpen" :rail="!mobile && rail" />
  <AppBarNav @toggle-nav="toggleNav" @open-settings="settingsOpen = true" />

  <v-main>
    <div :class="{ 'content-boxed': themeStore.contentWidth === 'boxed' }">
      <router-view />
    </div>
  </v-main>

  <AppFooter />
  <ThemeSettingsDrawer v-model="settingsOpen" />
</template>

<style scoped>
.content-boxed {
  max-width: 1440px;
  margin: 0 auto;
}
</style>
