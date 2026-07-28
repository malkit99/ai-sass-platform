<script setup>
import { computed } from 'vue'

const props = defineProps({
  active: { type: String, required: true },
  channelCount: { type: Number, default: 0 },
  channelLimit: { type: Number, default: null },
})

defineEmits(['update:active', 'add-account'])

const atLimit = computed(() => props.channelLimit !== null && props.channelCount >= props.channelLimit)

const features = [
  { key: 'dashboard', label: 'Dashboard', icon: 'mdi-view-dashboard-outline' },
  { key: 'accounts', label: 'Accounts', icon: 'mdi-account-multiple-outline' },
  { key: 'send', label: 'Send Single Message', icon: 'mdi-send' },
  { key: 'profile', label: 'Profile', icon: 'mdi-account-circle-outline' },
  { key: 'templates', label: 'Templates', icon: 'mdi-file-document-multiple-outline' },
  { key: 'bulk', label: 'Bulk messaging', icon: 'mdi-layers-outline' },
  { key: 'autoresponder', label: 'Autoresponder', icon: 'mdi-reply-outline' },
  { key: 'chatbot', label: 'Chatbot', icon: 'mdi-robot-outline' },
]

const contactFeatures = [
  { key: 'contacts', label: 'Contacts', icon: 'mdi-account-box-outline' },
]
</script>

<template>
  <div class="whatsapp-nav d-flex flex-column flex-shrink-0">
    <div class="pa-3">
      <v-tooltip v-if="atLimit" :text="`Plan limit reached (${channelLimit} accounts)`" location="bottom">
        <template #activator="{ props: tooltipProps }">
          <div v-bind="tooltipProps">
            <v-btn block color="success" variant="tonal" prepend-icon="mdi-plus" disabled>Add account</v-btn>
          </div>
        </template>
      </v-tooltip>
      <v-btn v-else block color="success" variant="tonal" prepend-icon="mdi-plus" @click="$emit('add-account')">Add account</v-btn>
    </div>
    <div class="text-caption text-medium-emphasis px-4 pt-2 pb-1">FEATURES</div>
    <v-list nav density="comfortable">
      <v-list-item
        v-for="item in features" :key="item.key" :active="active === item.key"
        :prepend-icon="item.icon" :title="item.label" rounded="lg"
        @click="$emit('update:active', item.key)"
      />
    </v-list>

    <div class="text-caption text-medium-emphasis px-4 pt-2 pb-1">CONTACT</div>
    <v-list nav density="comfortable">
      <v-list-item
        v-for="item in contactFeatures" :key="item.key" :active="active === item.key"
        :prepend-icon="item.icon" :title="item.label" rounded="lg"
        @click="$emit('update:active', item.key)"
      />
    </v-list>
  </div>
</template>

<style scoped>
.whatsapp-nav {
  width: 220px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
