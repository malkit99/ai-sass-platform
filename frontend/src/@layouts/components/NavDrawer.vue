<script setup>
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding/branding'
import { modules } from '@core/utils/modules'

const { t } = useI18n()

const props = defineProps({
  modelValue: { type: Boolean, default: true },
  rail: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const branding = useBrandingStore()
const route = useRoute()
const { mobile } = useDisplay()

// On mobile, Vuetify auto-switches an otherwise-`permanent` drawer to a
// temporary overlay (with scrim) below its mobile breakpoint — close it after
// navigating so it doesn't stay covering the page once a link is picked.
watch(
  () => route.fullPath,
  () => {
    if (mobile.value) emit('update:modelValue', false)
  },
)
</script>

<template>
  <v-navigation-drawer
    :model-value="modelValue"
    :rail="rail"
    permanent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #prepend>
      <v-list-item class="py-4">
        <template #prepend>
          <v-avatar :image="branding.logoUrl || undefined" :color="branding.logoUrl ? undefined : 'primary'" size="28">
            <span v-if="!branding.logoUrl" class="text-caption font-weight-bold">{{ branding.productName[0] }}</span>
          </v-avatar>
        </template>
        <v-list-item-title v-if="!rail" class="font-weight-bold">{{ branding.productName }}</v-list-item-title>
      </v-list-item>
      <v-divider />
    </template>

    <v-list density="compact" nav active-class="bg-primary-lighten-5">
      <v-list-item
        v-for="item in modules"
        :key="item.titleKey"
        :title="t(item.titleKey)"
        :to="item.enabled ? { name: item.route } : undefined"
        :disabled="!item.enabled"
        :subtitle="item.enabled ? undefined : t('nav.comingSoon')"
        exact
      >
        <template #prepend>
          <v-icon :icon="item.icon" :color="item.enabled ? item.color : 'grey'" />
        </template>
      </v-list-item>
    </v-list>
  </v-navigation-drawer>
</template>

<style scoped>
:deep(.v-list-item__prepend) {
  margin-inline-end: 8px !important;
}

:deep(.v-list-item__prepend .v-list-item__spacer) {
  width: 8px !important;
}

:deep(.v-list-item-title) {
  font-weight: 700 !important;
}
</style>
