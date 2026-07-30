<script setup>
defineProps({
  rules: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
})

defineEmits(['edit', 'delete'])

function channelName(channels, channelId) {
  if (channelId === null) return 'All WhatsApp accounts'
  return channels.find((c) => c.id === channelId)?.display_name ?? `Account #${channelId}`
}

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Applies to', key: 'channel_id' },
  { title: 'Keywords', key: 'keywords', sortable: false },
  { title: 'Status', key: 'enabled' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <v-data-table :headers="headers" :items="rules" items-per-page="10">
    <template #item.channel_id="{ item }">{{ channelName(channels, item.channel_id) }}</template>

    <template #item.keywords="{ item }">
      <v-chip v-for="kw in item.keywords.slice(0, 3)" :key="kw" size="x-small" class="mr-1">{{ kw }}</v-chip>
      <span v-if="item.keywords.length > 3" class="text-caption text-medium-emphasis">+{{ item.keywords.length - 3 }}</span>
    </template>

    <template #item.enabled="{ item }">
      <v-chip :color="item.enabled ? 'success' : 'default'" size="small" variant="flat">{{ item.enabled ? 'Enabled' : 'Disabled' }}</v-chip>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-end ga-1">
        <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="$emit('edit', item)" />
        <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', item)" />
      </div>
    </template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-robot-outline" size="48" class="mb-2" />
        <div class="text-h6">No chatbot items yet</div>
        <div class="text-body-2 text-medium-emphasis">Reply automatically when a message contains a keyword.</div>
      </v-card>
    </template>
  </v-data-table>
</template>
