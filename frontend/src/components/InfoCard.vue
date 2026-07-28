<script setup>
defineProps({
  icon: { type: String, default: null },
  color: { type: String, default: 'primary' },
  title: { type: String, default: null },
  subtitle: { type: String, default: null },
  variant: { type: String, default: 'elevated' }, // elevated | outlined | tonal
})
</script>

<template>
  <v-card class="pa-4" :variant="variant" :color="variant === 'tonal' ? color : undefined">
    <div v-if="icon || title" class="d-flex align-center ga-3 mb-2">
      <v-avatar v-if="icon" :color="color" rounded="lg" size="40">
        <v-icon color="white">{{ icon }}</v-icon>
      </v-avatar>
      <div>
        <div v-if="title" class="text-subtitle-1 font-weight-bold">{{ title }}</div>
        <div v-if="subtitle" class="text-caption text-medium-emphasis">{{ subtitle }}</div>
      </div>
    </div>
    <slot />
  </v-card>
</template>
