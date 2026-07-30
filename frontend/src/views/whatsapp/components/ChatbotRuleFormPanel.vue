<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { templateTypeMeta } from '@core/utils/whatsappTemplateTypes'
import AppButton from '@/components/AppButton.vue'
import MediaPickerDialog from './template/MediaPickerDialog.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
  contactGroups: { type: Array, default: () => [] },
  editing: { type: Object, default: null },
})

const emit = defineEmits(['back', 'saved', 'connect-channel'])

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const showMediaPicker = ref(false)
const keywords = ref(props.editing?.keywords ?? [])

const applyToAll = ref(props.editing ? props.editing.channel_id === null : true)

const INTERACTIVE_TYPES = ['buttons', 'list']
const initialTab = INTERACTIVE_TYPES.includes(props.editing?.message_type) ? props.editing.message_type : 'text_media'
const messageTab = ref(initialTab)
const textMediaType = ref(props.editing?.message_type === 'media' ? 'media' : 'text')
const selectedTemplateId = ref(null)

const buttons = ref(props.editing?.interactive_config?.buttons ?? [])
const listButtonText = ref(props.editing?.interactive_config?.button_text ?? 'View Options')
const sections = ref(
  props.editing?.interactive_config?.sections?.length
    ? JSON.parse(JSON.stringify(props.editing.interactive_config.sections))
    : [{ title: 'Section 1', rows: [{ title: '', description: '', id: '' }] }],
)

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().nullable(),
    enabled: yup.boolean(),
    target: yup.string().oneOf(['all', 'individual', 'group']).required(),
    target_phone: yup.string().nullable(),
    contact_group_id: yup.number().nullable(),
    match_type: yup.string().oneOf(['contains', 'exact']).required(),
    name: yup.string().required('Item name is required'),
    body: yup.string().nullable().max(4096, 'Message must be 4096 characters or fewer'),
    media_url: yup.string().nullable().url('Enter a valid URL'),
  }),
)

const { defineField, handleSubmit, errors, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    channel_id: props.editing?.channel_id ?? null,
    enabled: props.editing?.enabled ?? true,
    target: props.editing?.target ?? 'all',
    target_phone: props.editing?.target_phone ?? '',
    contact_group_id: props.editing?.contact_group_id ?? null,
    match_type: props.editing?.match_type ?? 'contains',
    name: props.editing?.name ?? '',
    body: props.editing?.body ?? '',
    media_url: props.editing?.media_url ?? '',
  },
})

const [channelId] = defineField('channel_id')
const [enabled] = defineField('enabled')
const [target] = defineField('target')
const [targetPhone, targetPhoneAttrs] = defineField('target_phone')
const [contactGroupId] = defineField('contact_group_id')
const [matchType] = defineField('match_type')
const [name, nameAttrs] = defineField('name')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')

watch(applyToAll, (val) => { if (val) channelId.value = null })
watch(messageTab, () => { selectedTemplateId.value = null })

if (!whatsapp.templates.length) whatsapp.fetchTemplates()

const selectedTemplate = computed(() => whatsapp.templates.find((t) => t.id === selectedTemplateId.value) ?? null)

