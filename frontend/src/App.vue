<script setup>
import { watchEffect } from 'vue'
import { useTheme } from 'vuetify'
import { useI18n } from 'vue-i18n'
import vuetify from '@core/plugins/vuetify'
import { useThemeStore } from '@/stores/theme/theme'
import { useBrandingStore } from '@/stores/branding/branding'
import { useLocaleStore } from '@/stores/locale/locale'

const vuetifyTheme = useTheme()
const themeStore = useThemeStore()
const branding = useBrandingStore()
const localeStore = useLocaleStore()
const { locale } = useI18n()

watchEffect(() => {
  locale.value = localeStore.current
})

themeStore.watchSystemPreference()

watchEffect(() => {
  vuetifyTheme.global.name.value = themeStore.effectiveTheme
})

watchEffect(() => {
  const primary = themeStore.themeColorCustomized ? themeStore.themeColor : branding.primaryColor
  vuetifyTheme.themes.value.light.colors.primary = primary
  vuetifyTheme.themes.value.dark.colors.primary = primary
})

watchEffect(() => {
  vuetify.defaults.value.VCard = {
    ...vuetify.defaults.value.VCard,
    variant: themeStore.skin === 'border' ? 'outlined' : undefined,
  }
})
</script>

<template>
  <v-app>
    <router-view />
  </v-app>
</template>
