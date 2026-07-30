<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BotBuilderDashboard from './BotBuilderDashboard.vue'
import CreateBotChoice from './CreateBotChoice.vue'
import BotTemplatesMarketplace from './BotTemplatesMarketplace.vue'

// 'builder' is deliberately not a state here — the flow editor (screenshots
// 59-62) is a dedicated full-screen route with no app nav/WhatsApp feature
// sidebar (see router/routes/botFlowEditor.js), unlike dashboard/create/
// marketplace which stay nested panels like every other WhatsApp feature.
const view = ref('dashboard') // 'dashboard' | 'create' | 'marketplace'
const channelId = ref(null)

const router = useRouter()

function openCreate(selectedChannelId) {
  channelId.value = selectedChannelId
  view.value = 'create'
}

function openBuilder(bot) {
  router.push({ name: 'bot-flow-editor', params: { id: bot.id } })
}

function openMarketplace(selectedChannelId) {
  channelId.value = selectedChannelId
  view.value = 'marketplace'
}

function backToDashboard() {
  view.value = 'dashboard'
}
</script>

<template>
  <BotBuilderDashboard
    v-if="view === 'dashboard'"
    @create="openCreate" @open="openBuilder" @marketplace="openMarketplace"
  />
  <CreateBotChoice
    v-else-if="view === 'create'" :channel-id="channelId"
    @back="backToDashboard" @created="openBuilder" @marketplace="view = 'marketplace'"
  />
  <BotTemplatesMarketplace
    v-else-if="view === 'marketplace'" :channel-id="channelId"
    @back="backToDashboard" @created="openBuilder"
  />
</template>
