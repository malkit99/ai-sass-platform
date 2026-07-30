<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import WhatsappFeatureNav from './components/WhatsappFeatureNav.vue'
import WhatsappDashboardPanel from './components/WhatsappDashboardPanel.vue'
import ChannelsList from './components/ChannelsList.vue'
import ProfilePanel from './components/ProfilePanel.vue'
import TemplatesPanel from './components/TemplatesPanel.vue'
import BulkCampaignsPanel from './components/BulkCampaignsPanel.vue'
import AutoresponderPanel from './components/AutoresponderPanel.vue'
import ChatbotPanel from './components/ChatbotPanel.vue'
import LinkGeneratorPanel from './components/LinkGeneratorPanel.vue'
import GroupsPanel from './components/GroupsPanel.vue'
import CallResponderPanel from './components/CallResponderPanel.vue'
import MessageHistoryPanel from './components/MessageHistoryPanel.vue'
import FormsModulePanel from './components/FormsModulePanel.vue'
import ApiPanel from './components/ApiPanel.vue'
import BotBuilderModulePanel from './components/BotBuilderModulePanel.vue'
import ConnectAccountPanel from './components/ConnectAccountPanel.vue'
import SendMessagePanel from './components/SendMessagePanel.vue'
import ContactsPanel from './components/ContactsPanel.vue'
import { fireConfirm } from '@core/plugins/sweetalert'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const route = useRoute()

// Set when returning from the standalone bot-flow editor route (see
// router/routes/botFlowEditor.js), so "Back" lands on Bot Builder instead
// of resetting to the WhatsApp Analytics dashboard.
const active = ref(route.query.feature || 'dashboard')
const activeChannel = ref(null)

onMounted(async () => {
  await whatsapp.fetchChannels()
  await whatsapp.fetchDashboard()
})

async function addAccount() {
  if (whatsapp.channelLimit !== null && whatsapp.channels.length >= whatsapp.channelLimit) {
    alertStore.warning(`You've reached your plan's WhatsApp account limit (${whatsapp.channelLimit}).`)
    return
  }

  activeChannel.value = await whatsapp.createChannel(null)
  active.value = 'connect'
}

function connectExisting(channel) {
  activeChannel.value = channel
  active.value = 'connect'
}

function openSend() {
  active.value = 'send'
}

async function disconnect(channel) {
  await whatsapp.disconnectChannel(channel.id)
  alertStore.info(`${channel.name || 'Account'} disconnected.`)
}

async function deleteChannel(channel) {
  const confirmed = await fireConfirm(
    'Delete this WhatsApp account?',
    `"${channel.display_name}" and its conversation history will be permanently removed. This can't be undone.`,
  )
  if (!confirmed) return

  await whatsapp.deleteChannel(channel.id)
  await whatsapp.fetchDashboard()
  alertStore.info('WhatsApp account deleted.')
}

async function onConnected() {
  await whatsapp.fetchChannels()
  await whatsapp.fetchDashboard()
  active.value = 'accounts'
}
</script>

<template>
  <div class="d-flex" style="height: 100%">
    <WhatsappFeatureNav
      :active="active"
      :channel-count="whatsapp.channels.length"
      :channel-limit="whatsapp.channelLimit"
      @update:active="active = $event"
      @add-account="addAccount"
    />

    <v-container fluid class="pa-4 pa-sm-6" style="overflow-y: auto">
      <WhatsappDashboardPanel
        v-if="active === 'dashboard'"
        :dashboard="whatsapp.dashboard"
        @send-message="openSend"
        @bulk-send="active = 'bulk'"
        @view-campaigns="active = 'bulk'"
        @manage-autoresponders="active = 'autoresponder'"
        @manage-chatbot="active = 'chatbot'"
      />

      <div v-else-if="active === 'accounts'">
        <div class="mb-4">
          <h2 class="text-h5">WhatsApp Accounts</h2>
          <div class="text-caption text-medium-emphasis">Connect a number and send messages (unofficial QR-session channel)</div>
        </div>
        <ChannelsList
          :channels="whatsapp.channels"
          :limit="whatsapp.channelLimit"
          :loading="whatsapp.loading"
          @connect="connectExisting"
          @send="openSend"
          @disconnect="disconnect"
          @delete="deleteChannel"
        />
      </div>

      <SendMessagePanel
        v-else-if="active === 'send'" :channels="whatsapp.channels"
        @connect-channel="connectExisting"
      />
      <ProfilePanel v-else-if="active === 'profile'" />
      <TemplatesPanel v-else-if="active === 'templates'" />
      <ContactsPanel v-else-if="active === 'contacts'" />

      <BulkCampaignsPanel v-else-if="active === 'bulk'" @connect-channel="connectExisting" />
      <AutoresponderPanel v-else-if="active === 'autoresponder'" />
      <ChatbotPanel v-else-if="active === 'chatbot'" @connect-channel="connectExisting" />
      <LinkGeneratorPanel v-else-if="active === 'links'" />
      <GroupsPanel v-else-if="active === 'groups'" />
      <CallResponderPanel v-else-if="active === 'call-responder'" />
      <MessageHistoryPanel v-else-if="active === 'message-history'" />
      <FormsModulePanel v-else-if="active === 'forms'" />
      <ApiPanel v-else-if="active === 'api'" />
      <BotBuilderModulePanel v-else-if="active === 'bot-builder'" />

      <ConnectAccountPanel
        v-else-if="active === 'connect'"
        :channel="activeChannel"
        @back="active = 'accounts'"
        @connected="onConnected"
      />
    </v-container>
  </div>
</template>
