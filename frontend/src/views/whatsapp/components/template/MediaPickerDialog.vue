<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useMediaStore } from '@/stores/media/media'
import { useAlertStore } from '@/stores/alert/alert'
import AppDialog from '@/components/AppDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  type: { type: String, required: true }, // image|video|audio|document
})

const emit = defineEmits(['update:modelValue', 'selected'])

const media = useMediaStore()
const alertStore = useAlertStore()

const tab = ref('library')
const externalUrl = ref('')
const fileInput = ref(null)

const acceptAttr = computed(() => ({
  image: 'image/*',
  video: 'video/*',
  audio: 'audio/*',
  document: '.pdf,.doc,.docx,.xls,.xlsx,.txt',
}[props.type] ?? '*/*'))

const filesOfType = computed(() => media.files.filter((f) => f.type === props.type))

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      tab.value = 'library'
      externalUrl.value = ''
    }
  },
)

onMounted(() => media.fetchFiles({ type: props.type, purpose: 'whatsapp_media' }))

function triggerUpload() {
  fileInput.value?.click()
}

async function onFileChosen(event) {
  const file = event.target.files?.[0]
  if (!file) return

  try {
    await media.uploadFile(file, 'whatsapp_media')
    alertStore.success('File uploaded.')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Upload failed.')
  } finally {
    event.target.value = ''
  }
}

async function removeFile(file) {
  await media.deleteFile(file.id)
}

function selectFile(file) {
  emit('selected', file.url)
  emit('update:modelValue', false)
}

// Share-page links (Google Drive "edit"/"view" links, Dropbox "s/" share
// pages, etc.) return an HTML login/preview page — not the raw file — when
// fetched by a server with no browser session. WhatsApp then fails to send
// or, worse, can knock the session offline trying to process the bad
// download. Catch the common ones here instead of only failing at send time.
const SHARE_PAGE_HOSTS = [
  { pattern: /(^|\.)docs\.google\.com$/, name: 'Google Docs/Sheets/Slides' },
  { pattern: /(^|\.)drive\.google\.com$/, name: 'Google Drive' },
  { pattern: /(^|\.)dropbox\.com$/, name: 'Dropbox' },
  { pattern: /(^|\.)onedrive\.live\.com$/, name: 'OneDrive' },
  { pattern: /(^|\.)1drv\.ms$/, name: 'OneDrive' },
]

function useExternalUrl() {
  if (!externalUrl.value) return

  let host = ''
  try {
    host = new URL(externalUrl.value).hostname
  } catch {
    alertStore.error('Enter a valid URL.')
    return
  }

  const sharePage = SHARE_PAGE_HOSTS.find((h) => h.pattern.test(host))
  if (sharePage) {
    alertStore.error(`This looks like a ${sharePage.name} share link, not a direct file link — WhatsApp can't fetch it. Use a direct download URL, or upload the file to the Media Library instead.`)
    return
  }

  emit('selected', externalUrl.value)
  emit('update:modelValue', false)
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const typeIcon = { image: 'mdi-image-outline', video: 'mdi-video-outline', audio: 'mdi-music-note', document: 'mdi-file-document-outline' }
</script>

<template>
  <AppDialog :model-value="modelValue" title="Choose Media" max-width="640" @update:model-value="$emit('update:modelValue', $event)">
    <v-tabs v-model="tab" density="comfortable" class="mb-4">
      <v-tab value="library">Media Library</v-tab>
      <v-tab value="url">External URL</v-tab>
    </v-tabs>

    <div v-if="tab === 'library'">
      <div class="d-flex justify-end mb-3">
        <input ref="fileInput" type="file" :accept="acceptAttr" class="d-none" @change="onFileChosen" />
        <AppButton size="small" prepend-icon="mdi-upload" :loading="media.uploading" @click="triggerUpload">
          Upload New
        </AppButton>
      </div>

      <div v-if="media.loading" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-row v-else-if="filesOfType.length">
        <v-col v-for="file in filesOfType" :key="file.id" cols="6" sm="4">
          <v-card class="pa-2 media-card" @click="selectFile(file)">
            <v-img v-if="file.type === 'image'" :src="file.url" height="90" cover class="rounded mb-1" />
            <div v-else class="d-flex align-center justify-center rounded mb-1" style="height: 90px; background: rgba(0, 0, 0, 0.04)">
              <v-icon :icon="typeIcon[file.type]" size="36" color="medium-emphasis" />
            </div>
            <div class="text-caption text-truncate">{{ file.name }}</div>
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption text-medium-emphasis">{{ formatSize(file.size) }}</span>
              <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="error" @click.stop="removeFile(file)" />
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-card v-else variant="tonal" class="pa-8 text-center">
        <v-icon :icon="typeIcon[type]" size="40" class="mb-2" />
        <div class="text-body-2 text-medium-emphasis">No files yet — upload one to get started.</div>
      </v-card>
    </div>

    <div v-else class="d-flex flex-column ga-3">
      <v-text-field v-model="externalUrl" label="Media URL" placeholder="https://…" variant="outlined" />
      <AppButton :disabled="!externalUrl" @click="useExternalUrl">Use this URL</AppButton>
    </div>

    <template #actions>
      <AppButton variant="outlined" @click="$emit('update:modelValue', false)">Close</AppButton>
    </template>
  </AppDialog>
</template>

<style scoped>
.media-card {
  cursor: pointer;
  transition: box-shadow 0.15s ease;
}

.media-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>
