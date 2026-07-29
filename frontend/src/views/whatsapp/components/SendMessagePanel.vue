<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireSuccess } from '@core/plugins/sweetalert'
import { templateTypeMeta } from '@core/utils/whatsappTemplateTypes'
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

// Same tab structure as the bulk campaign form (NewCampaignPanel) — Text and
// Media as their own top-level tabs (no combined Text/Media tab with a
// sub-toggle), plus one filter tab per template kind. Text is the default.
const messageTab = ref('text') // 'text' | 'media' | 'text_template' | 'buttons' | 'list' | 'poll' | 'template'
const selectedTemplateId = ref(null)
const bodyTextareaRef = ref(null)

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }

const selectedChannel = computed(() => props.channels.find((c) => c.id === channelId.value) ?? null)
const selectedChannelDisconnected = computed(() => selectedChannel.value && selectedChannel.value.status !== 'connected')

const isFreeform = computed(() => messageTab.value === 'text' || messageTab.value === 'media')

// Only types this platform can actually send today (see WhatsappTemplate::ALL_SENDABLE_TYPES).
// Carousel isn't included — it needs a protocol this bridge doesn't support.
const SENDABLE_TYPES = [
  'text', 'text_image', 'text_video', 'text_document', 'text_audio',
  'text_buttons', 'text_lists', 'text_poll', 'interactive_buttons',
]

// Buttons/list are newly re-enabled on the switched bridge engine and not yet
// confirmed rendering on a real device — flagged in the UI until they are.
const BEST_EFFORT_TYPES = ['text_buttons', 'interactive_buttons', 'text_lists']

const filteredTemplates = computed(() => {
  if (messageTab.value === 'text_template') return whatsapp.templates.filter((t) => t.type === 'text')
  if (messageTab.value === 'buttons') return whatsapp.templates.filter((t) => ['text_buttons', 'interactive_buttons'].includes(t.type))
  if (messageTab.value === 'list') return whatsapp.templates.filter((t) => t.type === 'text_lists')
  if (messageTab.value === 'poll') return whatsapp.templates.filter((t) => t.type === 'text_poll')
  if (messageTab.value === 'template') return whatsapp.templates

  return []
})

const selectedTemplate = computed(() => filteredTemplates.value.find((t) => t.id === selectedTemplateId.value) ?? null)
const selectedTemplateUnsendable = computed(() => selectedTemplate.value && !SENDABLE_TYPES.includes(selectedTemplate.value.type))
const selectedTemplateBestEffort = computed(() => selectedTemplate.value && BEST_EFFORT_TYPES.includes(selectedTemplate.value.type))

// {{...}} placeholders found in the selected template's body — one input each
// so the sender types the real values ({{phone}} fills itself from the
// recipient number, so it's excluded). Bulk campaigns don't need this: they
// resolve variables per-recipient from the contact table instead.
const variableValues = ref({})
const templateVariables = computed(() => {
  const text = selectedTemplate.value?.body ?? ''
  const names = [...text.matchAll(/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/g)].map((m) => m[1])

  return [...new Set(names)].filter((name) => name !== 'phone')
})

watch(selectedTemplateId, () => { variableValues.value = {} })

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

