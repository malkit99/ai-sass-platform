<script setup>
import { ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  groupId: { type: [Number, String], required: true },
})

const emit = defineEmits(['update:modelValue', 'imported'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const channelId = ref(null)
const importing = ref(false)

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) channelId.value = whatsapp.channels.find((c) => c.status === 'connected')?.id ?? null
  },
)

async function importNow() {
  if (!channelId.value) {
    alertStore.warning('Select a connected WhatsApp account.')
    return
  }

  importing.value = true
  try {
    const result = await whatsapp.importContactsFromWhatsapp(props.groupId, channelId.value)
    alertStore.success(`Imported ${result.imported}, updated ${result.updated} from WhatsApp.`)
    emit('imported')
    emit('update:modelValue', false)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Import from WhatsApp failed.')
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" max-width="440" @update:model-value="$emit('update:modelValue', $event)">
    <template #header>
      <div class="import-wa-banner d-flex align-center ga-4 pa-5">
        <v-avatar color="white" variant="tonal" size="44"><v-icon icon="mdi-whatsapp" color="white" /></v-avatar>
        <div>
          <div class="text-h6 text-white">Import WhatsApp Contacts</div>
          <div class="text-body-2" style="color: rgba(255, 255, 255, 0.85)">Sync your phonebook into this contact group</div>
        </div>
      </div>
    </template>

    <v-card variant="tonal" class="pa-4 mb-4">
      <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-1">
        <v-icon icon="mdi-account-outline" size="14" />WHATSAPP ACCOUNT
      </div>
      <v-select
        v-model="channelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
        variant="outlined" density="comfortable" hide-details
        :placeholder="whatsapp.channels.length ? 'Select an instance' : 'No WhatsApp accounts connected'"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item v-bind="itemProps" :disabled="item.raw.status !== 'connected'">
            <template #append><v-chip size="x-small" :color="item.raw.status === 'connected' ? 'success' : 'default'">{{ item.raw.status }}</v-chip></template>
          </v-list-item>
        </template>
      </v-select>
    </v-card>

    <v-alert type="info" variant="tonal" density="compact" icon="mdi-lightbulb-outline">
      All saved contacts from your WhatsApp phonebook will be imported. Duplicate numbers are automatically skipped.
    </v-alert>

    <template #actions>
      <AppButton variant="outlined" @click="$emit('update:modelValue', false)">Close</AppButton>
      <AppButton color="success" prepend-icon="mdi-whatsapp" :loading="importing" @click="importNow">Import Now</AppButton>
    </template>
  </AppDialog>
</template>

<style scoped>
.import-wa-banner {
  background: linear-gradient(120deg, #1fb972, #0d8b52);
}
</style>
