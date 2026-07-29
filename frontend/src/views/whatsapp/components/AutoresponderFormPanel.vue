<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import MediaPickerDialog from './template/MediaPickerDialog.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
  contactGroups: { type: Array, default: () => [] },
  editing: { type: Object, default: null },
})

const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const showMediaPicker = ref(false)

const applyToAll = ref(props.editing ? props.editing.channel_id === null : true)
const messageTab = ref(props.editing?.message_type === 'buttons' || props.editing?.message_type === 'list' ? props.editing.message_type : 'text_media')
const textMediaType = ref(props.editing?.message_type === 'media' ? 'media' : 'text')

const buttons = ref(props.editing?.interactive_config?.buttons ?? [])
const listButtonText = ref(props.editing?.interactive_config?.button_text ?? 'View Options')
const sections = ref(
  props.editing?.interactive_config?.sections?.length
    ? JSON.parse(JSON.stringify(props.editing.interactive_config.sections))
    : [{ title: 'Section 1', rows: [{ title: '', description: '', id: '' }] }],
)

const RESUBMIT_OPTIONS = [1, 5, 10, 15, 30, 60, 120, 240, 360, 720, 1440]

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().nullable(),
    enabled: yup.boolean(),
    target: yup.string().oneOf(['all', 'individual', 'group']).required(),
    target_phone: yup.string().nullable(),
    contact_group_id: yup.number().nullable(),
    body: yup.string().nullable().max(4096, 'Message must be 4096 characters or fewer'),
    media_url: yup.string().nullable().url('Enter a valid URL'),
    resubmit_after_minutes: yup.number().required(),
    except_contacts_raw: yup.string().nullable(),
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
    body: props.editing?.body ?? '',
    media_url: props.editing?.media_url ?? '',
    resubmit_after_minutes: props.editing?.resubmit_after_minutes ?? 1,
    except_contacts_raw: (props.editing?.except_contacts ?? []).join(', '),
  },
})

const [channelId] = defineField('channel_id')
const [enabled] = defineField('enabled')
const [target] = defineField('target')
const [targetPhone, targetPhoneAttrs] = defineField('target_phone')
const [contactGroupId] = defineField('contact_group_id')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')
const [resubmitAfterMinutes] = defineField('resubmit_after_minutes')
const [exceptContactsRaw] = defineField('except_contacts_raw')

watch(applyToAll, (val) => { if (val) channelId.value = null })

const variableHint = 'Random message by Spintax — Ex: {Hi|Hello|Hola}. Advanced: {Hi|Hello} {Mr.|Ms.}. Variables available: %phone%, %date%, %time%, %random_number%'

const submit = handleSubmit(async (values) => {
  if (!applyToAll.value && !values.channel_id) {
    alertStore.warning('Select a WhatsApp account, or enable "Apply for all accounts".')
    return
  }

  let messageType = 'text'
  let interactiveConfig = null

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
    interactiveConfig = { buttons: buttons.value }
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
    interactiveConfig = { button_text: listButtonText.value, sections: cleanSections }
  }

  if (values.target === 'individual' && !values.target_phone) {
    alertStore.warning('Enter the phone number to reply to.')
    return
  }
  if (values.target === 'group' && !values.contact_group_id) {
    alertStore.warning('Select a contact group.')
    return
  }

  const exceptContacts = (values.except_contacts_raw ?? '')
    .split(/[\n,]+/).map((s) => s.replace(/\D/g, '')).filter(Boolean)

  const payload = {
    channel_id: applyToAll.value ? null : values.channel_id,
    enabled: values.enabled,
    target: values.target,
    target_phone: values.target === 'individual' ? values.target_phone : null,
    contact_group_id: values.target === 'group' ? values.contact_group_id : null,
    message_type: messageType,
    body: values.body,
    media_url: messageType === 'media' ? values.media_url : null,
    config: interactiveConfig,
    resubmit_after_minutes: values.resubmit_after_minutes,
    except_contacts: exceptContacts,
  }

  saving.value = true
  try {
    if (props.editing) {
      await whatsapp.updateAutoresponder(props.editing.id, payload)
      alertStore.success('Autoresponder updated.')
    } else {
      await whatsapp.createAutoresponder(payload)
      alertStore.success('Autoresponder created.')
    }
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save autoresponder.')
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
</script>

<template>
  <div>
    <div class="d-flex align-center ga-3 mb-4">
      <AppButton variant="outlined" prepend-icon="mdi-arrow-left" @click="$emit('back')">Back</AppButton>
      <div>
        <h2 class="text-h5">Autoresponder</h2>
        <div class="text-caption text-medium-emphasis">Send a pre-written message</div>
      </div>
    </div>

    <v-form @submit.prevent="submit">
      <v-card class="pa-4 mb-4">
        <v-checkbox v-model="applyToAll" label="Apply for all accounts" density="comfortable" hide-details />
      </v-card>

      <v-card class="pa-6">
        <div class="text-h6 mb-4">Set autoresponder for {{ applyToAll ? 'all accounts' : 'this account' }}</div>

        <template v-if="!applyToAll">
          <div class="text-caption text-medium-emphasis mb-1">WHATSAPP ACCOUNT</div>
          <v-select
            v-model="channelId" :items="channelOptions" item-title="display_name" item-value="id"
            placeholder="Select an instance" variant="outlined" density="comfortable" class="mb-4"
          />
        </template>

        <v-divider class="mb-4" />

        <div class="text-caption text-medium-emphasis mb-1">STATUS</div>
        <v-radio-group v-model="enabled" inline hide-details class="mb-4">
          <v-radio label="Enable" :value="true" />
          <v-radio label="Disable" :value="false" />
        </v-radio-group>

        <div class="text-caption text-medium-emphasis mb-1">SENT TO</div>
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

        <v-btn-toggle v-model="messageTab" mandatory density="compact" class="mb-4 message-type-toggle flex-wrap" divided>
          <v-btn size="small" value="text_media">Text & Media</v-btn>
          <v-btn size="small" value="buttons">Buttons</v-btn>
          <v-btn size="small" value="list">List messages</v-btn>
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
        </template>

        <template v-else-if="messageTab === 'buttons'">
          <div class="text-caption text-medium-emphasis mb-1">BUTTONS (UP TO 3)</div>
          <v-combobox
            v-model="buttons" multiple chips closable-chips variant="outlined" class="mb-4"
            hint="Press enter after each button label — up to 3 buttons, 20 characters each" persistent-hint
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
        </template>

        <div class="text-caption text-medium-emphasis mb-1">CAPTION</div>
        <v-textarea
          v-model="body" v-bind="bodyAttrs" placeholder="Write a caption" variant="outlined" rows="4" auto-grow
          maxlength="4096" counter :error-messages="errors.body" class="mb-1"
        />
        <div class="text-caption text-medium-emphasis mb-4">{{ variableHint }}</div>

        <v-card variant="tonal" class="pa-4 mb-4">
          <div class="text-caption text-medium-emphasis mb-1">RESUBMIT MESSAGE ONLY AFTER (MINUTE)</div>
          <v-select v-model="resubmitAfterMinutes" :items="RESUBMIT_OPTIONS" variant="outlined" density="comfortable" class="mb-4" />

          <div class="text-caption text-medium-emphasis mb-1">EXCEPT CONTACTS</div>
          <v-textarea
            v-model="exceptContactsRaw" placeholder="911234567890, 910123456789" variant="outlined" rows="2" auto-grow
            hint="Comma or newline separated — these numbers never get an auto-reply" persistent-hint
          />
        </v-card>

        <div class="d-flex justify-space-between">
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
