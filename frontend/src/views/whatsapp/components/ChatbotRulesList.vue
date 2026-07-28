<script setup>
defineProps({
  rules: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
})

const emit = defineEmits(['edit', 'delete'])

function channelName(channels, channelId) {
  if (channelId === null) return 'All WhatsApp accounts'
  return channels.find((c) => c.id === channelId)?.display_name ?? `Account #${channelId}`
}
</script>

<template>
  <v-table v-if="rules.length">
    <thead>
      <tr>
        <th>Name</th>
        <th>Applies to</th>
        <th>Keywords</th>
        <th>Status</th>
        <th />
      </tr>
    </thead>
    <tbody>
      <tr v-for="rule in rules" :key="rule.id">
        <td>{{ rule.name }}</td>
        <td>{{ channelName(channels, rule.channel_id) }}</td>
        <td>
          <v-chip v-for="kw in rule.keywords.slice(0, 3)" :key="kw" size="x-small" class="mr-1">{{ kw }}</v-chip>
          <span v-if="rule.keywords.length > 3" class="text-caption text-medium-emphasis">+{{ rule.keywords.length - 3 }}</span>
        </td>
        <td><v-chip :color="rule.enabled ? 'success' : 'default'" size="small" variant="flat">{{ rule.enabled ? 'Enabled' : 'Disabled' }}</v-chip></td>
        <td>
          <v-btn size="small" variant="text" @click="$emit('edit', rule)">Edit</v-btn>
          <v-btn size="small" variant="text" color="error" @click="$emit('delete', rule)">Delete</v-btn>
        </td>
      </tr>
    </tbody>
  </v-table>

  <v-card v-else variant="tonal" class="pa-8 text-center">
    <v-icon icon="mdi-robot-outline" size="48" class="mb-2" />
    <div class="text-h6">No chatbot rules yet</div>
    <div class="text-body-2 text-medium-emphasis">Reply automatically when a message contains a keyword.</div>
  </v-card>
</template>
