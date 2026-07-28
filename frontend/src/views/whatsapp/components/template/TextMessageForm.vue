<script setup>
import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import TemplateFormShell from './TemplateFormShell.vue'
import TemplatePhonePreview from './TemplatePhonePreview.vue'

const props = defineProps({ editing: { type: Object, default: null } })
const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    body: yup.string().required('Message is required'),
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

// Kept as a script-side constant — Vue's template compiler chokes trying to
// parse a literal "{{name}}" written directly inside a mustache interpolation
// (nested same-char delimiters), so this stays a single simple interpolation.
const variableHint = 'Use {{name}}, {{phone}} for variables.'

const submit = handleSubmit(async (values) => {
  saving.value = true
  try {
    const payload = { ...values, type: 'text' }
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
  <TemplateFormShell badge="TEXT" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. Welcome Message)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card class="pa-4">
      <div class="text-caption text-medium-emphasis mb-1">MESSAGE CONTENT</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="8" auto-grow
        :error-messages="errors.body"
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
      <TemplatePhonePreview :body-text="body" :footer-text="footer" />
    </template>
  </TemplateFormShell>
</template>
