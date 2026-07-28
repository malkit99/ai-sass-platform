<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireSuccess } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import MediaPickerDialog from './template/MediaPickerDialog.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
})

const emit = defineEmits(['connect-channel'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const checkingStatus = ref(true)
const showPicker = ref(false)
const messageType = ref('text') // text|media|template
const selectedTemplateId = ref(null)
const bodyTextareaRef = ref(null)

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }

const selectedChannel = computed(() => props.channels.find((c) => c.id === channelId.value) ?? null)
const selectedChannelDisconnected = computed(() => selectedChannel.value && selectedChannel.value.status !== 'connected')

// Only types this platform can actually send today (see WhatsappTemplate::SENDABLE_TYPES).
const SENDABLE_TYPES = ['text', 'text_image', 'text_video', 'text_document', 'text_audio']
const sendableTemplates = computed(() => whatsapp.templates.filter((t) => SENDABLE_TYPES.includes(t.type)))

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().required('Select a WhatsApp account'),
    phone: yup.string().required('Phone number is required').test(
      'valid-phone',
      'Enter a valid mobile number with country code (no + sign)',
      (value) => (value ? (parsePhoneNumberFromString(`+${value}`)?.isValid() ?? false) : false),
    ),
    body: yup.string().nullable(),
    media_url: yup.string().nullable().url('Enter a valid URL'),
  }),
)

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { channel_id: props.channels[0]?.id ?? null, phone: '', body: '', media_url: '' },
})

const [channelId, channelIdAttrs] = defineField('channel_id')
const [phone, phoneAttrs] = defineField('phone')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')

onMounted(async () => {
  if (!whatsapp.templates.length) whatsapp.fetchTemplates()

  // The store's cached status can be stale (e.g. after a bridge restart that
  // never fired a disconnect webhook) — refresh each channel's live status
  // before trusting the list, instead of assuming "connected" means connected.
  checkingStatus.value = true
  try {
    await Promise.all(
      props.channels.map(async (c) => {
        try {
          const status = await whatsapp.fetchStatus(c.id)
          whatsapp.updateChannelStatus(c.id, status)
        } catch {
          // Bridge unreachable — leave the cached status as-is rather than
          // failing the whole page over one instance.
        }
      }),
    )
  } finally {
    checkingStatus.value = false
  }
})

watch(messageType, (t) => {
  if (t !== 'template') selectedTemplateId.value = null
})

watch(selectedTemplateId, (id) => {
  const template = sendableTemplates.value.find((t) => t.id === id)
  if (!template) return
  body.value = template.body ?? ''
  mediaUrl.value = template.media_url ?? ''
})

function getTextareaEl() {
  return bodyTextareaRef.value?.$el?.querySelector('textarea') ?? null
}

function wrapSelection(before, after = before) {
  const el = getTextareaEl()
  if (!el) return

  const start = el.selectionStart
  const end = el.selectionEnd
  const current = body.value ?? ''
  const selected = current.substring(start, end)

  body.value = current.substring(0, start) + before + selected + after + current.substring(end)

  nextTick(() => {
    el.focus()
    el.setSelectionRange(start + before.length, start + before.length + selected.length)
  })
}

function insertVariable(token) {
  const el = getTextareaEl()
  const current = body.value ?? ''
  const pos = el ? el.selectionStart : current.length

  body.value = current.substring(0, pos) + token + current.substring(pos)

  nextTick(() => {
    el?.focus()
    el?.setSelectionRange(pos + token.length, pos + token.length)
  })
}

