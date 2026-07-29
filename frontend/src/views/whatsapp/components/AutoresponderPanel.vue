<script setup>
import { onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import AutoresponderList from './AutoresponderList.vue'
import AutoresponderFormPanel from './AutoresponderFormPanel.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const view = ref('list') // 'list' | 'form'
const editing = ref(null)

onMounted(() => {
  whatsapp.fetchAutoresponders()
  if (!whatsapp.contactGroups.length) whatsapp.fetchContactGroups()
})

function create() {
  editing.value = null
  view.value = 'form'
}

function edit(autoresponder) {
  editing.value = autoresponder
  view.value = 'form'
}

function onSaved() {
  view.value = 'list'
  editing.value = null
}

async function remove(autoresponder) {
  const confirmed = await fireConfirm('Delete autoresponder?', 'This reply will stop sending immediately.')
  if (!confirmed) return

  await whatsapp.deleteAutoresponder(autoresponder.id)
  alertStore.info('Autoresponder deleted.')
}
</script>

<template>
  <div>
    <template v-if="view === 'list'">
      <div class="d-flex flex-wrap align-center ga-3 mb-4">
        <div class="flex-grow-1">
          <h2 class="text-h5">Autoresponder</h2>
          <div class="text-caption text-medium-emphasis">Send a pre-written reply to any inbound message</div>
        </div>
        <AppButton prepend-icon="mdi-plus" @click="create">New Autoresponder</AppButton>
      </div>

      <AutoresponderList :autoresponders="whatsapp.autoresponders" :channels="whatsapp.channels" @edit="edit" @delete="remove" />
    </template>

    <AutoresponderFormPanel
      v-else :channels="whatsapp.channels" :contact-groups="whatsapp.contactGroups" :editing="editing"
      @back="view = 'list'" @saved="onSaved"
    />
  </div>
</template>
