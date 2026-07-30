<script setup>
import { computed, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import GroupsList from './GroupsList.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const channelId = ref(null)
const loadingGroups = ref(false)
const exportingId = ref(null)

const steps = [
  'Send a message to group you want export participants',
  'Select account you want export participants',
  'Click Download button of group you want export on list',
]

watch(channelId, async (id) => {
  if (!id) {
    whatsapp.groups = []
    return
  }

  loadingGroups.value = true
  try {
    await whatsapp.fetchGroups(id)
  } finally {
    loadingGroups.value = false
  }
})

const selectedChannel = computed(() => whatsapp.channels.find((c) => c.id === channelId.value) ?? null)
const channelConnected = computed(() => selectedChannel.value?.status === 'connected')

async function exportGroup(group) {
  exportingId.value = group.id
  try {
    await whatsapp.exportGroupParticipants(group)
    await whatsapp.fetchGroups(channelId.value)
    alertStore.success('Participants exported.')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to export participants — the account may be disconnected.')
  } finally {
    exportingId.value = null
  }
}
</script>

<template>
  <div>
    <div class="d-flex align-center ga-2 mb-1">
      <v-icon icon="mdi-file-export-outline" color="primary" />
      <h2 class="text-h5">Export participants</h2>
    </div>
    <div class="text-caption text-medium-emphasis mb-4">Export participant list</div>

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

    <v-alert v-if="selectedChannel && !channelConnected" type="warning" variant="tonal" density="compact" class="mb-4" style="max-width: 420px">
      This account isn't connected — connect it first to download participants.
    </v-alert>

    <v-card class="pa-6 mb-4">
      <div class="text-h6 mb-2">How to use?</div>
      <v-list density="compact">
        <v-list-item
          v-for="(step, index) in steps" :key="index" :title="`${index + 1}. ${step}`"
          :style="index > 0 ? 'border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))' : undefined"
        />
      </v-list>
    </v-card>

    <v-card class="pa-4">
      <GroupsList
        :groups="whatsapp.groups" :channel-selected="!!channelId" :can-export="channelConnected"
        :exporting-id="exportingId" @export="exportGroup"
      />
    </v-card>
  </div>
</template>
