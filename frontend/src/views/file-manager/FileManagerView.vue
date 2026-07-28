<script setup>
import { computed, onMounted, ref } from 'vue'
import { useMediaStore } from '@/stores/media/media'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'

const media = useMediaStore()
const alertStore = useAlertStore()

const fileInput = ref(null)
const dragOver = ref(false)
const selected = ref(new Set())
const keyword = ref('')
const mediaType = ref('all')
const showUrlInput = ref(false)
const externalUrl = ref('')

const typeOptions = [
  { title: 'All Media', value: 'all' },
  { title: 'Images', value: 'image' },
  { title: 'Videos', value: 'video' },
  { title: 'Audio', value: 'audio' },
  { title: 'Documents', value: 'document' },
]

const typeIcon = { image: 'mdi-image-outline', video: 'mdi-video-outline', audio: 'mdi-music-note', document: 'mdi-file-document-outline' }

const filtered = computed(() => {
  let files = media.files
  if (mediaType.value !== 'all') files = files.filter((f) => f.type === mediaType.value)
  if (keyword.value.trim()) {
    const q = keyword.value.trim().toLowerCase()
    files = files.filter((f) => f.name.toLowerCase().includes(q))
  }
  return files
})

const allSelected = computed(() => filtered.value.length > 0 && filtered.value.every((f) => selected.value.has(f.id)))

const usageColor = computed(() => {
  const pct = media.usage?.percent ?? 0
  if (pct >= 90) return 'error'
  if (pct >= 70) return 'warning'
  return 'success'
})

onMounted(async () => {
  await Promise.all([media.fetchFiles(), media.fetchUsage()])
})

function toggleSelect(file) {
  if (selected.value.has(file.id)) selected.value.delete(file.id)
  else selected.value.add(file.id)
}

function toggleSelectAll() {
  if (allSelected.value) {
    filtered.value.forEach((f) => selected.value.delete(f.id))
  } else {
    filtered.value.forEach((f) => selected.value.add(f.id))
  }
}

async function deleteSelected() {
  if (!selected.value.size) return

  const confirmed = await fireConfirm('Delete selected files?', `${selected.value.size} file(s) will be permanently removed.`)
  if (!confirmed) return

  for (const id of [...selected.value]) {
    await media.deleteFile(id)
  }
  selected.value.clear()
  await media.fetchUsage()
  alertStore.info('Selected files deleted.')
}

function triggerUpload() {
  fileInput.value?.click()
}

async function uploadFiles(fileList) {
  for (const file of fileList) {
    try {
      await media.uploadFile(file, 'whatsapp_media')
    } catch (e) {
      alertStore.error(e.response?.data?.errors?.file?.[0] ?? e.response?.data?.message ?? `Failed to upload ${file.name}.`)
      return
    }
  }
  await media.fetchUsage()
  alertStore.success(`${fileList.length} file(s) uploaded.`)
}

async function onFileChosen(event) {
  const files = event.target.files
  if (!files?.length) return
  await uploadFiles(files)
  event.target.value = ''
}

async function onDrop(event) {
  dragOver.value = false
  const files = event.dataTransfer?.files
  if (files?.length) await uploadFiles(files)
}

async function addByUrl() {
  if (!externalUrl.value) return

  try {
    const response = await fetch(externalUrl.value)
    const blob = await response.blob()
    const name = externalUrl.value.split('/').pop() || 'file'
    const file = new File([blob], name, { type: blob.type })
    await uploadFiles([file])
    externalUrl.value = ''
    showUrlInput.value = false
  } catch {
    alertStore.error("Couldn't fetch that URL — check it's a direct, publicly reachable file link.")
  }
}

