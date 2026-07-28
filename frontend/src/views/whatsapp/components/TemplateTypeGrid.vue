<script setup>
import { templateTypes } from '@core/utils/whatsappTemplateTypes'

const props = defineProps({
  templates: { type: Array, default: () => [] },
})

defineEmits(['select'])

function countFor(type) {
  return props.templates.filter((t) => t.type === type).length
}
</script>

<template>
  <v-row>
    <v-col v-for="t in templateTypes" :key="t.value" cols="12" sm="6" md="4">
      <v-card class="pa-4 template-type-card" @click="$emit('select', t.value)">
        <div class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="tonal"><v-icon :icon="t.icon" /></v-avatar>
          <div class="flex-grow-1">
            <div class="text-body-1 font-weight-medium">{{ t.label }}</div>
            <div class="text-caption text-medium-emphasis">{{ countFor(t.value) }} template(s)</div>
          </div>
          <v-icon icon="mdi-chevron-right" color="medium-emphasis" />
        </div>
      </v-card>
    </v-col>
  </v-row>
</template>

<style scoped>
.template-type-card {
  cursor: pointer;
  transition: box-shadow 0.15s ease;
}

.template-type-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
