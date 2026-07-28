<script setup>
import { watch } from 'vue'
import { timeAgo } from '@core/utils/time'
import { useActivityStore } from '@/stores/activity/activity'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const activityStore = useActivityStore()

const actionIcons = {
  created: { icon: 'mdi-plus-circle-outline', color: 'success' },
  updated: { icon: 'mdi-pencil-outline', color: 'info' },
  stage_changed: { icon: 'mdi-swap-horizontal', color: 'primary' },
  deleted: { icon: 'mdi-delete-outline', color: 'error' },
}

function iconFor(action) {
  return actionIcons[action] ?? { icon: 'mdi-information-outline', color: 'grey' }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) activityStore.fetchLogs()
  },
)
</script>

<template>
  <v-navigation-drawer
    :model-value="modelValue"
    location="right"
    temporary
    width="360"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="pa-4">
      <div class="d-flex align-center justify-space-between">
        <span class="text-h6">Activity Log</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="$emit('update:modelValue', false)" />
      </div>
      <p class="text-caption text-error mb-0 mt-1">
        Entries older than 90 days are automatically deleted to keep the database from filling up.
      </p>
    </div>
    <v-divider />

    <div v-if="activityStore.loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-list v-else-if="activityStore.logs.length" density="comfortable" lines="two">
      <v-list-item v-for="log in activityStore.logs" :key="log.id">
        <template #prepend>
          <v-avatar :color="iconFor(log.action).color" variant="tonal" size="36">
            <v-icon :icon="iconFor(log.action).icon" size="small" />
          </v-avatar>
        </template>
        <v-list-item-title class="text-wrap">{{ log.description }}</v-list-item-title>
        <v-list-item-subtitle>
          {{ log.user?.name ?? 'System' }} &middot; {{ timeAgo(log.created_at) }}
        </v-list-item-subtitle>
      </v-list-item>
    </v-list>

    <p v-else class="text-caption text-medium-emphasis text-center pa-8">No activity yet.</p>
  </v-navigation-drawer>
</template>
