<script setup>
import { useAlertStore } from '@/stores/alert/alert'

const alertStore = useAlertStore()

const icons = {
  success: 'mdi-check-circle-outline',
  error: 'mdi-alert-circle-outline',
  warning: 'mdi-alert-outline',
  info: 'mdi-information-outline',
}
</script>

<template>
  <v-snackbar
    v-model="alertStore.snackbar.show"
    :color="alertStore.snackbar.color"
    :timeout="alertStore.snackbar.timeout"
    location="top right"
    variant="flat"
    rounded="lg"
  >
    <div class="d-flex align-center ga-2">
      <v-icon :icon="icons[alertStore.snackbar.color] ?? icons.info" />
      <span>{{ alertStore.snackbar.message }}</span>
    </div>

    <template #actions>
      <v-btn icon="mdi-close" variant="text" density="comfortable" @click="alertStore.close" />
    </template>
  </v-snackbar>
</template>
