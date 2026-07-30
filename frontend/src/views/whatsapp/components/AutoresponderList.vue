<script setup>
defineProps({
  autoresponders: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
})

defineEmits(['edit', 'delete'])

function channelName(channels, channelId) {
  if (channelId === null) return 'All WhatsApp accounts'
  return channels.find((c) => c.id === channelId)?.display_name ?? `Account #${channelId}`
}

const headers = [
  { title: 'Applies to', key: 'channel_id' },
  { title: 'Status', key: 'enabled' },
  { title: 'Message', key: 'body', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <v-data-table :headers="headers" :items="autoresponders" items-per-page="10">
    <template #item.channel_id="{ item }">{{ channelName(channels, item.channel_id) }}</template>

    <template #item.enabled="{ item }">
      <v-chip :color="item.enabled ? 'success' : 'default'" size="small" variant="flat">{{ item.enabled ? 'Enabled' : 'Disabled' }}</v-chip>
    </template>

    <template #item.body="{ item }">
      <span class="text-truncate d-inline-block" style="max-width: 320px">{{ item.body }}</span>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-end ga-1">
        <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="$emit('edit', item)" />
        <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', item)" />
      </div>
    </template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-reply-outline" size="48" class="mb-2" />
        <div class="text-h6">No autoresponder configured</div>
        <div class="text-body-2 text-medium-emphasis">Set a fallback reply for messages that don't match any chatbot rule.</div>
      </v-card>
    </template>
  </v-data-table>
</template>
