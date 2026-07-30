<script setup>
import { ref, watch } from 'vue'
import AppDialog from '@/components/AppDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
})

const emit = defineEmits(['update:modelValue', 'create'])

const name = ref('')
const keywords = ref('')
const creating = ref(false)

watch(() => props.modelValue, (open) => {
  if (open) {
    name.value = ''
    keywords.value = ''
  }
})

async function submit() {
  creating.value = true
  try {
    await emit('create', {
      name: name.value.trim() || 'My WhatsApp Bot',
      triggerKeywords: keywords.value.split(',').map((k) => k.trim()).filter(Boolean),
    })
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" title="Name your bot" max-width="460" @update:model-value="$emit('update:modelValue', $event)">
    <div class="text-body-2 text-medium-emphasis mb-4">Give your bot a name. You can change it later.</div>

    <div class="text-caption text-medium-emphasis font-weight-bold mb-1">BOT NAME</div>
    <v-text-field v-model="name" placeholder="My WhatsApp Bot" variant="outlined" density="comfortable" class="mb-1" />
    <div class="text-caption text-medium-emphasis mb-4">This will be visible only to you</div>

    <div class="text-caption text-medium-emphasis font-weight-bold mb-1">TRIGGER KEYWORDS (comma separated)</div>
    <v-text-field v-model="keywords" placeholder="hi, hello, start" variant="outlined" density="comfortable" class="mb-1" />
    <div class="text-caption text-medium-emphasis">Bot activates when user sends any of these keywords</div>

    <template #actions>
      <AppButton variant="outlined" @click="$emit('update:modelValue', false)">Cancel</AppButton>
      <AppButton :loading="creating" prepend-icon="mdi-rocket-launch-outline" @click="submit">Create Bot</AppButton>
    </template>
  </AppDialog>
</template>
