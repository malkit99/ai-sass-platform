<script setup>
import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import TemplateFormShell from './TemplateFormShell.vue'
import TemplatePhonePreview from './TemplatePhonePreview.vue'
import MediaPickerDialog from './MediaPickerDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({ editing: { type: Object, default: null } })
const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const showPicker = ref(false)

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    media_url: yup.string().required('Video URL is required').url('Enter a valid URL'),
    body: yup.string().nullable(),
    footer: yup.string().nullable().max(60, 'Footer must be 60 characters or fewer'),
  }),
)

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: {
    name: props.editing?.name ?? '',
    media_url: props.editing?.media_url ?? '',
    body: props.editing?.body ?? '',
    footer: props.editing?.footer ?? '',
  },
})

const [name, nameAttrs] = defineField('name')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')
const [body, bodyAttrs] = defineField('body')
const [footer, footerAttrs] = defineField('footer')

const variableHint = 'Use {{name}}, {{phone}} for variables.'

const submit = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = { ...values, type: 'text_video' }
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
  <TemplateFormShell badge="VIDEO" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. Product Demo)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="text-overline text-medium-emphasis mb-3">CONFIGURATION</div>
      <div class="text-caption text-medium-emphasis mb-1">VIDEO URL *</div>
      <div class="d-flex ga-2">
        <v-text-field
          v-model="mediaUrl" v-bind="mediaUrlAttrs" placeholder="https://…"
          variant="outlined" density="comfortable" :error-messages="errors.media_url"
        />
        <AppButton variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showPicker = true">Browse</AppButton>
      </div>
    </v-card>

    <MediaPickerDialog v-model="showPicker" type="video" @selected="mediaUrl = $event" />

    <v-card class="pa-4">
      <div class="text-caption text-medium-emphasis mb-1">CAPTION</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="6" auto-grow
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
        icon="mdi-video-outline" media-type="video" :media-url="mediaUrl" :body-text="body" :footer-text="footer"
      />
    </template>
  </TemplateFormShell>
</template>