const submit = handleSubmit(async (values) => {
  if (!applyToAll.value && !values.channel_id) {
    alertStore.warning('Select a WhatsApp account, or enable "Apply for all accounts".')
    return
  }
  if (!keywords.value.length) {
    alertStore.warning('Add at least one keyword.')
    return
  }
  if (values.target === 'individual' && !values.target_phone) {
    alertStore.warning('Enter the phone number to reply to.')
    return
  }
  if (values.target === 'group' && !values.contact_group_id) {
    alertStore.warning('Select a contact group.')
    return
  }

  let messageType = 'text'
  let config = null
  let templateId = null

  if (messageTab.value === 'text_media') {
    messageType = textMediaType.value
    if (messageType === 'text' && !values.body) {
      setErrors({ body: 'Message is required' })
      return
    }
    if (messageType === 'media' && !values.media_url) {
      setErrors({ media_url: 'Media URL is required' })
      return
    }
  } else if (messageTab.value === 'buttons') {
    if (!buttons.value.length) {
      alertStore.warning('Add at least one button.')
      return
    }
    if (buttons.value.length > 3) {
      alertStore.warning('WhatsApp allows up to 3 buttons per message.')
      return
    }
    if (buttons.value.some((b) => b.length > 20)) {
      alertStore.warning('Button labels must be 20 characters or fewer.')
      return
    }
    if (!values.body) {
      setErrors({ body: 'Message is required' })
      return
    }
    messageType = 'buttons'
    config = { buttons: buttons.value }
  } else if (messageTab.value === 'list') {
    const cleanSections = sections.value
      .map((s) => ({ ...s, rows: s.rows.filter((r) => r.title.trim()) }))
      .filter((s) => s.rows.length)

    if (!cleanSections.length) {
      alertStore.warning('Add at least one section with at least one row.')
      return
    }
    if (!values.body) {
      setErrors({ body: 'Message is required' })
      return
    }
    messageType = 'list'
    config = { button_text: listButtonText.value, sections: cleanSections }
  } else if (messageTab.value === 'template') {
    if (!selectedTemplateId.value) {
      alertStore.warning('Select a template.')
      return
    }
    messageType = 'template'
    templateId = selectedTemplateId.value
  }

  const payload = {
    channel_id: applyToAll.value ? null : values.channel_id,
    enabled: values.enabled,
    target: values.target,
    target_phone: values.target === 'individual' ? values.target_phone : null,
    contact_group_id: values.target === 'group' ? values.contact_group_id : null,
    match_type: values.match_type,
    name: values.name,
    keywords: keywords.value,
    message_type: messageType,
    template_id: templateId,
    body: messageType === 'template' ? null : values.body,
    media_url: messageType === 'media' ? values.media_url : null,
    config,
  }

  saving.value = true
  try {
    if (props.editing) {
      await whatsapp.updateChatbotRule(props.editing.id, payload)
      alertStore.success('Chatbot item updated.')
    } else {
      await whatsapp.createChatbotRule(payload)
      alertStore.success('Chatbot item created.')
    }
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save chatbot item.')
    }
  } finally {
    saving.value = false
  }
})

function addSection() {
  sections.value.push({ title: `Section ${sections.value.length + 1}`, rows: [{ title: '', description: '', id: '' }] })
}
function removeSection(index) {
  sections.value.splice(index, 1)
}
function addRow(section) {
  section.rows.push({ title: '', description: '', id: '' })
}
function removeRow(section, index) {
  section.rows.splice(index, 1)
}

const channelOptions = computed(() => props.channels)
const selectedChannel = computed(() => props.channels.find((c) => c.id === channelId.value) ?? null)
const selectedChannelDisconnected = computed(() => selectedChannel.value && selectedChannel.value.status !== 'connected')
</script>