async function removeFile(file) {
  const confirmed = await fireConfirm('Delete this file?', `"${file.name}" will be permanently removed.`)
  if (!confirmed) return

  await media.deleteFile(file.id)
  selected.value.delete(file.id)
  await media.fetchUsage()
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)}KB`
  return `${(bytes / (1024 * 1024)).toFixed(2)}MB`
}
</script>

<template>
  <div class="d-flex" style="height: 100%">
    <v-container fluid class="pa-4 pa-sm-6" style="overflow-y: auto">
      <div v-if="media.loading" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-row v-else-if="filtered.length">
        <v-col v-for="file in filtered" :key="file.id" cols="6" sm="4" md="3" lg="2">
          <v-card :class="{ 'file-card': true, 'file-card--selected': selected.has(file.id) }" @click="toggleSelect(file)">
            <div class="position-relative">
              <v-checkbox-btn
                :model-value="selected.has(file.id)" class="file-checkbox" density="compact"
                @click.stop="toggleSelect(file)"
              />
              <v-img v-if="file.type === 'image'" :src="file.url" height="110" cover />
              <div v-else class="d-flex align-center justify-center" style="height: 110px; background: rgba(0, 0, 0, 0.04)">
                <v-icon :icon="typeIcon[file.type]" size="40" color="medium-emphasis" />
              </div>
            </div>
            <div class="pa-2">
              <div class="text-body-2 font-weight-medium text-truncate">{{ file.name }}</div>
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption text-medium-emphasis">{{ formatSize(file.size) }}</span>
                <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="error" @click.stop="removeFile(file)" />
              </div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-card v-else variant="tonal" class="pa-8 text-center">
        <v-icon icon="mdi-folder-open-outline" size="48" class="mb-2" />
        <div class="text-h6">No files yet</div>
        <div class="text-body-2 text-medium-emphasis">Upload media to use it across the app.</div>
      </v-card>
    </v-container>

    <div class="file-manager-sidebar pa-4">
      <div class="d-flex ga-2 mb-3">
        <AppButton variant="tonal" prepend-icon="mdi-check-all" block @click="toggleSelectAll">
          {{ allSelected ? 'Deselect all' : 'Select all' }}
        </AppButton>
        <v-btn icon="mdi-delete-outline" color="error" variant="flat" :disabled="!selected.size" @click="deleteSelected" />
      </div>

      <div class="text-overline text-medium-emphasis mb-2">Upload</div>
      <input ref="fileInput" type="file" multiple class="d-none" @change="onFileChosen" />
      <div
        class="upload-dropzone d-flex flex-column align-center justify-center pa-4 mb-3"
        :class="{ 'upload-dropzone--active': dragOver }"
        @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop"
      >
        <v-icon icon="mdi-cloud-upload-outline" size="36" color="primary" class="mb-2" />
        <div class="text-body-2 text-medium-emphasis mb-1">Drag & Drop files here</div>
        <div class="text-caption text-medium-emphasis mb-2">Or</div>
        <AppButton size="small" :loading="media.uploading" @click="triggerUpload">Browse Files</AppButton>
      </div>

      <AppButton
        variant="tonal" prepend-icon="mdi-link-variant" block class="mb-3"
        @click="showUrlInput = !showUrlInput"
      >
        Upload by URL
      </AppButton>
      <div v-if="showUrlInput" class="d-flex ga-2 mb-3">
        <v-text-field v-model="externalUrl" placeholder="https://…" density="compact" variant="outlined" hide-details />
        <v-btn icon="mdi-check" size="small" color="primary" variant="flat" @click="addByUrl" />
      </div>

      <div class="text-overline text-medium-emphasis mb-2">Filter</div>
      <v-text-field
        v-model="keyword" placeholder="Enter keyword" density="compact" variant="outlined"
        prepend-inner-icon="mdi-magnify" class="mb-2" hide-details
      />
      <v-select
        v-model="mediaType" :items="typeOptions" density="compact" variant="outlined" class="mb-4"
        prepend-inner-icon="mdi-filter-outline" hide-details
      />

      <div v-if="media.usage" class="mt-auto">
        <div class="text-overline text-medium-emphasis mb-1">Media info</div>
        <div class="d-flex align-center justify-space-between mb-1">
          <span class="text-body-2 font-weight-medium text-primary">{{ media.usage.used_mb }}MB</span>
          <span class="text-body-2 font-weight-medium">{{ media.usage.limit_mb }}MB</span>
        </div>
        <v-progress-linear :model-value="media.usage.percent" :color="usageColor" height="6" rounded />
        <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis mt-1">
          <span>Used</span>
          <span>Total</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.file-manager-sidebar {
  width: 300px;
  flex-shrink: 0;
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.file-card {
  cursor: pointer;
  border: 2px solid transparent;
}

.file-card--selected {
  border-color: rgb(var(--v-theme-primary));
}

.file-checkbox {
  position: absolute;
  top: -4px;
  left: -4px;
  z-index: 1;
}

.upload-dropzone {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.upload-dropzone--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
