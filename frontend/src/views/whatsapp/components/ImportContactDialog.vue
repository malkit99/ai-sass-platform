<script setup>
import { ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  groupId: { type: [Number, String], required: true },
})

const emit = defineEmits(['update:modelValue', 'imported'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const PARAM_LIMIT = 20
const variableHint = '{{name}}, {{param1}}…{{param20}}'

const tab = ref('csv')
const fileInput = ref(null)
const chosenFile = ref(null)
const importing = ref(false)

const phone = ref('')
const name = ref('')
const params = ref([])
const savingContact = ref(false)

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      tab.value = 'csv'
      chosenFile.value = null
      phone.value = ''
      name.value = ''
      params.value = []
    }
  },
)

function triggerFilePick() {
  fileInput.value?.click()
}

function onFileChosen(event) {
  chosenFile.value = event.target.files?.[0] ?? null
}

function downloadTemplate() {
  const headers = ['Phone', 'Name', ...Array.from({ length: PARAM_LIMIT }, (_, i) => `Param${i + 1}`)]
  const blob = new Blob([headers.join(',') + '\n'], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'contacts-template.csv'
  link.click()
  URL.revokeObjectURL(url)
}

async function upload() {
  if (!chosenFile.value) {
    alertStore.warning('Choose a CSV or Excel file first.')
    return
  }

  importing.value = true
  try {
    const result = await whatsapp.importContactsCsv(props.groupId, chosenFile.value)
    alertStore.success(`Imported ${result.imported}, updated ${result.updated}${result.invalid ? `, ${result.invalid} invalid format` : ''}.`)
    emit('imported')
    emit('update:modelValue', false)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Import failed.')
  } finally {
    importing.value = false
  }
}

function addParam() {
  if (params.value.length < PARAM_LIMIT) params.value.push('')
}

function removeParam(index) {
  params.value.splice(index, 1)
}

async function submitForm() {
  if (!phone.value.trim()) {
    alertStore.warning('Phone number is required.')
    return
  }

  savingContact.value = true
  try {
    await whatsapp.createContact(props.groupId, { phone: phone.value, name: name.value || null, params: params.value })
    alertStore.success('Contact added.')
    phone.value = ''
    name.value = ''
    params.value = []
    emit('imported')
  } catch (e) {
    alertStore.error(e.response?.data?.errors?.phone?.[0] ?? e.response?.data?.message ?? 'Failed to add contact.')
  } finally {
    savingContact.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" title="Import contact" max-width="480" @update:model-value="$emit('update:modelValue', $event)">
    <div class="d-flex ga-1 pa-1 mb-4 tab-switch">
      <button type="button" class="tab-switch-btn flex-grow-1" :class="{ active: tab === 'csv' }" @click="tab = 'csv'">
        UPLOAD CSV
      </button>
      <button type="button" class="tab-switch-btn flex-grow-1" :class="{ active: tab === 'form' }" @click="tab = 'form'">
        VIA FORM
      </button>
    </div>

    <div v-if="tab === 'csv'">
      <AppButton block variant="tonal" color="secondary" prepend-icon="mdi-download-outline" class="mb-3" @click="downloadTemplate">
        Download template
      </AppButton>

      <input ref="fileInput" type="file" accept=".csv,.txt,.xlsx,.xls" class="d-none" @change="onFileChosen" />
      <div class="d-flex align-center file-picker mb-3" @click="triggerFilePick">
        <div class="file-picker-btn">Choose File</div>
        <div class="text-body-2 text-medium-emphasis text-truncate px-3">{{ chosenFile?.name ?? 'No file chosen' }}</div>
      </div>

      <AppButton block size="large" :loading="importing" prepend-icon="mdi-upload-outline" class="upload-btn mb-4" @click="upload">
        Upload CSV / Excel
      </AppButton>

      <v-card variant="tonal" class="pa-3">
        <div class="d-flex align-start ga-2 text-caption text-medium-emphasis mb-2">
          <v-icon icon="mdi-information-outline" size="16" />
          <span>Supports: CSV (.csv) and Excel (.xlsx, .xls)</span>
        </div>
        <div class="d-flex align-start ga-2 text-caption text-medium-emphasis mb-2">
          <v-icon icon="mdi-content-duplicate" size="16" />
          <span>Duplicate numbers (matched by phone) update the existing contact instead of creating a new one.</span>
        </div>
        <div class="d-flex align-start ga-2 text-caption text-medium-emphasis">
          <v-icon icon="mdi-tag-outline" size="16" />
          <span>Columns: Phone (required), Name, Param1–Param20 — usable as {{ variableHint }} in messages.</span>
        </div>
      </v-card>
    </div>

    <div v-else>
      <div class="text-caption text-medium-emphasis mb-1">PHONE NUMBER *</div>
      <v-text-field v-model="phone" placeholder="e.g. 919876543210" variant="outlined" density="comfortable" class="mb-3" />

      <div class="text-caption text-medium-emphasis mb-1">NAME</div>
      <v-text-field v-model="name" placeholder="Optional" variant="outlined" density="comfortable" class="mb-3" />

      <div v-for="(p, index) in params" :key="index" class="d-flex ga-2 mb-2">
        <v-text-field v-model="params[index]" :label="`Param${index + 1}`" variant="outlined" density="comfortable" hide-details />
        <v-btn icon="mdi-close" size="small" variant="text" @click="removeParam(index)" />
      </div>

      <AppButton
        v-if="params.length < PARAM_LIMIT" variant="tonal" prepend-icon="mdi-plus" size="small" class="mb-4"
        @click="addParam"
      >
        Add Param
      </AppButton>

      <AppButton block size="large" :loading="savingContact" prepend-icon="mdi-account-plus-outline" @click="submitForm">
        Add Contact
      </AppButton>
    </div>
  </AppDialog>
</template>

<style scoped>
.tab-switch {
  background: rgba(var(--v-theme-on-surface), 0.06);
  border-radius: 10px;
}

.tab-switch-btn {
  padding: 10px;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: rgba(var(--v-theme-on-surface), 0.7);
  background: transparent;
}

.tab-switch-btn.active {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.file-picker {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
}

.file-picker-btn {
  padding: 10px 16px;
  background: rgba(var(--v-theme-on-surface), 0.06);
  font-size: 0.8125rem;
  white-space: nowrap;
}

.upload-btn {
  background: linear-gradient(120deg, #1fb972, #17a866) !important;
  color: white !important;
}
</style>
