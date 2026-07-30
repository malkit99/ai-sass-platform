<script setup>
import AppButton from '@/components/AppButton.vue'

defineProps({
  groups: { type: Array, default: () => [] },
  channelSelected: { type: Boolean, default: false },
  canExport: { type: Boolean, default: false },
  exportingId: { type: Number, default: null },
})

defineEmits(['export'])

function formatDate(value) {
  if (!value) return 'Never'
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const headers = [
  { title: 'Group', key: 'name' },
  { title: 'Group ID', key: 'group_jid' },
  { title: 'Participants', key: 'participant_count', align: 'end' },
  { title: 'Last synced', key: 'last_synced_at' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <v-data-table v-if="channelSelected" :headers="headers" :items="groups" items-per-page="10">
    <template #item.name="{ item }">{{ item.name || 'Unnamed group' }}</template>
    <template #item.last_synced_at="{ item }">{{ formatDate(item.last_synced_at) }}</template>

    <template #item.actions="{ item }">
      <AppButton
        size="small" variant="tonal" prepend-icon="mdi-download" :disabled="!canExport"
        :loading="exportingId === item.id" @click="$emit('export', item)"
      >
        Download
      </AppButton>
    </template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-account-group-outline" size="48" class="mb-2" />
        <div class="text-h6">No groups discovered yet</div>
        <div class="text-body-2 text-medium-emphasis">Send or receive a message in the group you want to export — it'll show up here.</div>
      </v-card>
    </template>
  </v-data-table>

  <v-card v-else variant="tonal" class="pa-8 text-center">
    <v-icon icon="mdi-account-group-outline" size="48" class="mb-2" />
    <div class="text-h6">Select an account</div>
    <div class="text-body-2 text-medium-emphasis">Select an account to view its groups</div>
  </v-card>
</template>
