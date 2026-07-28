<script setup>
import { useI18n } from 'vue-i18n'
import { timeAgo } from '@core/utils/time'
import { useThemeStore } from '@/stores/theme/theme'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  lead: { type: Object, required: true },
  prevStage: { type: Object, default: null },
  nextStage: { type: Object, default: null },
})

const emit = defineEmits(['hot', 'prev', 'next', 'delete'])

const themeStore = useThemeStore()
const { t } = useI18n()

async function confirmDelete() {
  const confirmed = await fireConfirm(
    'Delete this lead?',
    `${props.lead.name} will be permanently deleted. This can't be undone.`,
  )
  if (confirmed) {
    emit('delete', props.lead.id)
  }
}
</script>

<template>
  <v-card class="mb-2 drag-handle" :variant="themeStore.skin === 'border' ? 'outlined' : 'tonal'">
    <v-card-text class="pb-2">
      <div class="d-flex align-center ga-1">
        <strong>{{ lead.name }}</strong>
        <v-spacer />
        <v-icon
          :color="lead.is_hot ? 'orange' : 'grey'"
          size="small"
          style="cursor: pointer"
          @click="$emit('hot', lead)"
        >
          mdi-fire
        </v-icon>
        <v-icon color="error" size="small" style="cursor: pointer" @click="confirmDelete">
          mdi-delete
        </v-icon>
      </div>
      <div class="text-caption text-medium-emphasis">{{ timeAgo(lead.last_activity_at) }}</div>
      <div class="text-caption">{{ lead.phone || lead.email || t('crm.noContactInfo') }}</div>
    </v-card-text>
    <v-card-actions class="pt-0">
      <AppButton
        v-if="prevStage"
        size="small"
        variant="text"
        prepend-icon="mdi-arrow-left"
        @click="$emit('prev', prevStage.id)"
      >
        {{ prevStage.name }}
      </AppButton>
      <v-spacer />
      <AppButton
        v-if="nextStage"
        size="small"
        variant="text"
        append-icon="mdi-arrow-right"
        @click="$emit('next', nextStage.id)"
      >
        {{ nextStage.name }}
      </AppButton>
    </v-card-actions>
  </v-card>
</template>

<style scoped>
.drag-handle {
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}
</style>
