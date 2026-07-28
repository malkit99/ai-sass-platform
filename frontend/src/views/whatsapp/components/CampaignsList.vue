<script setup>
defineProps({
  campaigns: { type: Array, default: () => [] },
})

defineEmits(['edit', 'pause', 'resume', 'delete', 'report'])

const statusColor = { running: 'warning', paused: 'default', completed: 'success', cancelled: 'default', failed: 'error', scheduled: 'info' }

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Status', key: 'status' },
  { title: 'Total Contacts', key: 'recipients_count', align: 'end' },
  { title: 'Queued', key: 'queued_count', align: 'end' },
  { title: 'Success', key: 'sent_count', align: 'end' },
  { title: 'Failed', key: 'failed_count', align: 'end' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <v-data-table :headers="headers" :items="campaigns" items-per-page="10">
    <template #item.name="{ item }">
      {{ item.name }}
      <div v-if="item.recurring_frequency" class="text-caption text-medium-emphasis">
        <v-icon icon="mdi-repeat" size="12" /> {{ item.recurring_frequency }}
      </div>
    </template>

    <template #item.status="{ item }">
      <v-chip :color="statusColor[item.status] ?? 'default'" size="small" variant="flat">{{ item.status }}</v-chip>
      <div v-if="item.status === 'scheduled' && item.scheduled_at" class="text-caption text-medium-emphasis mt-1">
        {{ new Date(item.scheduled_at).toLocaleString() }}
      </div>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-end ga-1">
        <v-btn icon="mdi-file-chart-outline" size="small" variant="text" @click="$emit('report', item)" />
        <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="$emit('edit', item)" />
        <v-btn
          v-if="item.status === 'running'" icon="mdi-pause" size="small" variant="text" color="warning"
          @click="$emit('pause', item)"
        />
        <v-btn
          v-else-if="item.status === 'paused'" icon="mdi-play" size="small" variant="text" color="success"
          @click="$emit('resume', item)"
        />
        <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', item)" />
      </div>
    </template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-send-outline" size="48" class="mb-2" />
        <div class="text-h6">No campaigns yet</div>
        <div class="text-body-2 text-medium-emphasis">Start a bulk campaign to message multiple contacts at once.</div>
      </v-card>
    </template>
  </v-data-table>
</template>
