<script setup>
import { useI18n } from 'vue-i18n'
import { useThemeStore } from '@/stores/theme/theme'

defineProps({
  modelValue: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const themeStore = useThemeStore()
const { t } = useI18n()

const modeOptions = [
  { value: 'light', icon: 'mdi-white-balance-sunny', labelKey: 'theme.light' },
  { value: 'dark', icon: 'mdi-weather-night', labelKey: 'theme.dark' },
  { value: 'system', icon: 'mdi-monitor', labelKey: 'theme.system' },
]

const skinOptions = [
  { value: 'default', icon: 'mdi-square-rounded', labelKey: 'theme.default' },
  { value: 'border', icon: 'mdi-square-rounded-outline', labelKey: 'theme.border' },
]

const widthOptions = [
  { value: 'fluid', icon: 'mdi-arrow-expand-horizontal', labelKey: 'theme.fluid' },
  { value: 'boxed', icon: 'mdi-arrow-collapse-horizontal', labelKey: 'theme.boxed' },
]

const colorPresets = ['#1976D2', '#7E57C2', '#26A69A', '#EF5350', '#FB8C00', '#43A047']
</script>

<template>
  <v-navigation-drawer
    :model-value="modelValue"
    location="right"
    temporary
    width="320"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="d-flex align-center justify-space-between pa-4">
      <span class="text-h6">{{ t('theme.title') }}</span>
      <v-btn icon="mdi-close" variant="text" density="comfortable" @click="$emit('update:modelValue', false)" />
    </div>
    <v-divider />

    <div class="pa-4">
      <div class="text-caption font-weight-medium text-medium-emphasis mb-2">{{ t('theme.mode').toUpperCase() }}</div>
      <v-btn-toggle
        :model-value="themeStore.mode"
        mandatory
        variant="outlined"
        density="comfortable"
        class="mb-6 ga-2"
        @update:model-value="themeStore.setMode"
      >
        <v-btn v-for="option in modeOptions" :key="option.value" :value="option.value" :icon="option.icon" rounded="lg" />
      </v-btn-toggle>

      <div class="text-caption font-weight-medium text-medium-emphasis mb-2">{{ t('theme.skin').toUpperCase() }}</div>
      <v-btn-toggle
        :model-value="themeStore.skin"
        mandatory
        variant="outlined"
        divided
        density="comfortable"
        class="mb-6 d-flex"
        @update:model-value="themeStore.setSkin"
      >
        <v-btn v-for="option in skinOptions" :key="option.value" :value="option.value" :prepend-icon="option.icon" class="flex-grow-1">
          {{ t(option.labelKey) }}
        </v-btn>
      </v-btn-toggle>

      <div class="text-caption font-weight-medium text-medium-emphasis mb-2">{{ t('theme.contentWidth').toUpperCase() }}</div>
      <v-btn-toggle
        :model-value="themeStore.contentWidth"
        mandatory
        variant="outlined"
        divided
        density="comfortable"
        class="mb-6 d-flex"
        @update:model-value="themeStore.setContentWidth"
      >
        <v-btn v-for="option in widthOptions" :key="option.value" :value="option.value" :prepend-icon="option.icon" class="flex-grow-1">
          {{ t(option.labelKey) }}
        </v-btn>
      </v-btn-toggle>

      <div class="d-flex align-center justify-space-between mb-2">
        <span class="text-caption font-weight-medium text-medium-emphasis">{{ t('theme.primaryColor').toUpperCase() }}</span>
        <a
          v-if="themeStore.themeColorCustomized"
          href="#"
          class="text-caption text-decoration-none"
          @click.prevent="themeStore.resetThemeColor"
        >
          {{ t('theme.reset') }}
        </a>
      </div>
      <div class="d-flex ga-2 align-center flex-wrap">
        <button
          v-for="preset in colorPresets"
          :key="preset"
          type="button"
          class="color-swatch"
          :style="{ backgroundColor: preset }"
          :class="{ 'color-swatch--active': themeStore.themeColor === preset && themeStore.themeColorCustomized }"
          @click="themeStore.setThemeColor(preset)"
        />
        <input
          type="color"
          class="color-swatch color-swatch--input"
          :value="themeStore.themeColor"
          @input="themeStore.setThemeColor($event.target.value)"
        />
      </div>
    </div>
  </v-navigation-drawer>
</template>

<style scoped>
.color-swatch {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  padding: 0;
}

.color-swatch--active {
  border-color: rgb(var(--v-theme-on-surface));
}

.color-swatch--input {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: none;
}
</style>
