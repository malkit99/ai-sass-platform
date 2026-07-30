<script setup>
import { ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import CreateBotDialog from './CreateBotDialog.vue'

const props = defineProps({
  channelId: { type: Number, required: true },
})

const emit = defineEmits(['back', 'created', 'marketplace'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const showNameDialog = ref(false)
const showImport = ref(false)
const importing = ref(false)
const fileInput = ref(null)

function startFromScratch() {
  showNameDialog.value = true
}

async function onNamed({ name, triggerKeywords }) {
  const bot = await whatsapp.createBotFlow({
    channel_id: props.channelId,
    name,
    trigger_keywords: triggerKeywords,
    status: 'draft',
    flow_definition: {
      nodes: [
        { id: 'start-1', type: 'start', position: { x: 100, y: 200 }, data: {} },
        { id: 'end-1', type: 'end', position: { x: 420, y: 200 }, data: {} },
      ],
      edges: [{ id: 'e-start-end', source: 'start-1', target: 'end-1' }],
    },
  })
  showNameDialog.value = false
  emit('created', bot)
}

function openImport() {
  showImport.value = true
}

function pickFile() {
  fileInput.value?.click()
}

async function onFileChosen(event) {
  const file = event.target.files?.[0]
  if (! file) return

  importing.value = true
  try {
    const bot = await whatsapp.importBotFlow(props.channelId, file)
    alertStore.success('Bot imported.')
    emit('created', bot)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to import this file.')
  } finally {
    importing.value = false
    event.target.value = ''
  }
}

function onDrop(event) {
  const file = event.dataTransfer?.files?.[0]
  if (file) onFileChosen({ target: { value: '', files: [file] } })
}
</script>

<template>
  <div class="d-flex flex-column align-center py-8">
    <v-avatar color="primary" variant="tonal" size="72" class="mb-4">
      <v-icon icon="mdi-robot-happy-outline" size="36" />
    </v-avatar>
    <div class="text-h5 font-weight-bold mb-1">Create a new bot</div>
    <div class="text-body-2 text-medium-emphasis mb-6">Choose how you want to start building your WhatsApp automation</div>

    <div class="choice-list">
      <v-card class="pa-4 mb-3 choice-card" rounded="lg" @click="startFromScratch">
        <div class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="flat" size="44" rounded="lg"><v-icon icon="mdi-plus" color="white" /></v-avatar>
          <div class="flex-grow-1">
            <div class="font-weight-medium">Start from scratch</div>
            <div class="text-caption text-medium-emphasis">Begin with an empty canvas and build your flow step-by-step</div>
          </div>
          <v-icon icon="mdi-arrow-right" />
        </div>
      </v-card>

      <div class="text-center text-caption text-medium-emphasis my-2">OR</div>

      <v-card class="pa-4 mb-3 choice-card" rounded="lg" @click="$emit('marketplace')">
        <div class="d-flex align-center ga-3">
          <v-avatar color="warning" variant="flat" size="44" rounded="lg"><v-icon icon="mdi-view-grid-outline" color="white" /></v-avatar>
          <div class="flex-grow-1">
            <div class="font-weight-medium">Start from a template</div>
            <div class="text-caption text-medium-emphasis">Choose from our library of pre-built, high-conversion flows</div>
          </div>
          <v-icon icon="mdi-arrow-right" />
        </div>
      </v-card>

      <v-card class="pa-4 mb-4 choice-card" :class="{ 'choice-card-active': showImport }" rounded="lg" @click="openImport">
        <div class="d-flex align-center ga-3">
          <v-avatar color="secondary" variant="flat" size="44" rounded="lg"><v-icon icon="mdi-arrow-right-thin" color="white" /></v-avatar>
          <div class="flex-grow-1">
            <div class="font-weight-medium">Import a file</div>
            <div class="text-caption text-medium-emphasis">Upload a .json file exported from another bot instance</div>
          </div>
          <v-icon icon="mdi-arrow-right" />
        </div>
      </v-card>

      <v-card
        v-if="showImport" variant="tonal" color="primary" class="pa-8 text-center mb-4" rounded="lg"
        @dragover.prevent @drop.prevent="onDrop"
      >
        <v-icon icon="mdi-cloud-upload-outline" size="36" class="mb-2" />
        <div>
          <v-btn color="primary" variant="flat" :loading="importing" prepend-icon="mdi-folder-outline" @click="pickFile">Choose JSON File</v-btn>
        </div>
        <div class="text-caption text-medium-emphasis mt-2">or drag &amp; drop your .json file here</div>
        <input ref="fileInput" type="file" accept="application/json,.json" class="d-none" @change="onFileChosen" />
      </v-card>
    </div>

    <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="$emit('back')">Back to bots</v-btn>

    <CreateBotDialog v-model="showNameDialog" @create="onNamed" />
  </div>
</template>

<style scoped>
.choice-list {
  width: 100%;
  max-width: 620px;
}

.choice-card {
  cursor: pointer;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: border-color .15s ease;
}

.choice-card:hover {
  border-color: rgb(var(--v-theme-primary));
}

.choice-card-active {
  border-color: rgb(var(--v-theme-primary));
}
</style>
