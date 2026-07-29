<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  campaignId: { type: [Number, String], required: true },
})

defineEmits(['back'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const loading = ref(true)
const campaign = ref(null)

const statusColor = { running: 'warning', paused: 'default', completed: 'success', cancelled: 'default', failed: 'error', scheduled: 'info' }
const recipientStatusColor = { pending: 'info', sent: 'success', failed: 'error' }

const recipients = computed(() => campaign.value?.recipients ?? [])
const stats = computed(() => ({
  total: recipients.value.length,
  sent: recipients.value.filter((r) => r.status === 'sent').length,
  failed: recipients.value.filter((r) => r.status === 'failed').length,
  pending: recipients.value.filter((r) => r.status === 'pending').length,
}))

const headers = [
  { title: 'Phone', key: 'phone' },
  { title: 'Status', key: 'status' },
  { title: 'Scheduled', key: 'scheduled_at' },
  { title: 'Sent', key: 'sent_at' },
  { title: 'Error', key: 'error' },
]

function formatDate(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

async function load() {
  loading.value = true
  try {
    campaign.value = await whatsapp.fetchCampaign(props.campaignId)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to load campaign report.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <AppButton variant="outlined" prepend-icon="mdi-arrow-left" @click="$emit('back')">Back</AppButton>
      <div class="flex-grow-1">
        <h2 class="text-h5">{{ campaign?.name ?? 'Campaign Report' }}</h2>
        <div class="text-caption text-medium-emphasis">Delivery status for every recipient in this campaign</div>
      </div>
      <v-chip v-if="campaign" :color="statusColor[campaign.status] ?? 'default'" variant="flat">{{ campaign.status }}</v-chip>
    </div>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="campaign">
      <v-alert v-if="['buttons', 'list'].includes(campaign.message_type)" type="info" variant="tonal" density="compact" class="mb-4">
        This campaign uses WhatsApp's native interactive-message format (recently switched to after an older format silently failed to deliver).
        "Sent" here means WhatsApp's servers accepted the message — worth spot-checking a real device the first few times you run this message type.
      </v-alert>

      <v-row class="mb-1">
        <v-col cols="6" sm="3">
          <v-card color="primary" variant="tonal" class="pa-4">
            <v-icon icon="mdi-account-multiple-outline" size="24" class="mb-2" />
            <div class="text-caption">Total</div>
            <div class="text-h5 font-weight-bold">{{ stats.total }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card color="success" variant="tonal" class="pa-4">
            <v-icon icon="mdi-check-circle-outline" size="24" class="mb-2" />
            <div class="text-caption">Sent</div>
            <div class="text-h5 font-weight-bold">{{ stats.sent }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card color="error" variant="tonal" class="pa-4">
            <v-icon icon="mdi-alert-circle-outline" size="24" class="mb-2" />
            <div class="text-caption">Failed</div>
            <div class="text-h5 font-weight-bold">{{ stats.failed }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card color="info" variant="tonal" class="pa-4">
            <v-icon icon="mdi-clock-outline" size="24" class="mb-2" />
            <div class="text-caption">Queued</div>
            <div class="text-h5 font-weight-bold">{{ stats.pending }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-card class="pa-2">
        <v-data-table :headers="headers" :items="recipients" items-per-page="25">
          <template #item.status="{ item }">
            <v-chip :color="recipientStatusColor[item.status] ?? 'default'" size="small" variant="flat">{{ item.status }}</v-chip>
          </template>
          <template #item.scheduled_at="{ item }">{{ formatDate(item.scheduled_at) }}</template>
          <template #item.sent_at="{ item }">{{ formatDate(item.sent_at) }}</template>
          <template #item.error="{ item }">
            <span class="text-caption text-error">{{ item.error ?? '—' }}</span>
          </template>
          <template #no-data>
            <div class="pa-8 text-center text-body-2 text-medium-emphasis">No recipients on this campaign.</div>
          </template>
        </v-data-table>
      </v-card>
    </template>
  </div>
</template>
