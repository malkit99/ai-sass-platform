<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import ImportContactDialog from './ImportContactDialog.vue'
import ImportWhatsappDialog from './ImportWhatsappDialog.vue'

const props = defineProps({
  group: { type: Object, required: true },
})

const emit = defineEmits(['back'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const search = ref('')
const page = ref(1)
const selected = ref([])
const validating = ref(false)

const statusColor = { valid: 'success', invalid: 'error', unknown: 'default' }

const showImportCsv = ref(false)
const showImportWhatsapp = ref(false)

// "Validate" routes through a connected instance too, but isn't part of the
// two dedicated import dialogs — kept as its own small picker.
const showChannelPicker = ref(false)
const pickerChannelId = ref(null)

onMounted(async () => {
  if (!whatsapp.channels.length) await whatsapp.fetchChannels()
  load()
})

function load() {
  whatsapp.fetchContacts(props.group.id, { search: search.value, page: page.value })
}

watch(page, load)

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

const allSelected = computed(() => whatsapp.contacts.length > 0 && selected.value.length === whatsapp.contacts.length)

function toggleSelectAll() {
  selected.value = allSelected.value ? [] : whatsapp.contacts.map((c) => c.id)
}

function paramsPreview(contact) {
  const entries = Object.entries(contact.params ?? {})
  if (!entries.length) return '—'
  return entries.map(([k, v]) => `${k}: ${v}`).join(', ')
}

function onImported() {
  page.value = 1
  load()
}

function openValidatePicker() {
  pickerChannelId.value = whatsapp.channels.find((c) => c.status === 'connected')?.id ?? null
  showChannelPicker.value = true
}

async function confirmValidate() {
  if (!pickerChannelId.value) {
    alertStore.warning('Select a connected WhatsApp account.')
    return
  }

  showChannelPicker.value = false
  validating.value = true
  try {
    const result = await whatsapp.validateContacts(props.group.id, { channelId: pickerChannelId.value, ids: selected.value.length ? selected.value : null })
    alertStore.success(`Checked ${result.checked} — ${result.valid} valid, ${result.invalid} invalid.`)
    selected.value = []
    load()
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Validation failed.')
  } finally {
    validating.value = false
  }
}

async function deleteInvalid() {
  const confirmed = await fireConfirm('Delete all invalid contacts?', 'Every contact marked invalid in this group will be permanently removed.')
  if (!confirmed) return

  const result = await whatsapp.deleteInvalidContacts(props.group.id)
  alertStore.info(`${result.deleted} invalid contact(s) deleted.`)
  load()
}

async function deleteSelected() {
  if (!selected.value.length) return

  const confirmed = await fireConfirm('Delete selected contacts?', `${selected.value.length} contact(s) will be permanently removed.`)
  if (!confirmed) return

  await whatsapp.bulkDeleteContacts(props.group.id, selected.value)
  selected.value = []
  alertStore.info('Selected contacts deleted.')
  load()
}

function exportCsv() {
  whatsapp.exportContacts(props.group.id, props.group.name)
}
</script>

<template>
  <div>
    <div class="d-flex align-center ga-3 mb-4">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$emit('back')" />
      <div>
        <h2 class="text-h5">{{ group.name }}</h2>
        <div class="text-caption text-medium-emphasis">{{ whatsapp.contactsMeta?.total ?? 0 }} contacts</div>
      </div>
    </div>

    <v-text-field
      v-model="search" placeholder="Search numbers…" prepend-inner-icon="mdi-magnify"
      variant="outlined" density="comfortable" class="mb-3" hide-details
    />

    <div class="d-flex flex-wrap ga-2 mb-4">
      <AppButton color="primary" prepend-icon="mdi-file-import-outline" @click="showImportCsv = true">
        Import CSV
      </AppButton>
      <AppButton color="success" prepend-icon="mdi-whatsapp" @click="showImportWhatsapp = true">
        Import from WhatsApp
      </AppButton>
      <AppButton color="info" prepend-icon="mdi-check-decagram-outline" :loading="validating" @click="openValidatePicker">
        Validate
      </AppButton>
      <AppButton color="error" prepend-icon="mdi-delete-alert-outline" @click="deleteInvalid">
        Delete Invalid
      </AppButton>
      <AppButton color="success" variant="tonal" prepend-icon="mdi-export-variant" @click="exportCsv">
        Export
      </AppButton>
      <v-btn icon="mdi-delete-outline" color="error" :disabled="!selected.length" @click="deleteSelected" />
    </div>

    <v-table>
      <thead>
        <tr>
          <th style="width: 40px"><v-checkbox-btn :model-value="allSelected" @update:model-value="toggleSelectAll" /></th>
          <th>NO.</th>
          <th>PHONE NUMBER</th>
          <th>STATUS</th>
          <th>PARAMS</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="whatsapp.contactsLoading">
          <td colspan="5" class="text-center pa-6">
            <v-progress-circular indeterminate color="primary" />
          </td>
        </tr>
        <tr v-else-if="!whatsapp.contacts.length">
          <td colspan="5" class="text-center text-medium-emphasis pa-6">No contacts yet — import a CSV or sync from WhatsApp.</td>
        </tr>
        <tr v-for="(contact, index) in whatsapp.contacts" :key="contact.id">
          <td><v-checkbox-btn v-model="selected" :value="contact.id" /></td>
          <td>{{ (page - 1) * 20 + index + 1 }}</td>
          <td>
            {{ contact.phone }}
            <div v-if="contact.name" class="text-caption text-medium-emphasis">{{ contact.name }}</div>
          </td>
          <td><v-chip :color="statusColor[contact.status] ?? 'default'" size="small" variant="tonal">{{ contact.status }}</v-chip></td>
          <td class="text-caption text-truncate" style="max-width: 260px">{{ paramsPreview(contact) }}</td>
        </tr>
      </tbody>
    </v-table>

    <div v-if="whatsapp.contactsMeta?.lastPage > 1" class="d-flex justify-center mt-4">
      <v-pagination v-model="page" :length="whatsapp.contactsMeta.lastPage" density="comfortable" total-visible="5" />
    </div>

    <AppDialog v-model="showChannelPicker" title="Select WhatsApp Account" max-width="420">
      <div class="text-caption text-medium-emphasis mb-1">INSTANCE</div>
      <v-select
        v-model="pickerChannelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
        variant="outlined" density="comfortable" placeholder="Select a connected instance"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item v-bind="itemProps" :disabled="item.raw.status !== 'connected'">
            <template #append><v-chip size="x-small" :color="item.raw.status === 'connected' ? 'success' : 'default'">{{ item.raw.status }}</v-chip></template>
          </v-list-item>
        </template>
      </v-select>

      <template #actions>
        <AppButton variant="outlined" @click="showChannelPicker = false">Cancel</AppButton>
        <AppButton @click="confirmValidate">Continue</AppButton>
      </template>
    </AppDialog>

    <ImportContactDialog v-model="showImportCsv" :group-id="group.id" @imported="onImported" />
    <ImportWhatsappDialog v-model="showImportWhatsapp" :group-id="group.id" @imported="onImported" />
  </div>
</template>
