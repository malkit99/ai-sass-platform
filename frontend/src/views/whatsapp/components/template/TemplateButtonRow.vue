<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Object, required: true }, // { type: 'reply'|'call'|'url', label: string, value: string }
})

const emit = defineEmits(['update:modelValue', 'remove'])

// Matches Meta's real WhatsApp template button types: quick-reply ("Reply"),
// a phone number that opens the dialer ("Call"), and a CTA link ("URL") —
// see screenshot 70's per-card button row.
const typeOptions = [
  { title: 'Reply', value: 'reply' },
  { title: 'Call', value: 'call' },
  { title: 'URL', value: 'url' },
]

const valuePlaceholder = computed(() => ({
  reply: 'Reply ID',
  call: 'e.g. 919876543210',
  url: 'https://…',
}[props.modelValue.type] ?? 'Val/ID'))

function update(field, val) {
  emit('update:modelValue', { ...props.modelValue, [field]: val })
}
</script>

<template>
  <div class="d-flex ga-2 mb-2 align-start">
    <v-select
      :model-value="modelValue.type" :items="typeOptions" item-title="title" item-value="value"
      density="compact" variant="outlined" hide-details style="max-width: 110px"
      @update:model-value="update('type', $event)"
    />
    <v-text-field
      :model-value="modelValue.label" placeholder="Label (max 20)" density="compact" variant="outlined" hide-details
      maxlength="20" @update:model-value="update('label', $event)"
    />
    <v-text-field
      :model-value="modelValue.value" :placeholder="valuePlaceholder" density="compact" variant="outlined" hide-details
      @update:model-value="update('value', $event)"
    />
    <v-btn icon="mdi-close-circle" size="small" variant="text" color="error" @click="$emit('remove')" />
  </div>
</template>
