<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'

const emit = defineEmits(['create', 'open', 'marketplace'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const search = ref('')

onMounted(async () => {
  if (! whatsapp.channels.length) await whatsapp.fetchChannels()
  whatsapp.fetchBotFlows()
  whatsapp.fetchBotFlowsDashboard()
})

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (! term) return whatsapp.botFlows
  return whatsapp.botFlows.filter((b) => b.name.toLowerCase().includes(term))
})

function defaultChannelId() {
  return whatsapp.channels.find((c) => c.status === 'connected')?.id ?? whatsapp.channels[0]?.id ?? null
}

function create() {
  const channelId = defaultChannelId()
  if (! channelId) {
    alertStore.warning('Connect a WhatsApp account first.')
    return
  }
  emit('create', channelId)
}

function marketplace() {
  const channelId = defaultChannelId()
  if (! channelId) {
    alertStore.warning('Connect a WhatsApp account first.')
    return
  }
  emit('marketplace', channelId)
}

async function toggleStatus(bot) {
  const status = bot.status === 'active' ? 'draft' : 'active'
  await whatsapp.updateBotFlow(bot.id, {
    channel_id: bot.channel_id, name: bot.name, status,
    trigger_keywords: bot.trigger_keywords, flow_definition: bot.flow_definition,
  })
  whatsapp.fetchBotFlowsDashboard()
}

async function remove(bot) {
  const confirmed = await fireConfirm('Delete this bot?', `"${bot.name}" and its conversation state will be permanently removed.`)
  if (! confirmed) return

  await whatsapp.deleteBotFlow(bot.id)
  whatsapp.fetchBotFlowsDashboard()
  alertStore.info('Bot deleted.')
}

function exportBot(bot) {
  whatsapp.exportBotFlow(bot)
}
</script>

<template>
  <div>
    <v-card class="pa-6 mb-5 bot-builder-banner" rounded="lg">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3">
        <div class="d-flex align-center ga-3">
          <v-avatar color="white" variant="tonal" size="52" rounded="lg">
            <v-icon icon="mdi-robot-happy-outline" size="28" />
          </v-avatar>
          <div>
            <div class="text-h5 font-weight-bold text-white">Bot Builder</div>
            <div class="text-body-2 text-white" style="opacity: .85">Create, manage and deploy your WhatsApp automation bots</div>
          </div>
        </div>
        <div class="d-flex ga-2">
          <v-btn variant="tonal" color="white" prepend-icon="mdi-shopping-outline" @click="marketplace">Marketplace</v-btn>
          <v-btn color="white" variant="flat" prepend-icon="mdi-plus" @click="create">Create Bot</v-btn>
        </div>
      </div>
    </v-card>

    <v-row class="mb-2">
      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal" color="primary" class="pa-4">
          <v-icon icon="mdi-robot-outline" size="26" class="mb-2" />
          <div class="text-h5 font-weight-bold">{{ whatsapp.botFlowsDashboard?.total_bots ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Total Bots</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal" color="success" class="pa-4">
          <v-icon icon="mdi-check-circle-outline" size="26" class="mb-2" />
          <div class="text-h5 font-weight-bold">{{ whatsapp.botFlowsDashboard?.active_bots ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Active Bots</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal" color="warning" class="pa-4">
          <v-icon icon="mdi-play-circle-outline" size="26" class="mb-2" />
          <div class="text-h5 font-weight-bold">{{ whatsapp.botFlowsDashboard?.total_runs ?? 0 }}</div>
          <div class="text-caption text-medium-emphasis">Total Runs</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal" color="info" class="pa-4">
          <v-icon icon="mdi-flag-checkered" size="26" class="mb-2" />
          <div class="text-h5 font-weight-bold">{{ whatsapp.botFlowsDashboard?.completion_rate ?? 0 }}%</div>
          <div class="text-caption text-medium-emphasis">Completion Rate</div>
        </v-card>
      </v-col>
    </v-row>

    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
      <div class="d-flex align-center ga-2">
        <v-icon icon="mdi-layers-outline" />
        <span class="text-subtitle-1 font-weight-medium">Your Bots</span>
        <v-chip size="small" variant="tonal">{{ whatsapp.botFlows.length }}</v-chip>
      </div>
      <v-text-field
        v-model="search" placeholder="Search bots…" prepend-inner-icon="mdi-magnify"
        variant="outlined" density="comfortable" hide-details style="max-width: 260px"
      />
    </div>

    <v-card v-if="! filtered.length" variant="tonal" class="pa-8 text-center" rounded="lg">
      <v-avatar color="primary" variant="tonal" size="64" class="mb-3">
        <v-icon icon="mdi-robot-outline" size="32" />
      </v-avatar>
      <div class="text-h6">No bots yet</div>
      <div class="text-body-2 text-medium-emphasis mb-4">
        Start building your first WhatsApp automation bot in minutes. Choose from templates or create from scratch.
      </div>
      <div class="d-flex justify-center ga-2">
        <AppButton prepend-icon="mdi-plus" @click="create">Create Your First Bot</AppButton>
        <AppButton variant="outlined" prepend-icon="mdi-shopping-outline" @click="marketplace">Browse Templates</AppButton>
      </div>
    </v-card>

    <v-row v-else>
      <v-col v-for="bot in filtered" :key="bot.id" cols="12" sm="6" md="4">
        <v-card class="pa-4" rounded="lg">
          <div class="d-flex align-start justify-space-between mb-2">
            <v-avatar color="primary" variant="tonal" size="44" rounded="lg">
              <v-icon icon="mdi-robot-outline" />
            </v-avatar>
            <v-chip :color="bot.status === 'active' ? 'success' : 'default'" size="small" variant="flat">
              {{ bot.status === 'active' ? 'Active' : 'Draft' }}
            </v-chip>
          </div>
          <div class="text-body-1 font-weight-medium text-truncate">{{ bot.name }}</div>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ (bot.trigger_keywords || []).join(', ') || 'No trigger keywords' }}
          </div>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ bot.run_count ?? 0 }} runs · {{ bot.completion_count ?? 0 }} completed
          </div>
          <div class="d-flex ga-1">
            <v-btn icon="mdi-pencil-outline" size="small" variant="tonal" @click="$emit('open', bot)" />
            <v-btn
              :icon="bot.status === 'active' ? 'mdi-pause' : 'mdi-play'" size="small" variant="tonal"
              :color="bot.status === 'active' ? 'warning' : 'success'" @click="toggleStatus(bot)"
            />
            <v-btn icon="mdi-download-outline" size="small" variant="tonal" @click="exportBot(bot)" />
            <v-btn icon="mdi-delete-outline" size="small" variant="tonal" color="error" @click="remove(bot)" />
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.bot-builder-banner {
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 60%, #ec4899 100%);
}
</style>
