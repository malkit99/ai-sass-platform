<script setup>
import { onMounted, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import MessageHistoryList from './MessageHistoryList.vue'

const whatsapp = useWhatsappStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const channelId = ref(null)
const search = ref('')
const direction = ref(null)
const type = ref(null)
const status = ref(null)
const page = ref(1)

const directionOptions = [
  { title: 'All', value: null },
  { title: 'Sent', value: 'out' },
  { title: 'Received', value: 'in' },
]
const typeOptions = [
  { title: 'All', value: null },
  { title: 'Text', value: 'text' },
  { title: 'Media', value: 'media' },
  { title: 'Poll', value: 'poll' },
  { title: 'Buttons', value: 'buttons' },
  { title: 'List', value: 'list' },
]
const statusOptions = [
  { title: 'All', value: null },
  { title: 'Sent', value: 'sent' },
  { title: 'Received', value: 'received' },
  { title: 'Sending', value: 'sending' },
  { title: 'Failed', value: 'failed' },
]

function load() {
  if (!channelId.value) return
  whatsapp.fetchMessageHistory(channelId.value, {
    search: search.value, direction: direction.value, type: type.value, status: status.value, page: page.value,
  })
}

watch(channelId, () => {
  page.value = 1
  whatsapp.messageHistory = []
  load()
})
watch([direction, type, status], () => {
  page.value = 1
  load()
})
watch(page, load)

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

onMounted(() => {
  channelId.value = whatsapp.channels[0]?.id ?? null
  if (!channelId.value) return
  load()
})
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">Message History</h2>
        <div class="text-caption text-medium-emphasis">Every message sent and received through this account</div>
      </div>
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
      <div class="d-flex flex-wrap ga-3 mb-4">
        <v-text-field
          v-model="search" placeholder="Search phone, name or message…" prepend-inner-icon="mdi-magnify"
          variant="outlined" density="comfortable" hide-details style="max-width: 280px"
        />
        <v-select v-model="direction" :items="directionOptions" label="Direction" variant="outlined" density="comfortable" hide-details style="max-width: 160px" />
        <v-select v-model="type" :items="typeOptions" label="Type" variant="outlined" density="comfortable" hide-details style="max-width: 160px" />
        <v-select v-model="status" :items="statusOptions" label="Status" variant="outlined" density="comfortable" hide-details style="max-width: 160px" />
      </div>

      <MessageHistoryList
        :messages="whatsapp.messageHistory" :loading="whatsapp.messageHistoryLoading"
        :page="page" :items-length="whatsapp.messageHistoryMeta?.total ?? 0"
        @update:page="page = $event"
      />
    </template>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-message-text-outline" size="48" class="mb-2" />
      <div class="text-h6">Select an account</div>
      <div class="text-body-2 text-medium-emphasis">Choose a WhatsApp account to see its message history.</div>
    </v-card>
  </div>
</template>
