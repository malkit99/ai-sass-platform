<script setup>
import { useAlertStore } from '@/stores/alert/alert'

const props = defineProps({
  links: { type: Array, default: () => [] },
  channelSelected: { type: Boolean, default: false },
})

defineEmits(['delete'])

const alertStore = useAlertStore()

function copy(link) {
  navigator.clipboard.writeText(link)
  alertStore.info('Link copied.')
}

const headers = [
  { title: 'Reference', key: 'reference_name' },
  { title: 'Phone', key: 'phone' },
  { title: 'Link', key: 'link', sortable: false },
  { title: 'Clicks', key: 'clicks', align: 'end' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
</script>

<template>
  <v-data-table v-if="channelSelected" :headers="headers" :items="links" items-per-page="10">
    <template #item.link="{ item }">
      <div class="text-truncate" style="max-width: 260px">{{ item.short_url }}</div>
      <div class="text-caption text-medium-emphasis text-truncate" style="max-width: 260px">→ {{ item.wa_me_url }}</div>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-end ga-1">
        <v-btn icon="mdi-content-copy" size="small" variant="text" @click="copy(item.short_url)" />
        <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="$emit('delete', item)" />
      </div>
    </template>

    <template #no-data>
      <v-card variant="tonal" class="pa-8 text-center ma-4">
        <v-icon icon="mdi-link-variant" size="48" class="mb-2" />
        <div class="text-h6">No saved links yet</div>
        <div class="text-body-2 text-medium-emphasis">Generate one on the left to see it here.</div>
      </v-card>
    </template>
  </v-data-table>

  <v-card v-else variant="tonal" class="pa-8 text-center">
    <v-icon icon="mdi-format-list-bulleted" size="48" class="mb-2" />
    <div class="text-h6">Select an account</div>
    <div class="text-body-2 text-medium-emphasis">Select an account to view saved links</div>
  </v-card>
</template>