const submit = handleSubmit(async (values) => {
  if (selectedChannelDisconnected.value) {
    alertStore.warning('This WhatsApp account is disconnected. Reconnect it first.')
    return
  }

  if (messageType.value === 'template' && !selectedTemplateId.value) {
    alertStore.warning('Select a template to send.')
    return
  }

  if (messageType.value === 'text' && !values.body) {
    setErrors({ body: 'Message is required' })
    return
  }
  if (messageType.value === 'media' && !values.media_url) {
    setErrors({ media_url: 'Media URL is required' })
    return
  }

  // A template is sent by reference — the server looks up its stored
  // type/body/media_url itself, rather than trusting whatever this form
  // echoed back into the body/media_url fields (which only exist here for
  // preview and lose the distinction between an image/video/document/audio
  // template that flattening them into a generic "media" send caused).
  const payload = messageType.value === 'template'
    ? { channel_id: values.channel_id, phone: values.phone, type: 'template', template_id: selectedTemplateId.value }
    : { channel_id: values.channel_id, phone: values.phone, type: messageType.value, body: values.body, media_url: values.media_url }

  saving.value = true
  try {
    await whatsapp.sendMessage(payload)
    fireSuccess('Message sent', `Your message to ${values.phone} was sent.`)
    resetForm({ values: { channel_id: values.channel_id, phone: '', body: '', media_url: '' } })
    messageType.value = 'text'
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to send message.')
    }
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <div>
    <div class="send-banner d-flex align-center ga-4 pa-6 mb-4">
      <v-avatar color="white" variant="tonal" size="48"><v-icon icon="mdi-send" color="white" /></v-avatar>
      <div>
        <div class="text-h6 text-white">Send Single Message</div>
        <div class="text-body-2" style="color: rgba(255, 255, 255, 0.85)">Quickly send a WhatsApp message to any number</div>
      </div>
    </div>

    <v-card class="pa-6">
      <v-form @submit.prevent="submit">
        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-1">
          <v-icon icon="mdi-cellphone-link" size="14" />SELECT INSTANCE
          <v-progress-circular v-if="checkingStatus" size="12" width="2" indeterminate class="ml-1" />
        </div>
        <v-select
          v-model="channelId" v-bind="channelIdAttrs" :items="channels" item-title="display_name" item-value="id"
          placeholder="Select an instance" variant="outlined" density="comfortable" :error-messages="errors.channel_id" class="mb-2"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps">
              <template #title>{{ item.raw.display_name }}</template>
              <template #append>
                <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ item.raw.status }}</v-chip>
              </template>
            </v-list-item>
          </template>
          <template #selection="{ item }">{{ item.raw.display_name }}</template>
        </v-select>

        <v-alert v-if="selectedChannelDisconnected" type="warning" variant="tonal" density="compact" class="mb-4">
          <div class="d-flex align-center justify-space-between ga-2">
            <span class="text-body-2">This account is disconnected — you can't send from it right now.</span>
            <AppButton size="small" variant="flat" color="warning" @click="$emit('connect-channel', selectedChannel)">
              Connect now
            </AppButton>
          </div>
        </v-alert>

        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-1">
          <v-icon icon="mdi-phone-outline" size="14" />PHONE NUMBER <span class="ml-1">with country code</span>
        </div>
        <v-text-field
          :model-value="phone" v-bind="phoneAttrs" placeholder="e.g. 911234567890" variant="outlined" density="comfortable"
          prepend-inner-icon="mdi-earth" :error-messages="errors.phone" class="mb-4"
          @update:model-value="(val) => (phone = val.replace(/\D/g, ''))"
        />

        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-1">
          <v-icon icon="mdi-message-outline" size="14" />MESSAGE TYPE
        </div>
        <v-btn-toggle v-model="messageType" mandatory density="comfortable" class="mb-4 message-type-toggle" divided>
          <v-btn value="text" prepend-icon="mdi-format-text">Text</v-btn>
          <v-btn value="media" prepend-icon="mdi-image-outline">Media</v-btn>
          <v-btn value="template" prepend-icon="mdi-file-document-multiple-outline">Template</v-btn>
        </v-btn-toggle>

        <template v-if="messageType === 'template'">
          <div class="text-caption text-medium-emphasis mb-1">TEMPLATE</div>
          <v-select
            v-model="selectedTemplateId" :items="sendableTemplates" item-title="name" item-value="id"
            placeholder="Select a template" variant="outlined" density="comfortable" class="mb-4"
          />
        </template>

        <template v-if="messageType === 'media' || (messageType === 'template' && selectedTemplateId)">
          <div class="text-caption text-medium-emphasis mb-1">MEDIA URL</div>
          <div class="d-flex ga-2 mb-4">
            <v-text-field
              v-model="mediaUrl" v-bind="mediaUrlAttrs" placeholder="https://…" variant="outlined" density="comfortable"
              :error-messages="errors.media_url" :readonly="messageType === 'template'"
            />
            <AppButton v-if="messageType === 'media'" variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showPicker = true">
              Browse
            </AppButton>
          </div>
          <MediaPickerDialog v-model="showPicker" type="image" @selected="mediaUrl = $event" />
        </template>

        <div class="d-flex align-center justify-space-between mb-1">
          <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
            <v-icon icon="mdi-pencil-outline" size="14" />MESSAGE / CAPTION
          </div>
          <span class="text-caption text-medium-emphasis">{{ (body ?? '').length }} / 4096</span>
        </div>
        <v-textarea
          ref="bodyTextareaRef" v-model="body" v-bind="bodyAttrs" placeholder="Type your message here..."
          variant="outlined" rows="6" auto-grow maxlength="4096" :error-messages="errors.body"
          :readonly="messageType === 'template'"
        />

        <div class="d-flex ga-1 mb-6">
          <v-btn size="small" variant="tonal" @click="wrapSelection('*')"><strong>B</strong></v-btn>
          <v-btn size="small" variant="tonal" @click="wrapSelection('_')"><em>I</em></v-btn>
          <v-btn size="small" variant="tonal" @click="wrapSelection('~')"><s>S</s></v-btn>
          <v-btn size="small" variant="tonal" @click="insertVariable('{{name}}')">{{ '{}' }}</v-btn>
        </div>

        <AppButton block size="x-large" :loading="saving" prepend-icon="mdi-send" class="send-button" @click="submit">
          Send Message
        </AppButton>
      </v-form>
    </v-card>
  </div>
</template>

<style scoped>
.send-banner {
  border-radius: 12px;
  background: linear-gradient(120deg, #6a4cf0, #d24cd6);
}

.message-type-toggle :deep(.v-btn) {
  text-transform: none;
}

.send-button {
  background: linear-gradient(120deg, #6a4cf0, #d24cd6) !important;
  color: white !important;
}
</style>
