<script setup>
import { onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import ChatbotRulesList from './ChatbotRulesList.vue'
import ChatbotRuleDialog from './ChatbotRuleDialog.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const showDialog = ref(false)
const editing = ref(null)

onMounted(() => whatsapp.fetchChatbotRules())

function create() {
  editing.value = null
  showDialog.value = true
}

function edit(rule) {
  editing.value = rule
  showDialog.value = true
}

async function remove(rule) {
  const confirmed = await fireConfirm('Delete chatbot rule?', `"${rule.name}" will stop matching immediately.`)
  if (!confirmed) return

  await whatsapp.deleteChatbotRule(rule.id)
  alertStore.info('Chatbot rule deleted.')
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">Chatbot</h2>
        <div class="text-caption text-medium-emphasis">Reply automatically when a message matches a keyword</div>
      </div>
      <AppButton prepend-icon="mdi-plus" @click="create">New Rule</AppButton>
    </div>

    <ChatbotRulesList :rules="whatsapp.chatbotRules" :channels="whatsapp.channels" @edit="edit" @delete="remove" />

    <ChatbotRuleDialog v-model="showDialog" :channels="whatsapp.channels" :editing="editing" />
  </div>
</template>
