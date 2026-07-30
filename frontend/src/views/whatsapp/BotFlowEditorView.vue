<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import BotFlowCanvas from './components/BotFlowCanvas.vue'

const props = defineProps({
  id: { type: [String, Number], required: true },
})

const router = useRouter()
const whatsapp = useWhatsappStore()

const bot = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    bot.value = await whatsapp.fetchBotFlow(props.id)
  } finally {
    loading.value = false
  }
})

function back() {
  router.push({ name: 'whatsapp', query: { feature: 'bot-builder' } })
}
</script>

<template>
  <div class="editor-page">
    <div v-if="loading" class="d-flex justify-center align-center" style="height: 100vh">
      <v-progress-circular indeterminate color="primary" />
    </div>
    <v-card v-else-if="! bot" class="pa-8 text-center ma-8">
      <div class="text-h6">Bot not found</div>
      <v-btn class="mt-4" variant="outlined" @click="back">Back to Bot Builder</v-btn>
    </v-card>
    <BotFlowCanvas v-else :bot="bot" @back="back" />
  </div>
</template>

<style scoped>
.editor-page {
  height: 100vh;
  width: 100vw;
  overflow: hidden;
  background: rgb(var(--v-theme-background));
  padding: 12px;
}
</style>
