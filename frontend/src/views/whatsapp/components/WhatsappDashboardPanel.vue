<script setup>
import { computed, onMounted } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  dashboard: { type: Object, default: null },
})

defineEmits(['send-message', 'bulk-send', 'view-campaigns', 'manage-autoresponders', 'manage-chatbot'])

const whatsapp = useWhatsappStore()

onMounted(() => {
  whatsapp.fetchCampaigns()
  whatsapp.fetchAutoresponders()
  whatsapp.fetchChatbotRules()
})

const recentCampaigns = computed(() => whatsapp.campaigns.slice(0, 3))
const campaignStatusColor = { running: 'warning', completed: 'success', cancelled: 'default', failed: 'error', scheduled: 'info' }

const distribution = computed(() => {
  const d = props.dashboard?.message_distribution ?? { direct: 0, bulk: 0, auto: 0 }
  const total = d.direct + d.bulk + d.auto

  return [
    { label: 'Direct', value: d.direct, color: 'primary', pct: total ? Math.round((d.direct / total) * 100) : 0 },
    { label: 'Bulk', value: d.bulk, color: 'success', pct: total ? Math.round((d.bulk / total) * 100) : 0 },
    { label: 'Auto', value: d.auto, color: 'warning', pct: total ? Math.round((d.auto / total) * 100) : 0 },
  ]
})

const totalMessages = computed(() => distribution.value.reduce((sum, d) => sum + d.value, 0))
</script>

