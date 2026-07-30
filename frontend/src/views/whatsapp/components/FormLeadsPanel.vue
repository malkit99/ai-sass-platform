<script setup>
import { computed, onMounted } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import { displayOnlyTypes } from '@core/utils/whatsappFormFieldTypes'

const props = defineProps({
  form: { type: Object, required: true },
})

const emit = defineEmits(['back'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

onMounted(() => whatsapp.fetchFormSubmissions(props.form.id))

const inputFields = computed(() => (props.form.fields ?? []).filter((f) => !displayOnlyTypes.includes(f.type)))

function fieldLabel(fieldId) {
  return inputFields.value.find((f) => f.id === fieldId)?.label ?? fieldId
}

function responseChips(submission) {
  return Object.entries(submission.data ?? {})
    .filter(([, value]) => value !== null && value !== '' && !(Array.isArray(value) && !value.length))
    .map(([fieldId, value]) => ({
      label: fieldLabel(fieldId),
      value: Array.isArray(value) ? value.join(', ') : String(value),
    }))
}

function formatDate(value) {
  return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatTime(value) {
  return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

async function exportCsv() {
  try {
    await whatsapp.exportFormSubmissions(props.form)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to export submissions.')
  }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <div class="d-flex align-center ga-2">
          <h2 class="text-h5">{{ form.name }}</h2>
          <v-chip color="primary" variant="tonal" size="small">{{ whatsapp.formSubmissions.length }} SUBMISSIONS</v-chip>
        </div>
        <div class="text-caption text-medium-emphasis">Live analytics and detailed response tracking</div>
      </div>
      <AppButton variant="outlined" prepend-icon="mdi-arrow-left" @click="$emit('back')">Forms List</AppButton>
      <AppButton prepend-icon="mdi-download" @click="exportCsv">Export</AppButton>
    </div>

    <v-table v-if="whatsapp.formSubmissions.length">
      <thead>
        <tr>
          <th>#ID</th>
          <th>Visitor Insight</th>
          <th>Response Analysis</th>
          <th>Submission Time</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="submission in whatsapp.formSubmissions" :key="submission.id">
          <td>
            <div class="d-flex align-center ga-2">
              <v-chip size="small" variant="tonal">{{ submission.id }}</v-chip>
              <v-chip v-if="submission.lead_id" size="small" color="success" variant="tonal" prepend-icon="mdi-account-check-outline">
                CRM Lead
              </v-chip>
            </div>
          </td>
          <td>
            <div class="d-flex align-center ga-1 text-body-2">
              <v-icon icon="mdi-earth" size="16" />{{ submission.ip_address ?? '—' }}
            </div>
            <div class="d-flex align-center ga-1 text-caption text-medium-emphasis text-truncate" style="max-width: 260px">
              <v-icon icon="mdi-laptop" size="14" />{{ submission.user_agent ?? '—' }}
            </div>
          </td>
          <td>
            <v-chip v-for="chip in responseChips(submission)" :key="chip.label" size="x-small" class="mr-1 mb-1">
              {{ chip.label }}: {{ chip.value }}
            </v-chip>
          </td>
          <td>
            <div class="text-body-2 font-weight-medium">{{ formatDate(submission.created_at) }}</div>
            <div class="text-caption text-medium-emphasis">{{ formatTime(submission.created_at) }}</div>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-account-search-outline" size="48" class="mb-2" />
      <div class="text-h6">No submissions yet</div>
      <div class="text-body-2 text-medium-emphasis">Responses to this form will show up here.</div>
    </v-card>
  </div>
</template>
