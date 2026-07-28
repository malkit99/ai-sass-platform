<script setup>
import AppButton from '@/components/AppButton.vue'

defineProps({
  badge: { type: String, required: true },
  editing: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

defineEmits(['back', 'save'])
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-6">
      <v-btn icon="mdi-arrow-left" variant="tonal" @click="$emit('back')" />
      <div>
        <div class="d-flex align-center ga-2">
          <h2 class="text-h5">{{ editing ? 'Edit Template' : 'Create Template' }}</h2>
          <v-chip color="success" size="small" variant="tonal">{{ badge }}</v-chip>
        </div>
        <div class="text-caption text-medium-emphasis">Design your reusable message asset.</div>
      </div>
      <v-spacer />
      <AppButton variant="outlined" :disabled="saving" @click="$emit('back')">Discard</AppButton>
      <AppButton variant="flat" color="success" prepend-icon="mdi-content-save-outline" :loading="saving" @click="$emit('save')">
        Save Template
      </AppButton>
    </div>

    <v-row>
      <v-col cols="12" md="7"><slot /></v-col>
      <v-col cols="12" md="5"><slot name="preview" /></v-col>
    </v-row>
  </div>
</template>
