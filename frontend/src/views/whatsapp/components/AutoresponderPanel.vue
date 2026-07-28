<script setup>
import { onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import AutoresponderList from './AutoresponderList.vue'
import AutoresponderDialog from './AutoresponderDialog.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const showDialog = ref(false)
const editing = ref(null)

onMounted(() => whatsapp.fetchAutoresponders())

function create() {
  editing.value = null
  showDialog.value = true
}

function edit(autoresponder) {
  editing.value = autoresponder
  showDialog.value = true
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
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">Autoresponder</h2>
        <div class="text-caption text-medium-emphasis">Send a pre-written reply to any inbound message</div>
      </div>
      <AppButton prepend-icon="mdi-plus" @click="create">New Autoresponder</AppButton>
    </div>

    <AutoresponderList :autoresponders="whatsapp.autoresponders" :channels="whatsapp.channels" @edit="edit" @delete="remove" />

    <AutoresponderDialog v-model="showDialog" :channels="whatsapp.channels" :editing="editing" />
  </div>
</template>
