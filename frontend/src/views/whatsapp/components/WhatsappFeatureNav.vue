<script setup>
import { computed } from 'vue'

const props = defineProps({
  active: { type: String, required: true },
  channelCount: { type: Number, default: 0 },
  channelLimit: { type: Number, default: null },
})

defineEmits(['update:active', 'add-account'])

const atLimit = computed(() => props.channelLimit !== null && props.channelCount >= props.channelLimit)

// `color` follows the same per-item "multicolor" convention already used by
// the main app sidebar (see @core/utils/modules.js / @layouts/components/NavDrawer.vue).
const features = [
  { key: 'dashboard', label: 'Dashboard', icon: 'mdi-view-dashboard-outline', color: '#1976D2' },
  { key: 'accounts', label: 'Accounts', icon: 'mdi-account-multiple-outline', color: '#3949AB' },
  { key: 'send', label: 'Send Single Message', icon: 'mdi-send', color: '#25D366' },
  { key: 'profile', label: 'Profile', icon: 'mdi-account-circle-outline', color: '#8E24AA' },
  { key: 'templates', label: 'Templates', icon: 'mdi-file-document-multiple-outline', color: '#26A69A' },
  { key: 'bulk', label: 'Bulk messaging', icon: 'mdi-layers-outline', color: '#D81B60' },
  { key: 'autoresponder', label: 'Autoresponder', icon: 'mdi-reply-outline', color: '#43A047' },
  { key: 'chatbot', label: 'Chatbot', icon: 'mdi-robot-outline', color: '#F4511E' },
  { key: 'links', label: 'Link Generator', icon: 'mdi-link-variant', color: '#00897B' },
  { key: 'groups', label: 'Export Participants', icon: 'mdi-file-export-outline', color: '#1E88E5' },
  { key: 'call-responder', label: 'Call Responder', icon: 'mdi-phone-alert-outline', color: '#E53935' },
  { key: 'forms', label: 'Form Builder', icon: 'mdi-file-document-edit-outline', color: '#5E35B1' },
]

const contactFeatures = [
  { key: 'contacts', label: 'Contacts', icon: 'mdi-account-box-outline', color: '#FB8C00' },
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
    <div class="nav-scroll">
      <div class="text-caption text-medium-emphasis font-weight-bold px-4 pt-2 pb-1">FEATURES</div>
      <v-list nav density="comfortable">
        <v-list-item
          v-for="item in features" :key="item.key" :active="active === item.key" :title="item.label"
          rounded="lg" @click="$emit('update:active', item.key)"
        >
          <template #prepend>
            <v-icon :icon="item.icon" :color="item.color" />
          </template>
        </v-list-item>
      </v-list>

      <div class="text-caption text-medium-emphasis font-weight-bold px-4 pt-2 pb-1">CONTACT</div>
      <v-list nav density="comfortable">
        <v-list-item
          v-for="item in contactFeatures" :key="item.key" :active="active === item.key" :title="item.label"
          rounded="lg" @click="$emit('update:active', item.key)"
        >
          <template #prepend>
            <v-icon :icon="item.icon" :color="item.color" />
          </template>
        </v-list-item>
      </v-list>
    </div>
  </div>
</template>

<style scoped>
.whatsapp-nav {
  width: 220px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.nav-scroll {
  overflow-y: auto;
  /* Bounded independently of the outer page's own scroll (WhatsappView's
     container scrolls the whole page, not per-panel), so this actually
     gets a scrollbar once the feature list overflows instead of just
     growing the page. */
  max-height: calc(100vh - 88px);
}

.nav-scroll :deep(.v-list-item-title) {
  font-weight: 700;
}
</style>
