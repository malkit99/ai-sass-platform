<script setup>
import { computed, ref, watch } from 'vue'
import { templateTypeMeta } from '@core/utils/whatsappTemplateTypes'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import TemplatePhonePreview from './template/TemplatePhonePreview.vue'

const props = defineProps({
  type: { type: String, required: true },
  templates: { type: Array, default: () => [] },
})

defineEmits(['back', 'create', 'edit', 'delete'])

const PER_PAGE = 6

const meta = computed(() => templateTypeMeta(props.type))
const filtered = computed(() => props.templates.filter((t) => t.type === props.type))

const page = ref(1)
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const paged = computed(() => {
  const start = (page.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

// Reset to page 1 whenever the underlying list shrinks below the current page
// (e.g. after a delete) or the type changes.
watch([filtered, () => props.type], () => {
  if (page.value > pageCount.value) page.value = 1
})

const previewing = ref(null)

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$emit('back')" />
      <v-avatar color="primary" variant="tonal"><v-icon :icon="meta.icon" /></v-avatar>
      <div class="flex-grow-1">
        <h2 class="text-h5">{{ meta.label }}</h2>
        <div class="text-caption text-medium-emphasis">Create and manage reusable {{ meta.label.toLowerCase() }} templates</div>
      </div>
      <AppButton prepend-icon="mdi-plus" @click="$emit('create')">Create Template</AppButton>
    </div>

    <template v-if="filtered.length">
      <v-row>
        <v-col v-for="t in paged" :key="t.id" cols="12" sm="6" md="4">
          <v-card class="pa-0 template-card">
            <div class="pa-4">
              <div class="d-flex align-center ga-3 mb-2">
                <v-avatar color="success" variant="tonal"><v-icon :icon="meta.icon" /></v-avatar>
                <div class="text-body-1 font-weight-medium text-truncate">{{ t.name }}</div>
              </div>
              <v-chip color="success" size="small" variant="tonal">{{ meta.badge }}</v-chip>
            </div>

            <div class="template-preview-wrap pa-4">
              <TemplatePhonePreview :template="t" compact />
            </div>

            <div class="d-flex align-center justify-space-between pa-2 px-4">
              <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
                <v-icon icon="mdi-clock-outline" size="14" />{{ formatDate(t.created_at) }}
              </div>
              <div>
                <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="previewing = t" />
                <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="$emit('edit', t)" />
                <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', t)" />
              </div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <div class="d-flex justify-center mt-4">
        <v-pagination v-model="page" :length="pageCount" density="comfortable" total-visible="5" />
      </div>
    </template>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon :icon="meta.icon" size="48" class="mb-2" />
      <div class="text-h6">No {{ meta.label.toLowerCase() }} templates yet</div>
      <div class="text-body-2 text-medium-emphasis">Create your first one to reuse it in campaigns, autoresponders, and chatbot rules.</div>
    </v-card>

    <AppDialog :model-value="!!previewing" title="Preview" @update:model-value="previewing = null">
      <TemplatePhonePreview v-if="previewing" :template="previewing" />
    </AppDialog>
  </div>
</template>

<style scoped>
.template-card {
  overflow: hidden;
}

.template-preview-wrap {
  background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
