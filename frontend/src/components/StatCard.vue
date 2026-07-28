<script setup>
import { useThemeStore } from '@/stores/theme/theme'

defineProps({
  icon: { type: String, required: true },
  color: { type: String, default: 'primary' },
  value: { type: [String, Number], required: true },
  label: { type: String, required: true },
})

const themeStore = useThemeStore()
</script>

<template>
  <v-card
    :variant="themeStore.skin === 'border' ? 'outlined' : 'tonal'"
    :color="color"
    class="stat-card pa-4 flex-grow-1"
  >
    <div class="d-flex align-center ga-3">
      <v-avatar :color="color" variant="flat" size="44">
        <v-icon color="white">{{ icon }}</v-icon>
      </v-avatar>
      <div>
        <div class="text-h5 font-weight-bold">{{ value }}</div>
        <div class="text-caption font-weight-medium" style="letter-spacing: 0.5px">{{ label.toUpperCase() }}</div>
      </div>
    </div>
  </v-card>
</template>

<style scoped>
.stat-card {
  cursor: default;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

:global(.v-theme--dark) .stat-card:hover {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
}
</style>
