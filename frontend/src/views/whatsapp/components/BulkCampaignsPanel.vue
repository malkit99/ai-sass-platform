<script setup>
import { onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import CampaignsList from './CampaignsList.vue'
import NewCampaignPanel from './NewCampaignPanel.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const view = ref('list') // 'list' | 'new'

const renaming = ref(null)
const renameValue = ref('')
const renamingSaving = ref(false)

onMounted(() => whatsapp.fetchCampaigns())

function editCampaign(campaign) {
  renaming.value = campaign
  renameValue.value = campaign.name
}

async function saveRename() {
  if (!renameValue.value.trim()) return

  renamingSaving.value = true
  try {
    await whatsapp.updateCampaign(renaming.value.id, { name: renameValue.value })
    alertStore.success('Campaign renamed.')
    renaming.value = null
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to rename campaign.')
  } finally {
    renamingSaving.value = false
  }
}

async function pauseCampaign(campaign) {
  try {
    await whatsapp.pauseCampaign(campaign.id)
    alertStore.info(`"${campaign.name}" paused.`)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to pause campaign.')
  }
}

async function resumeCampaign(campaign) {
  try {
    await whatsapp.resumeCampaign(campaign.id)
    alertStore.info(`"${campaign.name}" resumed.`)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to resume campaign.')
  }
}

async function deleteCampaign(campaign) {
  const confirmed = await fireConfirm('Delete this campaign?', `"${campaign.name}" and its recipient records will be permanently removed. Any messages not yet sent will be skipped.`)
  if (!confirmed) return

  await whatsapp.deleteCampaign(campaign.id)
  alertStore.info('Campaign deleted.')
}

function reportCampaign() {
  alertStore.info('A detailed campaign report view is coming in a future update.')
}

function onCreated() {
  view.value = 'list'
  whatsapp.fetchCampaigns()
}
</script>

<template>
  <div>
    <template v-if="view === 'list'">
      <div class="d-flex flex-wrap align-center ga-3 mb-4">
        <div class="flex-grow-1">
          <h2 class="text-h5">Bulk Messaging</h2>
          <div class="text-caption text-medium-emphasis">Send to multiple recipients with anti-ban pacing</div>
        </div>
        <AppButton prepend-icon="mdi-plus" :disabled="!whatsapp.connectedChannels.length" @click="view = 'new'">New Campaign</AppButton>
      </div>

      <v-alert v-if="!whatsapp.connectedChannels.length" type="info" variant="tonal" class="mb-4">
        Connect a WhatsApp account first to run a campaign.
      </v-alert>

      <CampaignsList
        :campaigns="whatsapp.campaigns"
        @edit="editCampaign" @pause="pauseCampaign" @resume="resumeCampaign" @delete="deleteCampaign" @report="reportCampaign"
      />
    </template>

    <NewCampaignPanel v-else :channels="whatsapp.connectedChannels" @back="view = 'list'" @created="onCreated" />

    <AppDialog :model-value="!!renaming" title="Rename Campaign" max-width="420" @update:model-value="renaming = null">
      <v-text-field v-model="renameValue" label="Campaign name" variant="outlined" density="comfortable" autofocus />
      <template #actions>
        <AppButton variant="outlined" @click="renaming = null">Cancel</AppButton>
        <AppButton :loading="renamingSaving" @click="saveRename">Save</AppButton>
      </template>
    </AppDialog>
  </div>
</template>