<template>
  <div v-if="dashboard">
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">WhatsApp Analytics</h2>
        <div class="text-caption text-medium-emphasis">Overview of your messaging performance and automation stats</div>
      </div>
      <AppButton prepend-icon="mdi-send" @click="$emit('send-message')">Send Message</AppButton>
      <AppButton prepend-icon="mdi-layers-outline" variant="tonal" @click="$emit('bulk-send')">Bulk Send</AppButton>
    </div>

    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card color="primary" variant="tonal" class="pa-4">
          <v-icon icon="mdi-database-outline" size="28" class="mb-2" />
          <div class="text-caption">Available Credits</div>
          <div class="text-h4 font-weight-bold">{{ dashboard.credits.remaining }}</div>
          <div class="text-caption text-medium-emphasis">Plan limit: {{ dashboard.credits.limit ?? '—' }}</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="info" variant="tonal" class="pa-4">
          <v-icon icon="mdi-send-outline" size="28" class="mb-2" />
          <div class="text-caption">Messages Sent</div>
          <div class="text-h4 font-weight-bold">{{ dashboard.messages_sent_this_month }}</div>
          <div class="text-caption text-medium-emphasis">This month</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="success" variant="tonal" class="pa-4">
          <v-icon icon="mdi-layers-outline" size="28" class="mb-2" />
          <div class="text-caption">Bulk Delivered</div>
          <div class="text-h4 font-weight-bold">{{ dashboard.bulk.delivered }}</div>
          <div class="text-caption text-medium-emphasis">{{ dashboard.bulk.success_rate }}% success</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card color="warning" variant="tonal" class="pa-4">
          <v-icon icon="mdi-reply-outline" size="28" class="mb-2" />
          <div class="text-caption">Autoresponder</div>
          <div class="text-h4 font-weight-bold">{{ dashboard.autoresponder_active_count }}</div>
          <div class="text-caption text-medium-emphasis">Active rules</div>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-1">
      <v-col cols="12" md="7">
        <v-card class="pa-4" style="height: 100%">
          <div class="d-flex align-center justify-space-between mb-3">
            <span class="text-subtitle-1 font-weight-medium">Message Distribution</span>
            <span class="text-caption text-medium-emphasis">{{ totalMessages }} total</span>
          </div>
          <div v-for="d in distribution" :key="d.label" class="mb-3">
            <div class="d-flex align-center justify-space-between mb-1">
              <div class="d-flex align-center ga-2">
                <span class="dist-dot" :class="`bg-${d.color}`" />
                <span class="text-body-2">{{ d.label }}</span>
              </div>
              <span class="text-body-2 font-weight-medium">{{ d.value }}</span>
            </div>
            <v-progress-linear :model-value="d.pct" :color="d.color" height="8" rounded bg-color="surface-variant" />
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card class="pa-4" style="height: 100%">
          <div class="text-subtitle-1 font-weight-medium mb-3">Performance Summary</div>
          <v-row>
            <v-col cols="6" class="text-center">
              <v-icon icon="mdi-calendar-month-outline" size="28" class="mb-1" />
              <div class="text-h5 font-weight-bold">{{ dashboard.messages_sent_this_month }}</div>
              <div class="text-caption text-medium-emphasis">This month</div>
            </v-col>
            <v-col cols="6" class="text-center">
              <v-icon icon="mdi-check-circle-outline" color="success" size="28" class="mb-1" />
              <div class="text-h5 font-weight-bold">{{ dashboard.bulk.success_rate }}%</div>
              <div class="text-caption text-medium-emphasis">Success rate</div>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-1">
      <v-col cols="12">
        <v-card class="pa-4">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-bullhorn-outline" color="medium-emphasis" />
              <span class="text-subtitle-1 font-weight-medium">Recent Campaigns</span>
            </div>
            <v-btn variant="outlined" size="small" append-icon="mdi-arrow-right" @click="$emit('view-campaigns')">View All</v-btn>
          </div>

          <div v-if="recentCampaigns.length">
            <div
              v-for="c in recentCampaigns" :key="c.id"
              class="d-flex align-center justify-space-between py-2"
              style="border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
            >
              <span class="text-body-2">{{ c.name }}</span>
              <div class="d-flex align-center ga-3">
                <span class="text-caption text-medium-emphasis">{{ c.sent_count }}/{{ c.recipients_count }} sent</span>
                <v-chip :color="campaignStatusColor[c.status] ?? 'default'" size="small" variant="flat">{{ c.status }}</v-chip>
              </div>
            </div>
          </div>
          <div v-else class="d-flex flex-column align-center pa-8">
            <v-icon icon="mdi-bullhorn-outline" size="40" color="medium-emphasis" class="mb-2" />
            <span class="text-body-2 text-medium-emphasis">No campaigns yet. Start your first bulk messaging campaign!</span>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-1">
      <v-col cols="12" md="6">
        <v-card class="pa-4" style="height: 100%">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-reply-outline" color="warning" />
              <span class="text-subtitle-1 font-weight-medium">Autoresponders</span>
            </div>
            <v-btn variant="outlined" size="small" append-icon="mdi-arrow-right" @click="$emit('manage-autoresponders')">Manage</v-btn>
          </div>

          <div v-if="whatsapp.autoresponders.length">
            <div
              v-for="a in whatsapp.autoresponders" :key="a.id"
              class="d-flex align-center justify-space-between py-2"
              style="border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
            >
              <span class="text-body-2 text-truncate" style="max-width: 220px">{{ a.body }}</span>
              <v-chip :color="a.enabled ? 'success' : 'default'" size="small" variant="flat">{{ a.enabled ? 'Enabled' : 'Disabled' }}</v-chip>
            </div>
          </div>
          <div v-else class="d-flex flex-column align-center pa-8">
            <v-icon icon="mdi-reply-outline" size="40" color="medium-emphasis" class="mb-2" />
            <span class="text-body-2 text-medium-emphasis">No autoresponders configured yet.</span>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card class="pa-4" style="height: 100%">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-robot-outline" color="secondary" />
              <span class="text-subtitle-1 font-weight-medium">Chatbot Performance</span>
            </div>
            <v-btn variant="outlined" size="small" append-icon="mdi-arrow-right" @click="$emit('manage-chatbot')">Manage</v-btn>
          </div>

          <div v-if="whatsapp.chatbotRules.length">
            <div
              v-for="rule in whatsapp.chatbotRules" :key="rule.id"
              class="d-flex align-center justify-space-between py-2"
              style="border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
            >
              <span class="text-body-2">{{ rule.name }}</span>
              <v-chip :color="rule.enabled ? 'success' : 'default'" size="small" variant="flat">{{ rule.enabled ? 'Active' : 'Inactive' }}</v-chip>
            </div>
          </div>
          <div v-else class="d-flex flex-column align-center pa-8">
            <v-icon icon="mdi-robot-outline" size="40" color="medium-emphasis" class="mb-2" />
            <span class="text-body-2 text-medium-emphasis">No chatbots active yet.</span>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>

  <div v-else class="d-flex justify-center pa-8">
    <v-progress-circular indeterminate color="primary" />
  </div>
</template>

<style scoped>
.dist-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
</style>
