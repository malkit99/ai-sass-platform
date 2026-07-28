<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  search: { type: String, required: true },
  filter: { type: String, required: true },
})

defineEmits(['update:search', 'update:filter'])

const { t } = useI18n()
</script>

<template>
  <div class="d-flex ga-2 mb-4 flex-wrap align-center flex-shrink-0">
    <v-text-field
      :model-value="search"
      :placeholder="t('crm.searchPlaceholder')"
      prepend-inner-icon="mdi-magnify"
      density="compact"
      variant="outlined"
      hide-details
      style="max-width: 320px"
      @update:model-value="$emit('update:search', $event)"
    />
    <v-chip-group
      :model-value="filter"
      mandatory
      selected-class="bg-primary"
      @update:model-value="$emit('update:filter', $event)"
    >
      <v-chip value="all" filter>{{ t('crm.all') }}</v-chip>
      <v-chip value="hot" filter prepend-icon="mdi-fire">{{ t('crm.hot') }}</v-chip>
      <v-chip value="recent" filter prepend-icon="mdi-calendar">{{ t('crm.recent') }}</v-chip>
    </v-chip-group>
  </div>
</template>
