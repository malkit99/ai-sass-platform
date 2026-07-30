<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import SavedLinksList from './SavedLinksList.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const saving = ref(false)
const loadingLinks = ref(false)
const generatedLink = ref('')

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().required('Select an account'),
    reference_name: yup.string().required('Reference name is required'),
    phone: yup.string().required('Phone number is required').test(
      'valid-phone',
      'Enter a valid mobile number with country code (no + sign)',
      (value) => (value ? (parsePhoneNumberFromString(`+${value}`)?.isValid() ?? false) : false),
    ),
    message: yup.string().nullable(),
  }),
)

const { defineField, handleSubmit, errors, setErrors, resetField } = useForm({
  validationSchema: schema,
  initialValues: { channel_id: null, reference_name: '', phone: '', message: '' },
})

const [channelId] = defineField('channel_id')
const [referenceName, referenceNameAttrs] = defineField('reference_name')
const [phone, phoneAttrs] = defineField('phone')
const [message, messageAttrs] = defineField('message')

watch(channelId, async (id) => {
  generatedLink.value = ''
  if (!id) {
    whatsapp.shortLinks = []
    return
  }

  loadingLinks.value = true
  try {
    await whatsapp.fetchShortLinks(id)
  } finally {
    loadingLinks.value = false
  }
})

function copyGeneratedLink() {
  if (!generatedLink.value) return
  navigator.clipboard.writeText(generatedLink.value)
  alertStore.info('Link copied.')
}

const submit = handleSubmit(async (values) => {
  saving.value = true
  try {
    const link = await whatsapp.createShortLink(values)
    generatedLink.value = link.short_url
    alertStore.success('Link generated and saved.')
    resetField('reference_name')
    resetField('phone')
    resetField('message')
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to generate link.')
    }
  } finally {
    saving.value = false
  }
})

async function remove(link) {
  const confirmed = await fireConfirm('Delete this link?', `"${link.reference_name}" will be removed from your saved links.`)
  if (!confirmed) return

  await whatsapp.deleteShortLink(link.id)
  alertStore.info('Link deleted.')
}

const channelSelected = computed(() => !!channelId.value)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="text-h5">Link Generator</h2>
      <div class="text-caption text-medium-emphasis">Create a WhatsApp click-to-chat link for bios, ads, or QR codes</div>
    </div>

    <v-row>
      <v-col cols="12" md="5">
        <v-card class="pa-6">
          <div class="d-flex align-center ga-2 mb-4">
            <v-icon icon="mdi-link-variant" color="primary" />
            <span class="text-h6">Generate Link</span>
          </div>

          <v-form @submit.prevent="submit">
            <div class="text-caption text-medium-emphasis mb-1">SELECT ACCOUNT</div>
            <v-select
              v-model="channelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
              placeholder="Choose account…" variant="outlined" density="comfortable" :error-messages="errors.channel_id" class="mb-4"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps">
                  <template #title>{{ item.raw.display_name }}</template>
                  <template #append>
                    <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
                  </template>
                </v-list-item>
              </template>
              <template #selection="{ item }">
                {{ item.raw.display_name }}
                <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat" class="ml-2">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
              </template>
            </v-select>

            <div class="text-caption text-medium-emphasis mb-1">REFERENCE NAME</div>
            <v-text-field
              v-model="referenceName" v-bind="referenceNameAttrs" placeholder="E.g. Customer Support"
              variant="outlined" density="comfortable" :error-messages="errors.reference_name" class="mb-4"
            />

            <div class="text-caption text-medium-emphasis mb-1">PHONE NUMBER <span class="text-medium-emphasis">with country code</span></div>
            <v-text-field
              :model-value="phone" v-bind="phoneAttrs" placeholder="E.g. 12125550123" prepend-inner-icon="mdi-earth"
              variant="outlined" density="comfortable" :error-messages="errors.phone" class="mb-4"
              @update:model-value="(val) => (phone = val.replace(/\D/g, ''))"
            />

            <div class="text-caption text-medium-emphasis mb-1">CUSTOM MESSAGE</div>
            <v-textarea
              v-model="message" v-bind="messageAttrs" placeholder="Hello, I need help with…"
              variant="outlined" rows="3" auto-grow class="mb-4"
            />

            <div class="text-caption text-medium-emphasis mb-1">GENERATED LINK</div>
            <div class="d-flex ga-2 mb-4">
              <v-text-field :model-value="generatedLink" readonly variant="outlined" density="comfortable" placeholder="—" />
              <v-btn icon="mdi-content-copy" variant="tonal" :disabled="!generatedLink" @click="copyGeneratedLink" />
            </div>

            <AppButton block :loading="saving" prepend-icon="mdi-plus-circle-outline" @click="submit">Save & Generate Link</AppButton>
          </v-form>
        </v-card>
      </v-col>

      <v-col cols="12" md="7">
        <v-card class="pa-6">
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-format-list-bulleted" color="primary" />
              <span class="text-h6">Saved Links</span>
            </div>
            <v-btn
              icon="mdi-refresh" variant="outlined" size="small" :loading="loadingLinks"
              :disabled="!channelSelected" @click="whatsapp.fetchShortLinks(channelId)"
            />
          </div>

          <SavedLinksList :links="whatsapp.shortLinks" :channel-selected="channelSelected" @delete="remove" />
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
