<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { playConnectedChime } from '@core/utils/sound'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  channel: { type: Object, required: true },
})

const emit = defineEmits(['back', 'connected'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const mode = ref('qr') // 'qr' | 'pairing'
const qr = ref(null)
const status = ref(props.channel.status ?? 'disconnected')
const polling = ref(false)
const pairingPhone = ref('')
const pairingCode = ref(null)
const requestingCode = ref(false)
const label = ref(props.channel.name ?? '')
const savingLabel = ref(false)
let pollTimer = null

const statusMeta = computed(() => ({
  connected: { label: 'CONNECTED', color: 'success' },
  connecting: { label: 'CONNECTING', color: 'warning' },
  disconnected: { label: 'DISCONNECTED', color: 'error' },
}[status.value] ?? { label: status.value.toUpperCase(), color: 'default' }))

function stopPolling() {
  clearInterval(pollTimer)
  pollTimer = null
  polling.value = false
}

function startPolling() {
  stopPolling()
  polling.value = true

  pollTimer = setInterval(async () => {
    try {
      status.value = await whatsapp.fetchStatus(props.channel.id)
      whatsapp.updateChannelStatus(props.channel.id, status.value)

      if (status.value === 'connected') {
        stopPolling()
        alertStore.success('WhatsApp connected.')
        playConnectedChime()
        emit('connected')
        return
      }

      if (mode.value === 'qr') {
        qr.value = await whatsapp.fetchQr(props.channel.id)
      }
    } catch (e) {
      stopPolling()
      alertStore.error(e.response?.data?.message ?? 'Lost connection to the WhatsApp bridge.')
    }
  }, 2500)
}

async function generate() {
  try {
    await whatsapp.connectChannel(props.channel.id)
    status.value = 'connecting'
    startPolling()
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to reach the WhatsApp bridge.')
  }
}

async function requestCode() {
  if (!pairingPhone.value) return

  requestingCode.value = true
  try {
    await whatsapp.connectChannel(props.channel.id)
    status.value = 'connecting'
    pairingCode.value = await whatsapp.requestPairingCode(props.channel.id, pairingPhone.value)
    startPolling()
  } catch (e) {
    alertStore.error(e.response?.data?.error ?? 'Failed to request a pairing code.')
  } finally {
    requestingCode.value = false
  }
}

function copyInstanceId() {
  navigator.clipboard.writeText(String(props.channel.id))
  alertStore.info('Instance ID copied.')
}

async function saveLabel() {
  savingLabel.value = true
  try {
    await whatsapp.renameChannel(props.channel.id, label.value || null)
    alertStore.success('Label saved.')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save label.')
  } finally {
    savingLabel.value = false
  }
}

watch(mode, () => {
  qr.value = null
})

onBeforeUnmount(stopPolling)
</script>

<template>
  <v-row>
    <v-col cols="12" md="4">
      <v-card class="pa-4">
        <div class="text-caption text-medium-emphasis mb-1">CONNECTION STATUS</div>
        <v-chip :color="statusMeta.color" size="small" variant="tonal" class="mb-4">{{ statusMeta.label }}</v-chip>

        <div class="text-caption text-medium-emphasis mb-1">LABEL</div>
        <v-text-field
          v-model="label" placeholder="e.g. Macroword" density="compact" variant="outlined" class="mb-4"
          hint="Shown as &quot;Label(phone number)&quot; in account lists" persistent-hint
          @blur="saveLabel"
        >
          <template #append-inner>
            <v-progress-circular v-if="savingLabel" size="16" width="2" indeterminate color="primary" />
          </template>
        </v-text-field>

        <div class="text-caption text-medium-emphasis mb-1">INSTANCE ID</div>
        <v-text-field :model-value="String(channel.id)" readonly density="compact" variant="outlined" class="mb-4">
          <template #append-inner>
            <v-btn icon="mdi-content-copy" size="small" variant="text" @click="copyInstanceId" />
          </template>
        </v-text-field>

        <div class="text-caption text-medium-emphasis mb-1">PLATFORM</div>
        <div class="d-flex ga-2 mb-6">
          <v-chip size="small" variant="outlined">BAILEYS MD</v-chip>
          <v-chip size="small" variant="outlined"><v-icon start size="14">mdi-shield-lock-outline</v-icon>E2E</v-chip>
        </div>

        <AppButton variant="outlined" prepend-icon="mdi-arrow-left" block @click="$emit('back')">Back to Accounts</AppButton>
      </v-card>
    </v-col>

    <v-col cols="12" md="8">
      <v-card class="pa-6">
        <div class="d-flex align-center justify-space-between mb-4">
          <div>
            <div class="text-h6">Connect WhatsApp</div>
            <div class="text-caption text-medium-emphasis">Secure connection via Baileys Multi-Device</div>
          </div>
          <v-chip :color="statusMeta.color" size="small" variant="flat">{{ statusMeta.label }}</v-chip>
        </div>

        <v-tabs v-model="mode" density="comfortable" class="mb-4">
          <v-tab value="qr">Scan QR</v-tab>
          <v-tab value="pairing">Pairing Code</v-tab>
        </v-tabs>

        <div v-if="mode === 'qr'" class="d-flex flex-column align-center ga-4 py-4">
          <div class="qr-box d-flex align-center justify-center">
            <v-img v-if="qr" :src="qr" width="220" height="220" />
            <v-progress-circular v-else-if="polling" indeterminate color="primary" />
            <AppButton v-else color="success" prepend-icon="mdi-qrcode" @click="generate">Generate QR Code</AppButton>
          </div>
          <div class="text-body-2 font-weight-medium">Open WhatsApp &gt; Linked Devices &gt; Link a Device</div>
          <div class="text-caption text-medium-emphasis">Point your phone at this screen to capture the code</div>
        </div>

        <div v-else class="d-flex flex-column ga-3 py-4" style="max-width: 360px">
          <v-text-field
            v-model="pairingPhone" label="Phone number" placeholder="e.g. 919876543210"
            hint="Country code + number, digits only" persistent-hint
            @update:model-value="(val) => (pairingPhone = val.replace(/\D/g, ''))"
          />
          <AppButton :loading="requestingCode" @click="requestCode">Get pairing code</AppButton>
          <div v-if="pairingCode" class="text-h4 text-center font-weight-bold">{{ pairingCode }}</div>
        </div>
      </v-card>
    </v-col>
  </v-row>
</template>

<style scoped>
.qr-box {
  width: 260px;
  height: 260px;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-surface-variant), 0.2);
}
</style>