watch(messageTab, () => { selectedTemplateId.value = null })

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

  let payload

  if (isFreeform.value) {
    if (messageTab.value === 'text' && !values.body) {
      setErrors({ body: 'Message is required' })
      return
    }
    if (messageTab.value === 'media' && !values.media_url) {
      setErrors({ media_url: 'Media URL is required' })
      return
    }

    payload = { channel_id: values.channel_id, phone: values.phone, type: messageTab.value, body: values.body, media_url: values.media_url }
  } else {
    if (!selectedTemplateId.value) {
      alertStore.warning('Select a template.')
      return
    }
    if (selectedTemplateUnsendable.value) {
      alertStore.warning('This template type can\'t be sent yet.')
      return
    }

    // Sent by reference — the server resolves the template's own stored
    // type/body/media/interactive config itself (see MessageController).
    payload = { channel_id: values.channel_id, phone: values.phone, type: 'template', template_id: selectedTemplateId.value }

    if (templateVariables.value.length) {
      payload.variables = variableValues.value
    }
  }

  saving.value = true
  try {
    await whatsapp.sendMessage(payload)
    fireSuccess('Message sent', `Your message to ${values.phone} was sent.`)
    resetForm({ values: { channel_id: values.channel_id, phone: '', body: '', media_url: '' } })
    messageTab.value = 'text'
    selectedTemplateId.value = null
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
        <v-btn-toggle v-model="messageTab" mandatory density="compact" class="mb-4 message-type-toggle flex-wrap" divided>
          <v-btn size="small" value="text" prepend-icon="mdi-format-text">Text</v-btn>
          <v-btn size="small" value="media" prepend-icon="mdi-image-outline">Media</v-btn>
          <v-btn size="small" value="text_template" prepend-icon="mdi-text-box-outline">Text template</v-btn>
          <v-btn size="small" value="buttons" prepend-icon="mdi-gesture-tap-button">Buttons</v-btn>
          <v-btn size="small" value="list" prepend-icon="mdi-format-list-bulleted">List message</v-btn>
          <v-btn size="small" value="poll" prepend-icon="mdi-poll">Poll</v-btn>
          <v-btn size="small" value="template" prepend-icon="mdi-file-document-multiple-outline">Template</v-btn>
        </v-btn-toggle>

        <template v-if="isFreeform">
          <template v-if="messageTab === 'media'">
            <div class="text-caption text-medium-emphasis mb-1">MEDIA URL</div>
            <div class="d-flex ga-2 mb-4">
              <v-text-field
                v-model="mediaUrl" v-bind="mediaUrlAttrs" placeholder="https://…" variant="outlined" density="comfortable"
                :error-messages="errors.media_url"
              />
              <AppButton variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showPicker = true">Browse</AppButton>
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
          />

          <div class="d-flex ga-1 mb-6">
            <v-btn size="small" variant="tonal" @click="wrapSelection('*')"><strong>B</strong></v-btn>
            <v-btn size="small" variant="tonal" @click="wrapSelection('_')"><em>I</em></v-btn>
            <v-btn size="small" variant="tonal" @click="wrapSelection('~')"><s>S</s></v-btn>
            <v-btn size="small" variant="tonal" @click="insertVariable('{{name}}')">{{ '{}' }}</v-btn>
          </div>
        </template>

        <template v-else>
          <v-alert v-if="!filteredTemplates.length" type="info" variant="tonal" density="compact" class="mb-4">
            No saved templates of this type yet — create one under Templates first.
          </v-alert>
          <v-radio-group v-else v-model="selectedTemplateId" class="mb-2">
            <v-card v-for="t in filteredTemplates" :key="t.id" variant="outlined" class="d-flex align-center pa-3 mb-2">
              <v-radio :value="t.id" density="comfortable" hide-details />
              <div class="ml-2 flex-grow-1">
                <div class="text-body-2 font-weight-medium">{{ t.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ templateTypeMeta(t.type).label }}</div>
              </div>
            </v-card>
          </v-radio-group>
          <v-alert v-if="selectedTemplateUnsendable" type="warning" variant="tonal" density="compact" class="mb-4">
            Sending {{ templateTypeMeta(selectedTemplate.type).label.toLowerCase() }} messages isn't available yet — coming in a future update.
          </v-alert>
          <v-alert v-else-if="selectedTemplateBestEffort" type="info" variant="tonal" density="compact" class="mb-4">
            {{ templateTypeMeta(selectedTemplate.type).label }} messages use WhatsApp's native interactive-message format on this unofficial connection —
            recently switched from an older format that silently failed to deliver. Confirm it arrives on a real device before relying on it.
          </v-alert>

          <template v-if="selectedTemplate && templateVariables.length">
            <v-card variant="tonal" class="pa-4 mb-4">
              <div class="text-overline text-medium-emphasis mb-2">FILL TEMPLATE VARIABLES</div>
              <v-text-field
                v-for="variable in templateVariables" :key="variable"
                v-model="variableValues[variable]" :label="variable" variant="outlined" density="comfortable" class="mb-2"
                :placeholder="`Value for {{${variable}}}`" hide-details
              />
              <div class="text-caption text-medium-emphasis mt-2">
                Left-empty variables fall back to the recipient's saved contact data (if any), then blank.
              </div>
            </v-card>
          </template>
        </template>

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
