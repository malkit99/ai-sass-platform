<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import ChatbotRulesList from './ChatbotRulesList.vue'
import ChatbotRuleFormPanel from './ChatbotRuleFormPanel.vue'

defineEmits(['connect-channel'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const view = ref('dashboard') // 'dashboard' | 'list' | 'form'
const editing = ref(null)
const formOrigin = ref('dashboard') // where 'back'/'saved' returns to

onMounted(() => {
  whatsapp.fetchChatbotRules()
  whatsapp.fetchDashboard()
  if (!whatsapp.contactGroups.length) whatsapp.fetchContactGroups()
})

const itemsCount = computed(() => whatsapp.dashboard?.chatbot_items_count ?? whatsapp.chatbotRules.length)
const sentCount = computed(() => whatsapp.dashboard?.chatbot_sent_count ?? 0)
const hasActiveItem = computed(() => whatsapp.chatbotRules.some((r) => r.enabled))

function create() {
  editing.value = null
  formOrigin.value = view.value === 'list' ? 'list' : 'dashboard'
  view.value = 'form'
}

function edit(rule) {
  editing.value = rule
  formOrigin.value = 'list'
  view.value = 'form'
}

async function onSaved() {
  view.value = formOrigin.value
  editing.value = null
  await whatsapp.fetchDashboard()
}

async function remove(rule) {
  const confirmed = await fireConfirm('Delete chatbot item?', `"${rule.name}" will stop matching immediately.`)
  if (!confirmed) return

  await whatsapp.deleteChatbotRule(rule.id)
  await whatsapp.fetchDashboard()
  alertStore.info('Chatbot item deleted.')
}
</script>

<template>
  <div>
    <template v-if="view === 'dashboard'">
      <div class="d-flex flex-wrap align-center ga-3 mb-4">
        <div class="flex-grow-1">
          <h2 class="text-h5">Chatbot</h2>
          <div class="text-caption text-medium-emphasis">Reply automatically when a message matches a keyword</div>
        </div>
      </div>

      <v-card class="pa-6">
        <div class="d-flex justify-end mb-2">
          <v-icon icon="mdi-robot-outline" size="56" color="secondary" />
        </div>

        <v-row class="mb-4">
          <v-col cols="12" sm="6">
            <v-card variant="tonal" color="success" class="pa-4 text-center">
              <v-icon icon="mdi-send-outline" size="28" class="mb-1" />
              <div class="text-h4 font-weight-bold">{{ sentCount }}</div>
              <div class="text-caption">Sent</div>
            </v-card>
          </v-col>
          <v-col cols="12" sm="6">
            <v-card variant="tonal" color="error" class="pa-4 text-center">
              <v-icon icon="mdi-robot-outline" size="28" class="mb-1" />
              <div class="text-h4 font-weight-bold">{{ itemsCount }}</div>
              <div class="text-caption">Items</div>
            </v-card>
          </v-col>
        </v-row>

        <v-card v-if="!hasActiveItem" variant="tonal" class="pa-4 text-center mb-4">
          Please add at least a chatbot item and enable it to can start
        </v-card>

        <div class="d-flex flex-wrap ga-3">
          <AppButton prepend-icon="mdi-plus" @click="create">Add item</AppButton>
          <AppButton variant="tonal" prepend-icon="mdi-format-list-bulleted" @click="view = 'list'">Item list</AppButton>
        </div>
      </v-card>
    </template>

    <template v-else-if="view === 'list'">
      <div class="d-flex flex-wrap align-center ga-3 mb-4">
        <v-btn icon="mdi-arrow-left" variant="text" @click="view = 'dashboard'" />
        <div class="flex-grow-1">
          <h2 class="text-h5">Chatbot items</h2>
          <div class="text-caption text-medium-emphasis">Reply automatically when a message matches a keyword</div>
        </div>
        <AppButton prepend-icon="mdi-plus" @click="create">Add item</AppButton>
      </div>

      <ChatbotRulesList :rules="whatsapp.chatbotRules" :channels="whatsapp.channels" @edit="edit" @delete="remove" />
    </template>

    <ChatbotRuleFormPanel
      v-else :channels="whatsapp.channels" :contact-groups="whatsapp.contactGroups" :editing="editing"
      @back="view = formOrigin" @saved="onSaved" @connect-channel="$emit('connect-channel', $event)"
    />
  </div>
</template>
