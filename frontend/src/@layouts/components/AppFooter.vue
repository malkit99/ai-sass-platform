<script setup>
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding/branding'

const branding = useBrandingStore()
const { t } = useI18n()
</script>

<template>
  <v-footer app border height="40">
    <span class="text-caption text-medium-emphasis">
      &copy; {{ new Date().getFullYear() }} {{ branding.productName }}. {{ t('footer.rights') }}
    </span>
  </v-footer>
</template>
