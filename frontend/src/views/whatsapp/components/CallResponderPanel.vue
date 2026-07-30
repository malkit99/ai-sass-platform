<script setup>
import { computed, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import CallHistoryList from './CallHistoryList.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const channelId = ref(null)
const tab = ref('settings')
const saving = ref(false)
const loading = ref(false)

const DEFAULTS = { enabled: false, auto_reject_enabled: true, reply_delay_seconds: 0, missed_call_reply: '', after_call_reply: '', rejected_call_reply: '', missed_before_answer_reply: '' }
const form = ref({ ...DEFAULTS })

watch(channelId, async (id) => {
  if (!id) {
    form.value = { ...DEFAULTS }
    whatsapp.callHistory = []
    return
  }

  loading.value = true
  try {
    const settings = await whatsapp.fetchCallResponderSettings(id)
    form.value = {
      enabled: settings.enabled ?? false,
      auto_reject_enabled: settings.auto_reject_enabled ?? true,
      reply_delay_seconds: settings.reply_delay_seconds ?? 0,
      missed_call_reply: settings.missed_call_reply ?? '',
      after_call_reply: settings.after_call_reply ?? '',
      rejected_call_reply: settings.rejected_call_reply ?? '',
      missed_before_answer_reply: settings.missed_before_answer_reply ?? '',
    }
    await whatsapp.fetchCallHistory(id)
  } finally {
    loading.value = false
  }
})

const selectedChannel = computed(() => whatsapp.channels.find((c) => c.id === channelId.value) ?? null)

async function save() {
  if (!channelId.value) {
    alertStore.warning('Select an account first.')
    return
  }

  saving.value = true
  try {
    await whatsapp.saveCallResponderSettings({ channel_id: channelId.value, ...form.value })
    alertStore.success('Call responder settings saved.')
  } catch (e) {
    if (e.response?.status === 422) {
      alertStore.error('Check the form — some fields are invalid.')
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save settings.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">Call Responder</h2>
        <div class="text-caption text-medium-emphasis">Configure auto-reply messages for incoming WhatsApp calls per account</div>
      </div>
      <v-chip variant="flat" color="primary">{{ whatsapp.channels.length }} ACCOUNT{{ whatsapp.channels.length === 1 ? '' : 'S' }}</v-chip>
    </div>

    <v-select
      v-model="channelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
      placeholder="Choose account…" variant="outlined" density="comfortable" style="max-width: 420px" class="mb-4"
    >
      <template #item="{ props: itemProps, item }">
        <v-list-item v-bind="itemProps">
          <template #title>{{ item.raw.display_name }}</template>
          <template #append>
            <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
          </template>
        </v-list-item>
      </template>
      <template #selection="{ item }">
        {{ item.raw.display_name }}
        <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat" class="ml-2">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
      </template>
    </v-select>

    <template v-if="channelId">
      <v-tabs v-model="tab" class="mb-4">
        <v-tab value="settings" prepend-icon="mdi-cog-outline">Settings</v-tab>
        <v-tab value="history" prepend-icon="mdi-history">Call History</v-tab>
      </v-tabs>

      <v-window v-model="tab">
        <v-window-item value="settings">
          <v-card variant="tonal" color="error" class="pa-4 mb-3 d-flex align-center justify-space-between">
            <div class="d-flex align-center ga-3">
              <v-icon icon="mdi-power" />
              <div>
                <div class="font-weight-medium">Call Responder</div>
                <div class="text-caption">{{ selectedChannel?.display_name }}</div>
              </div>
            </div>
            <v-switch v-model="form.enabled" color="primary" density="comfortable" hide-details />
          </v-card>

          <v-card variant="tonal" color="primary" class="pa-4 mb-4 d-flex align-center justify-space-between">
            <div class="d-flex align-center ga-3">
              <v-icon icon="mdi-phone-cancel-outline" />
              <div>
                <div class="font-weight-medium">Auto-Reject Incoming Calls</div>
                <div class="text-caption">Automatically reject calls before sending the reply</div>
              </div>
            </div>
            <v-switch v-model="form.auto_reject_enabled" color="primary" density="comfortable" hide-details />
          </v-card>

          <div class="d-flex align-center ga-2 mb-1">
            <v-avatar color="primary" size="28"><v-icon icon="mdi-clock-outline" size="16" /></v-avatar>
            <span class="font-weight-medium">Reply Delay</span>
          </div>
          <v-text-field
            v-model.number="form.reply_delay_seconds" type="number" min="0" max="120" variant="outlined"
            density="comfortable" style="max-width: 160px" suffix="seconds" class="mb-4"
            hint="seconds before sending reply message" persistent-hint
          />

          <div class="d-flex align-center ga-2 mb-3">
            <v-avatar color="primary" size="28"><v-icon icon="mdi-message-text-outline" size="16" /></v-avatar>
            <span class="font-weight-medium">Auto-Reply Messages</span>
          </div>

          <v-card class="pa-4 mb-3" variant="outlined">
            <div class="d-flex align-center ga-2 mb-2">
              <v-avatar color="error" size="28" rounded><v-icon icon="mdi-phone-missed" size="16" color="white" /></v-avatar>
              <span class="font-weight-medium">Missed Call / Auto-Reject Reply</span>
            </div>
            <v-textarea v-model="form.missed_call_reply" placeholder="e.g. Sorry, I am busy right now. I will get back to you soon." variant="outlined" rows="2" auto-grow />
          </v-card>

          <v-card class="pa-4 mb-3" variant="outlined">
            <div class="d-flex align-center ga-2 mb-2">
              <v-avatar color="warning" size="28" rounded><v-icon icon="mdi-phone-check-outline" size="16" color="white" /></v-avatar>
              <span class="font-weight-medium">After Call Ended (Answered & Hung Up)</span>
            </div>
            <v-textarea v-model="form.after_call_reply" placeholder="e.g. Thanks for calling! If your query wasn't fully resolved, please send me a message." variant="outlined" rows="2" auto-grow />
            <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mt-1">
              <v-icon icon="mdi-information-outline" size="14" />Sent after an answered call ends (when either party hangs up)
            </div>
          </v-card>

          <v-card class="pa-4 mb-3" variant="outlined">
            <div class="d-flex align-center ga-2 mb-2">
              <v-avatar color="secondary" size="28" rounded><v-icon icon="mdi-phone-cancel" size="16" color="white" /></v-avatar>
              <span class="font-weight-medium">Call Rejected (You Cut the Call)</span>
            </div>
            <v-textarea v-model="form.rejected_call_reply" placeholder="e.g. Sorry, I can't take calls right now. Please leave a message and I'll respond shortly." variant="outlined" rows="2" auto-grow />
            <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mt-1">
              <v-icon icon="mdi-information-outline" size="14" />Sent when you manually reject/cut the incoming call
            </div>
          </v-card>

          <v-card class="pa-4 mb-4" variant="outlined">
            <div class="d-flex align-center ga-2 mb-2">
              <v-avatar color="info" size="28" rounded><v-icon icon="mdi-phone-alert-outline" size="16" color="white" /></v-avatar>
              <span class="font-weight-medium">Missed Call (Caller Hung Up Before Answer)</span>
            </div>
            <v-textarea v-model="form.missed_before_answer_reply" placeholder="e.g. I noticed you called. How can I help you? Please send a message." variant="outlined" rows="2" auto-grow />
            <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mt-1">
              <v-icon icon="mdi-information-outline" size="14" />Sent when the caller cancels before you could answer
            </div>
          </v-card>

          <AppButton :loading="saving" prepend-icon="mdi-content-save" @click="save">Save Settings</AppButton>
        </v-window-item>

        <v-window-item value="history">
          <CallHistoryList :logs="whatsapp.callHistory" />
        </v-window-item>
      </v-window>
    </template>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-phone-alert-outline" size="48" class="mb-2" />
      <div class="text-h6">Select an account</div>
      <div class="text-body-2 text-medium-emphasis">Choose a WhatsApp account to configure its call responder.</div>
    </v-card>
  </div>
</template>
