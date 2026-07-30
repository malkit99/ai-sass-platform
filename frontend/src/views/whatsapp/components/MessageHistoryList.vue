<script setup>
const props = defineProps({
  messages: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 20 },
  itemsLength: { type: Number, default: 0 },
})

defineEmits(['update:page'])

const directionColor = { in: 'info', out: 'success' }
const directionIcon = { in: 'mdi-arrow-bottom-left', out: 'mdi-arrow-top-right' }
const directionLabel = { in: 'Received', out: 'Sent' }

const statusColor = { sending: 'warning', sent: 'success', received: 'info', failed: 'error' }

const typeIcon = {
  text: 'mdi-message-text-outline',
  media: 'mdi-image-outline',
  poll: 'mdi-poll',
  buttons: 'mdi-gesture-tap-button',
  list: 'mdi-format-list-bulleted',
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const headers = [
  { title: 'Direction', key: 'direction', sortable: false },
  { title: 'Contact', key: 'contact', sortable: false },
  { title: 'Type', key: 'type', sortable: false },
  { title: 'Message', key: 'body', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Time', key: 'created_at', sortable: false },
]
</script>

<template>
  <v-data-table-server
    :headers="headers" :items="props.messages" :loading="props.loading" :items-length="props.itemsLength"
    :page="props.page" :items-per-page="props.itemsPerPage" items-per-page-text=""
    :items-per-page-options="[]" @update:page="$emit('update:page', $event)"
  >
    <template #item.direction="{ item }">
      <v-chip :color="directionColor[item.direction] ?? 'default'" size="small" variant="tonal" :prepend-icon="directionIcon[item.direction]">
        {{ directionLabel[item.direction] ?? item.direction }}
      </v-chip>
    </template>

    <template #item.contact="{ item }">
      {{ item.conversation?.contact_phone ?? '—' }}
      <div v-if="item.conversation?.contact_name" class="text-caption text-medium-emphasis">{{ item.conversation.contact_name }}</div>
    </template>

    <template #item.type="{ item }">
      <v-icon :icon="typeIcon[item.type] ?? 'mdi-message-outline'" size="18" class="mr-1" />{{ item.type }}
    </template>

    <template #item.body="{ item }">
      <span v-if="item.body" class="text-truncate d-inline-block" style="max-width: 320px">{{ item.body }}</span>
      <span v-else-if="item.media_url" class="text-medium-emphasis">[media]</span>
      <span v-else class="text-medium-emphasis">—</span>
    </template>

    <template #item.status="{ item }">
      <v-chip :color="statusColor[item.status] ?? 'default'" size="small" variant="flat">{{ item.status }}</v-chip>
      <div v-if="item.error" class="text-caption text-error text-truncate" style="max-width: 200px">{{ item.error }}</div>
    </template>

    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-message-text-outline" size="48" class="mb-2" />
        <div class="text-h6">No messages yet</div>
        <div class="text-body-2 text-medium-emphasis">Messages sent and received through this account will show up here.</div>
      </v-card>
    </template>
  </v-data-table-server>
</template>
