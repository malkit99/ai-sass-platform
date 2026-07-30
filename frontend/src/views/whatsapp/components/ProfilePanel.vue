<script setup>
import { computed, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const selectedId = ref(whatsapp.channels[0]?.id ?? null)

watch(
  () => whatsapp.channels,
  (channels) => {
    if (!channels.some((c) => c.id === selectedId.value)) {
      selectedId.value = channels[0]?.id ?? null
    }
  },
)

const channel = computed(() => whatsapp.channels.find((c) => c.id === selectedId.value) ?? null)

function copy(value, label) {
  navigator.clipboard.writeText(String(value))
  alertStore.info(`${label} copied.`)
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

async function logout() {
  const confirmed = await fireConfirm('Log out of WhatsApp?', `"${channel.value.display_name}" will be disconnected. You can reconnect it later.`)
  if (!confirmed) return

  await whatsapp.disconnectChannel(channel.value.id)
  alertStore.info('Logged out.')
}
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="text-h5">Profile</h2>
      <div class="text-caption text-medium-emphasis">Information WhatsApp account</div>
    </div>

    <v-select
      v-model="selectedId" :items="whatsapp.channels" item-title="display_name" item-value="id"
      label="WhatsApp account" density="comfortable" variant="outlined" style="max-width: 420px" class="mb-6"
    >
      <template #item="{ props: itemProps, item }">
        <v-list-item v-bind="itemProps">
          <template #title>{{ item.raw.display_name }}</template>
          <template #append>
            <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
          </template>
        </v-list-item>
      </template>
      <template #selection="{ item }">{{ item.raw.display_name }}</template>
    </v-select>

    <v-card v-if="channel" class="pa-6">
      <div class="d-flex align-center justify-space-between mb-6">
        <div class="d-flex align-center ga-4">
          <v-avatar size="56" :color="statusColor[channel.status] ?? 'default'" variant="tonal"><v-icon size="32">mdi-whatsapp</v-icon></v-avatar>
          <div>
            <div class="d-flex align-center ga-2">
              <span class="text-h6">{{ channel.display_name }}</span>
              <v-chip :color="statusColor[channel.status] ?? 'default'" size="small" variant="flat">{{ statusLabel[channel.status] ?? channel.status }}</v-chip>
            </div>
            <div class="text-caption text-medium-emphasis">{{ channel.whatsapp_jid ?? 'Not connected yet' }}</div>
          </div>
        </div>
        <v-btn v-if="channel.status === 'connected'" color="error" variant="tonal" prepend-icon="mdi-logout" @click="logout">
          Logout
        </v-btn>
      </div>

      <v-divider class="mb-4" />

      <div class="text-caption text-medium-emphasis mb-1">INSTANCE ID</div>
      <v-text-field :model-value="String(channel.id)" readonly density="compact" variant="outlined" class="mb-4">
        <template #append-inner>
          <v-btn icon="mdi-content-copy" size="small" variant="text" @click="copy(channel.id, 'Instance ID')" />
        </template>
      </v-text-field>

      <div class="text-caption text-medium-emphasis mb-1">ACCESS TOKEN</div>
      <v-text-field :model-value="channel.access_token" readonly density="compact" variant="outlined" class="mb-2">
        <template #append-inner>
          <v-btn icon="mdi-content-copy" size="small" variant="text" @click="copy(channel.access_token, 'Access token')" />
        </template>
      </v-text-field>

      <div class="text-caption text-medium-emphasis text-right">Last update: {{ formatDate(channel.updated_at) }}</div>
    </v-card>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-account-circle-outline" size="48" class="mb-2" />
      <div class="text-h6">No WhatsApp accounts yet</div>
      <div class="text-body-2 text-medium-emphasis">Add an account to see its profile here.</div>
    </v-card>
  </div>
</template>
