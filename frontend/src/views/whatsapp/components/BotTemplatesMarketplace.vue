<script setup>
import { computed, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { botFlowTemplateCategories, botFlowTemplates } from '@core/utils/botFlowTemplates'

const props = defineProps({
  channelId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'created'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const search = ref('')
const category = ref('All')
const usingKey = ref(null)

const filtered = computed(() => botFlowTemplates.filter((t) => {
  const matchesCategory = category.value === 'All' || t.category === category.value
  const term = search.value.trim().toLowerCase()
  const matchesSearch = ! term || t.name.toLowerCase().includes(term) || t.description.toLowerCase().includes(term)

  return matchesCategory && matchesSearch
}))

async function use(template) {
  usingKey.value = template.key
  try {
    const bot = await whatsapp.createBotFlow({
      channel_id: props.channelId,
      name: template.name,
      trigger_keywords: template.triggerKeywords,
      status: 'draft',
      flow_definition: template.flowDefinition,
    })
    emit('created', bot)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to create a bot from this template.')
  } finally {
    usingKey.value = null
  }
}
</script>

<template>
  <div>
    <v-card class="pa-6 mb-5 marketplace-banner" rounded="lg">
      <div class="d-flex align-center ga-3">
        <v-avatar color="white" variant="tonal" size="52" rounded="lg"><v-icon icon="mdi-shopping-outline" size="28" /></v-avatar>
        <div>
          <div class="text-h5 font-weight-bold text-white">Templates Marketplace</div>
          <div class="text-body-2 text-white" style="opacity: .85">Discover production-ready templates to launch your WhatsApp automation instantly</div>
        </div>
      </div>
    </v-card>

    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
      <v-text-field
        v-model="search" placeholder="Search templates…" prepend-inner-icon="mdi-magnify"
        variant="outlined" density="comfortable" hide-details style="max-width: 320px"
      />
      <v-btn variant="outlined" prepend-icon="mdi-arrow-left" @click="$emit('back')">Back</v-btn>
    </div>

    <div class="d-flex flex-wrap ga-2 mb-4">
      <v-chip
        v-for="c in botFlowTemplateCategories" :key="c" :color="category === c ? 'primary' : 'default'"
        :variant="category === c ? 'flat' : 'outlined'" @click="category = c"
      >
        {{ c }}
      </v-chip>
    </div>

    <v-row v-if="filtered.length">
      <v-col v-for="t in filtered" :key="t.key" cols="12" sm="6" md="4">
        <v-card class="pa-4" rounded="lg" style="height: 100%">
          <v-avatar :color="t.color" variant="flat" size="48" rounded="lg" class="mb-3"><v-icon :icon="t.icon" color="white" /></v-avatar>
          <div class="text-caption text-medium-emphasis text-uppercase">{{ t.category }}</div>
          <div class="text-body-1 font-weight-medium mb-1">{{ t.name }}</div>
          <div class="text-caption text-medium-emphasis mb-4">{{ t.description }}</div>
          <v-btn color="primary" variant="tonal" block :loading="usingKey === t.key" prepend-icon="mdi-download-outline" @click="use(t)">Use</v-btn>
        </v-card>
      </v-col>
    </v-row>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-magnify" size="40" class="mb-2" />
      <div class="text-body-2 text-medium-emphasis">No templates match your search.</div>
    </v-card>
  </div>
</template>

<style scoped>
.marketplace-banner {
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 60%, #ec4899 100%);
}
</style>
