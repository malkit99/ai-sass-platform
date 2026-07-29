<script setup>
import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import TemplateFormShell from './TemplateFormShell.vue'
import TemplatePhonePreview from './TemplatePhonePreview.vue'
import TemplateButtonRow from './TemplateButtonRow.vue'
import MediaPickerDialog from './MediaPickerDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({ editing: { type: Object, default: null } })
const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const showPicker = ref(false)

// Meta's own template button types — see TemplateButtonRow.
const buttons = ref(props.editing?.config?.buttons ?? [])

// Meta's real template header component types — TEXT, IMAGE, VIDEO, DOCUMENT
// (or no header at all).
const headerType = ref(props.editing?.config?.header_type ?? 'none')
const headerText = ref(props.editing?.config?.header_text ?? '')
const headerMediaUrl = ref(props.editing?.config?.header_media_url ?? '')

const headerTypeOptions = [
  { title: 'None', value: 'none' },
  { title: 'Text', value: 'text' },
  { title: 'Image', value: 'image' },
  { title: 'Video', value: 'video' },
  { title: 'Document', value: 'document' },
]

const buttonIcon = { reply: 'mdi-reply-outline', call: 'mdi-phone-outline', url: 'mdi-open-in-new' }
const mediaIcon = { image: 'mdi-image-outline', video: 'mdi-video-outline', document: 'mdi-file-document-outline' }

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    body: yup.string().required('Message is required').max(1024, 'Message must be 1024 characters or fewer'),
    footer: yup.string().nullable().max(60, 'Footer must be 60 characters or fewer'),
  }),
)

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: { name: props.editing?.name ?? '', body: props.editing?.body ?? '', footer: props.editing?.footer ?? '' },
})

const [name, nameAttrs] = defineField('name')
const [body, bodyAttrs] = defineField('body')
const [footer, footerAttrs] = defineField('footer')

const variableHint = 'Use {{name}}, {{phone}}, or {{param1}}–{{param20}} (contact import columns) for variables.'

function addButton() {
  if (buttons.value.length >= 3) {
    alertStore.warning('WhatsApp allows up to 3 buttons per message.')
    return
  }
  buttons.value.push({ type: 'reply', label: '', value: '' })
}

function removeButton(index) {
  buttons.value.splice(index, 1)
}

const submit = handleSubmit(async (values) => {
  if (!buttons.value.length) {
    alertStore.warning('Add at least one button.')
    return
  }
  if (buttons.value.some((b) => !b.label)) {
    alertStore.warning('Every button needs a label.')
    return
  }
  if (buttons.value.some((b) => b.label.length > 20)) {
    alertStore.warning('Button labels must be 20 characters or fewer.')
    return
  }

  saving.value = true
  try {
    const config = {
      buttons: buttons.value,
      header_type: headerType.value,
      header_text: headerType.value === 'text' ? headerText.value : null,
      header_media_url: ['image', 'video', 'document'].includes(headerType.value) ? headerMediaUrl.value : null,
    }
    const payload = { ...values, type: 'interactive_buttons', config }
    if (props.editing) {
      await whatsapp.updateTemplate(props.editing.id, payload)
      alertStore.success('Template updated.')
    } else {
      await whatsapp.createTemplate(payload)
      alertStore.success('Template created.')
    }
    emit('saved')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save template.')
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <TemplateFormShell badge="INTERACTIVE" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. Quick Reply Menu)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="text-overline text-medium-emphasis mb-3">HEADER</div>
      <v-select
        v-model="headerType" label="Header type" :items="headerTypeOptions" item-title="title" item-value="value"
        variant="outlined" density="comfortable" class="mb-3"
      />

      <v-text-field
        v-if="headerType === 'text'" v-model="headerText" placeholder="Header text (e.g. Special Offer!)"
        variant="outlined" density="comfortable" maxlength="60" counter
      />

      <template v-else-if="['image', 'video', 'document'].includes(headerType)">
        <div class="d-flex ga-2">
          <v-text-field v-model="headerMediaUrl" placeholder="https://…" variant="outlined" density="comfortable" />
          <AppButton variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showPicker = true">Browse</AppButton>
        </div>
        <MediaPickerDialog v-model="showPicker" :type="headerType" @selected="headerMediaUrl = $event" />
      </template>
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="d-flex align-center justify-space-between mb-3">
        <div class="text-overline text-medium-emphasis">BUTTONS (UP TO 3)</div>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addButton">Add Button</v-btn>
      </div>
      <TemplateButtonRow
        v-for="(button, index) in buttons" :key="index" :model-value="button"
        @update:model-value="buttons[index] = $event" @remove="removeButton(index)"
      />
      <div v-if="!buttons.length" class="text-caption text-medium-emphasis">No buttons added yet.</div>
    </v-card>

    <v-card class="pa-4">
      <div class="text-caption text-medium-emphasis mb-1">MESSAGE CONTENT</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="6" auto-grow
        maxlength="1024" counter :error-messages="errors.body"
      />
      <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mt-1">
        <v-icon icon="mdi-information-outline" size="14" />
        {{ variableHint }}
      </div>
    </v-card>

    <v-card class="pa-4 mt-4">
      <div class="text-caption text-medium-emphasis mb-1">FOOTER (OPTIONAL)</div>
      <v-text-field
        v-model="footer" v-bind="footerAttrs" placeholder="e.g. Reply STOP to unsubscribe"
        variant="outlined" density="comfortable" maxlength="60" counter :error-messages="errors.footer"
      />
    </v-card>

    <template #preview>
      <TemplatePhonePreview
        :icon="mediaIcon[headerType]"
        :media-type="['image', 'video', 'document'].includes(headerType) ? headerType : null"
        :media-url="['image', 'video', 'document'].includes(headerType) ? headerMediaUrl : null"
        :header-text="headerType === 'text' ? headerText : ''"
        :body-text="body" :footer-text="footer"
      >
        <div v-if="buttons.length" class="d-flex flex-column ga-1 mt-2">
          <div
            v-for="(b, i) in buttons" :key="i"
            class="d-flex align-center justify-center ga-1 text-caption py-1 preview-button"
          >
            <v-icon :icon="buttonIcon[b.type]" size="14" />{{ b.label || 'Button' }}
          </div>
        </div>
      </TemplatePhonePreview>
    </template>
  </TemplateFormShell>
</template>

<style scoped>
.preview-button {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  color: #00a5f4;
}
</style>
