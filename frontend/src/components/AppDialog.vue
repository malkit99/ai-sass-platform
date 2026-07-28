<script setup>
defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: '' },
  maxWidth: { type: [String, Number], default: 480 },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <v-dialog :model-value="modelValue" :max-width="maxWidth" @update:model-value="$emit('update:modelValue', $event)">
    <v-card>
      <div v-if="$slots.header" class="position-relative">
        <slot name="header" />
        <v-btn
          icon="mdi-close" variant="text" density="comfortable" color="white"
          class="position-absolute" style="top: 8px; right: 8px"
          @click="$emit('update:modelValue', false)"
        />
      </div>
      <v-card-title v-else class="d-flex align-center justify-space-between pa-4">
        <span class="text-h6">{{ title }}</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="$emit('update:modelValue', false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <slot />
      </v-card-text>
      <template v-if="$slots.actions">
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <slot name="actions" />
        </v-card-actions>
      </template>
    </v-card>
  </v-dialog>
</template>
