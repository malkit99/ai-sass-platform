<script setup>
import { ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'

defineProps({
  channels: { type: Array, default: () => [] },
  limit: { type: Number, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['connect', 'disconnect', 'delete', 'send'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }

const editingId = ref(null)
const editingLabel = ref('')
const savingLabel = ref(false)

function startEdit(channel) {
  editingId.value = channel.id
  editingLabel.value = channel.name ?? ''
}

async function saveEdit(channel) {
  savingLabel.value = true
  try {
    await whatsapp.renameChannel(channel.id, editingLabel.value || null)
    editingId.value = null
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save label.')
  } finally {
    savingLabel.value = false
  }
}
</script>

<template>
<div>
  <div v-if="limit !== null" class="text-body-2 text-medium-emphasis mb-3">
    {{ channels.length }} / {{ limit }} WhatsApp accounts used
  </div>

  <v-row v-if="loading">
    <v-col cols="12" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" />
    </v-col>
  </v-row>

  <v-row v-else-if="!channels.length">
    <v-col cols="12">
      <v-card variant="tonal" class="pa-8 text-center">
        <v-icon icon="mdi-whatsapp" size="48" class="mb-2" />
        <div class="text-h6">No WhatsApp accounts connected yet</div>
        <div class="text-body-2 text-medium-emphasis">Add an account to start sending messages.</div>
      </v-card>
    </v-col>
  </v-row>

  <v-row v-else>
    <v-col v-for="channel in channels" :key="channel.id" cols="12" sm="6" md="4">
      <v-card>
        <v-card-item>
          <template #prepend>
            <v-avatar color="success" variant="tonal"><v-icon>mdi-whatsapp</v-icon></v-avatar>
          </template>

          <template v-if="editingId === channel.id">
            <v-text-field
              v-model="editingLabel" density="compact" variant="outlined" placeholder="e.g. Macroword"
              autofocus hide-details
              @keyup.enter="saveEdit(channel)" @blur="saveEdit(channel)"
            >
              <template #append-inner>
                <v-progress-circular v-if="savingLabel" size="16" width="2" indeterminate color="primary" />
              </template>
            </v-text-field>
          </template>
          <template v-else>
            <v-card-title class="d-flex align-center ga-1 text-body-1 font-weight-medium text-truncate">
              <span class="text-truncate">{{ channel.display_name }}</span>
              <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" @click="startEdit(channel)" />
            </v-card-title>
          </template>

          <v-card-subtitle>Instance ID: {{ channel.id }}</v-card-subtitle>
          <template #append>
            <v-chip :color="statusColor[channel.status] ?? 'default'" size="small" variant="flat">
              {{ channel.status }}
            </v-chip>
          </template>
        </v-card-item>
        <v-card-actions>
          <v-btn v-if="channel.status !== 'connected'" variant="text" @click="$emit('connect', channel)">Connect</v-btn>
          <v-btn v-else variant="text" @click="$emit('send', channel)">Send message</v-btn>
          <v-spacer />
          <v-btn v-if="channel.status === 'connected'" variant="text" color="error" @click="$emit('disconnect', channel)">
            Disconnect
          </v-btn>
          <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', channel)" />
        </v-card-actions>
      </v-card>
    </v-col>
  </v-row>
</div>
</template>
