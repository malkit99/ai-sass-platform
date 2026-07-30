<script setup>
defineProps({
  logs: { type: Array, default: () => [] },
})

const statusColor = {
  ringing: 'info',
  answered: 'info',
  completed: 'success',
  auto_rejected: 'warning',
  manually_rejected: 'warning',
  missed: 'error',
}
const statusLabel = {
  ringing: 'Ringing',
  answered: 'Answered',
  completed: 'Completed',
  auto_rejected: 'Auto-rejected',
  manually_rejected: 'Rejected',
  missed: 'Missed',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const headers = [
  { title: 'Caller', key: 'caller_phone' },
  { title: 'Video', key: 'is_video' },
  { title: 'Status', key: 'status' },
  { title: 'Reply sent', key: 'reply_type' },
  { title: 'Started', key: 'started_at' },
  { title: 'Ended', key: 'ended_at' },
]
</script>

<template>
  <v-data-table :headers="headers" :items="logs" items-per-page="10">
    <template #item.is_video="{ item }">
      <v-icon :icon="item.is_video ? 'mdi-video-outline' : 'mdi-phone-outline'" size="18" />
    </template>

    <template #item.status="{ item }">
      <v-chip :color="statusColor[item.status] ?? 'default'" size="small" variant="flat">{{ statusLabel[item.status] ?? item.status }}</v-chip>
    </template>

    <template #item.reply_type="{ item }">{{ item.reply_type ?? '—' }}</template>
    <template #item.started_at="{ item }">{{ formatDate(item.started_at) }}</template>
    <template #item.ended_at="{ item }">{{ formatDate(item.ended_at) }}</template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-phone-log-outline" size="48" class="mb-2" />
        <div class="text-h6">No calls yet</div>
        <div class="text-body-2 text-medium-emphasis">Incoming calls to this account will show up here.</div>
      </v-card>
    </template>
  </v-data-table>
</template>
