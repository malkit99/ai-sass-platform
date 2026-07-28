<script setup>
defineProps({
  autoresponders: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
})

const emit = defineEmits(['edit', 'delete'])

function channelName(channels, channelId) {
  if (channelId === null) return 'All WhatsApp accounts'
  return channels.find((c) => c.id === channelId)?.display_name ?? `Account #${channelId}`
}
</script>

<template>
  <v-table v-if="autoresponders.length">
    <thead>
      <tr>
        <th>Applies to</th>
        <th>Status</th>
        <th>Message</th>
        <th />
      </tr>
    </thead>
    <tbody>
      <tr v-for="a in autoresponders" :key="a.id">
        <td>{{ channelName(channels, a.channel_id) }}</td>
        <td><v-chip :color="a.enabled ? 'success' : 'default'" size="small" variant="flat">{{ a.enabled ? 'Enabled' : 'Disabled' }}</v-chip></td>
        <td class="text-truncate" style="max-width: 320px">{{ a.body }}</td>
        <td>
          <v-btn size="small" variant="text" @click="$emit('edit', a)">Edit</v-btn>
          <v-btn size="small" variant="text" color="error" @click="$emit('delete', a)">Delete</v-btn>
        </td>
      </tr>
    </tbody>
  </v-table>

  <v-card v-else variant="tonal" class="pa-8 text-center">
    <v-icon icon="mdi-reply-outline" size="48" class="mb-2" />
    <div class="text-h6">No autoresponder configured</div>
    <div class="text-body-2 text-medium-emphasis">Set a fallback reply for messages that don't match any chatbot rule.</div>
  </v-card>
</template>
