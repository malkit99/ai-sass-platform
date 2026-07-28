<script setup>
import { useI18n } from 'vue-i18n'
import { timeAgo } from '@core/utils/time'
import { useThemeStore } from '@/stores/theme/theme'
import AppButton from '@/components/AppButton.vue'

defineProps({
  lead: { type: Object, required: true },
  prevStage: { type: Object, default: null },
  nextStage: { type: Object, default: null },
})

defineEmits(['hot', 'prev', 'next', 'delete'])

const themeStore = useThemeStore()
const { t } = useI18n()
</script>

<template>
  <v-card class="mb-2 drag-handle" :variant="themeStore.skin === 'border' ? 'outlined' : 'tonal'">
    <v-card-text class="pb-2">
      <div class="d-flex align-center">
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
      <AppButton
        v-if="nextStage"
        size="small"
        variant="text"
        append-icon="mdi-arrow-right"
        @click="$emit('next', nextStage.id)"
      >
        {{ nextStage.name }}
      </AppButton>
      <v-spacer />
      <AppButton size="small" variant="text" icon="mdi-delete-outline" @click="$emit('delete', lead.id)" />
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
