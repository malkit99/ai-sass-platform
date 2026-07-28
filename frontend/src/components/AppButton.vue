<script setup>
defineProps({
  color: { type: String, default: 'primary' },
  variant: { type: String, default: 'flat' },
  block: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  icon: { type: String, default: null },
  prependIcon: { type: String, default: null },
  appendIcon: { type: String, default: null },
  size: { type: String, default: 'default' },
  type: { type: String, default: 'button' },
})
defineEmits(['click'])
</script>

<template>
  <v-btn
    :color="color"
    :variant="variant"
    :block="block"
    :loading="loading"
    :disabled="disabled"
    :icon="icon"
    :prepend-icon="prependIcon"
    :append-icon="appendIcon"
    :size="size"
    :type="type"
    @click="$emit('click', $event)"
  >
    <slot />
  </v-btn>
</template>