<template>
  <div>
    <div class="d-flex align-center ga-3 mb-4">
      <AppButton variant="outlined" prepend-icon="mdi-arrow-left" @click="$emit('back')">Back</AppButton>
      <div>
        <h2 class="text-h5">Chatbot item</h2>
        <div class="text-caption text-medium-emphasis">Reply automatically when a message matches a keyword</div>
      </div>
    </div>

    <v-form @submit.prevent="submit">
      <v-card class="pa-4 mb-4">
        <v-checkbox v-model="applyToAll" label="Apply for all accounts" density="comfortable" hide-details />
      </v-card>

      <v-card class="pa-6">
        <template v-if="!applyToAll">
          <div class="text-caption text-medium-emphasis mb-1">WHATSAPP ACCOUNT</div>
          <v-select
            v-model="channelId" :items="channelOptions" item-title="display_name" item-value="id"
            placeholder="Select an instance" variant="outlined" density="comfortable" class="mb-2"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps">
                <template #title>{{ item.raw.display_name }}</template>
                <template #append>
                  <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ item.raw.status }}</v-chip>
                </template>
              </v-list-item>
            </template>
            <template #selection="{ item }">
              {{ item.raw.display_name }}
              <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat" class="ml-2">{{ item.raw.status }}</v-chip>
            </template>
          </v-select>

          <v-alert v-if="selectedChannelDisconnected" type="warning" variant="tonal" density="compact" class="mb-4">
            <div class="d-flex align-center justify-space-between ga-2">
              <span class="text-body-2">Phone not connected — connect it first before this item can reply.</span>
              <AppButton size="small" variant="flat" color="warning" @click="$emit('connect-channel', selectedChannel)">
                Connect now
              </AppButton>
            </div>
          </v-alert>
        </template>

        <v-divider class="mb-4" />

        <div class="text-caption text-medium-emphasis mb-1">STATUS</div>
        <v-radio-group v-model="enabled" inline hide-details class="mb-4">
          <v-radio label="Enable" :value="true" />
          <v-radio label="Disable" :value="false" />
        </v-radio-group>

        <div class="text-caption text-medium-emphasis mb-1">SEND TO</div>
        <v-radio-group v-model="target" inline hide-details class="mb-2">
          <v-radio label="All" value="all" />
          <v-radio label="Individual" value="individual" />
          <v-radio label="Group" value="group" />
        </v-radio-group>

        <v-text-field
          v-if="target === 'individual'" v-model="targetPhone" v-bind="targetPhoneAttrs"
          placeholder="e.g. 911234567890" variant="outlined" density="comfortable" class="mb-4 mt-2"
        />
        <v-select
          v-if="target === 'group'" v-model="contactGroupId" :items="contactGroups" item-title="name" item-value="id"
          placeholder="Select contact group" variant="outlined" density="comfortable" class="mb-4 mt-2"
        />

        <div class="text-caption text-medium-emphasis mb-1">TYPE</div>
        <v-radio-group v-model="matchType" inline hide-details class="mb-4">
          <v-radio label="Message contains the keyword" value="contains" />
          <v-radio label="Message contains whole keyword" value="exact" />
        </v-radio-group>

        <div class="text-caption text-medium-emphasis mb-1">NAME</div>
        <v-text-field v-model="name" v-bind="nameAttrs" placeholder="Item name" variant="outlined" density="comfortable" :error-messages="errors.name" class="mb-4" />

        <div class="text-caption text-medium-emphasis mb-1">KEYWORDS</div>
        <v-combobox
          v-model="keywords" multiple chips closable-chips variant="outlined" class="mb-4"
          hint="Press enter after each keyword" persistent-hint
        />

        <v-btn-toggle v-model="messageTab" mandatory density="compact" class="mb-4 message-type-toggle flex-wrap" divided>
          <v-btn size="small" value="text_media">Text & Media</v-btn>
          <v-btn size="small" value="buttons">Buttons</v-btn>
          <v-btn size="small" value="list">List messages</v-btn>
          <v-btn size="small" value="template">Templates</v-btn>
        </v-btn-toggle>

        <template v-if="messageTab === 'text_media'">
          <v-btn-toggle v-model="textMediaType" mandatory density="compact" class="mb-4 message-type-toggle" divided>
            <v-btn size="small" value="text">Text</v-btn>
            <v-btn size="small" value="media">Media</v-btn>
          </v-btn-toggle>

          <template v-if="textMediaType === 'media'">
            <div class="text-caption text-medium-emphasis mb-1">MEDIA URL</div>
            <div class="d-flex ga-2 mb-4">
              <v-text-field
                v-model="mediaUrl" v-bind="mediaUrlAttrs" placeholder="https://…" variant="outlined" density="comfortable"
                :error-messages="errors.media_url"
              />
              <AppButton variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showMediaPicker = true">Browse</AppButton>
            </div>
            <MediaPickerDialog v-model="showMediaPicker" type="image" @selected="mediaUrl = $event" />
          </template>

          <div class="text-caption text-medium-emphasis mb-1">{{ textMediaType === 'media' ? 'CAPTION' : 'MESSAGE' }}</div>
          <v-textarea
            v-model="body" v-bind="bodyAttrs" placeholder="Write a message" variant="outlined" rows="4" auto-grow
            maxlength="4096" counter :error-messages="errors.body"
            hint="Supports spintax: {Hi|Hello|Hola} there" persistent-hint class="mb-1"
          />
        </template>

        <template v-else-if="messageTab === 'buttons'">
          <div class="text-caption text-medium-emphasis mb-1">BUTTONS (UP TO 3)</div>
          <v-combobox
            v-model="buttons" multiple chips closable-chips variant="outlined" class="mb-4"
            hint="Press enter after each button label — up to 3 buttons, 20 characters each" persistent-hint
          />

          <div class="text-caption text-medium-emphasis mb-1">MESSAGE</div>
          <v-textarea
            v-model="body" v-bind="bodyAttrs" placeholder="Write a message" variant="outlined" rows="3" auto-grow
            maxlength="4096" counter :error-messages="errors.body"
          />
        </template>

        <template v-else-if="messageTab === 'list'">
          <div class="text-caption text-medium-emphasis mb-1">BUTTON TEXT</div>
          <v-text-field
            v-model="listButtonText" placeholder="View Options" variant="outlined" density="comfortable"
            maxlength="20" counter class="mb-4"
          />

          <v-card v-for="(section, sIndex) in sections" :key="sIndex" class="pa-4 mb-4" variant="outlined">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-text-field
                v-model="section.title" placeholder="Section (max 24)" density="compact" variant="outlined" hide-details
                maxlength="24" style="max-width: 220px"
              />
              <v-btn size="small" variant="tonal" color="error" @click="removeSection(sIndex)">Remove</v-btn>
            </div>
            <div v-for="(row, rIndex) in section.rows" :key="rIndex" class="d-flex align-start ga-2 mb-2">
              <v-text-field v-model="row.title" placeholder="Title (max 24)" density="compact" variant="outlined" hide-details maxlength="24" />
              <v-text-field v-model="row.description" placeholder="Desc (max 72)" density="compact" variant="outlined" hide-details maxlength="72" />
              <v-text-field v-model="row.id" placeholder="ID" density="compact" variant="outlined" hide-details style="max-width: 100px" />
              <v-btn icon="mdi-close-circle" size="small" variant="text" color="error" @click="removeRow(section, rIndex)" />
            </div>
            <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="addRow(section)">Add Row</v-btn>
          </v-card>
          <v-btn variant="text" color="success" prepend-icon="mdi-plus" class="mb-4" @click="addSection">Add Section</v-btn>

          <div class="text-caption text-medium-emphasis mb-1">MESSAGE</div>
          <v-textarea
            v-model="body" v-bind="bodyAttrs" placeholder="Write a message" variant="outlined" rows="3" auto-grow
            maxlength="4096" counter :error-messages="errors.body"
          />
        </template>

        <template v-else-if="messageTab === 'template'">
          <v-alert v-if="!whatsapp.templates.length" type="info" variant="tonal" density="compact" class="mb-4">
            No saved templates yet — create one under Templates first.
          </v-alert>
          <v-radio-group v-else v-model="selectedTemplateId" class="mb-2">
            <v-card v-for="t in whatsapp.templates" :key="t.id" variant="outlined" class="d-flex align-center pa-3 mb-2">
              <v-radio :value="t.id" density="comfortable" hide-details />
              <div class="ml-2 flex-grow-1">
                <div class="text-body-2 font-weight-medium">{{ t.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ templateTypeMeta(t.type).label }}</div>
              </div>
            </v-card>
          </v-radio-group>
          <v-alert v-if="selectedTemplate?.type === 'text_carousel'" type="warning" variant="tonal" density="compact" class="mb-4">
            Carousel templates can't be sent on this connection yet.
          </v-alert>
        </template>

        <div class="d-flex justify-space-between mt-4">
          <AppButton variant="outlined" :disabled="saving" @click="$emit('back')">Back</AppButton>
          <AppButton :loading="saving" prepend-icon="mdi-content-save" @click="submit">Save</AppButton>
        </div>
      </v-card>
    </v-form>
  </div>
</template>

<style scoped>
.message-type-toggle :deep(.v-btn) {
  text-transform: none;
}
</style>
