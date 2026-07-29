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

// Explicit rows (not a combobox) — clearer than free-form chip entry and
// matches WhatsApp's own poll builder, which shows one field per option.
const pollOptions = ref(
  props.editing?.config?.poll_options?.length ? [...props.editing.config.poll_options] : ['', ''],
)

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    body: yup.string().required('Poll question is required').max(255, 'Poll question must be 255 characters or fewer'),
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

function addOption() {
  if (pollOptions.value.length >= 12) {
    alertStore.warning('WhatsApp polls allow up to 12 options.')
    return
  }
  pollOptions.value.push('')
}

function removeOption(index) {
  pollOptions.value.splice(index, 1)
}

const submit = handleSubmit(async (values) => {
  const options = pollOptions.value.map((o) => o.trim()).filter(Boolean)

  if (options.length < 2) {
    alertStore.warning('Add at least 2 poll options.')
    return
  }
  if (options.some((o) => o.length > 100)) {
    alertStore.warning('Poll options must be 100 characters or fewer.')
    return
  }

  saving.value = true
  try {
    const payload = { ...values, type: 'text_poll', config: { poll_options: options } }
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
  <TemplateFormShell badge="POLL" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. Feedback Poll)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="d-flex align-center justify-space-between mb-3">
        <div class="text-overline text-medium-emphasis">POLL OPTIONS (MIN 2, MAX 12)</div>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addOption">Add Option</v-btn>
      </div>
      <div v-for="(option, index) in pollOptions" :key="index" class="d-flex align-center ga-2 mb-2">
        <span class="text-caption text-medium-emphasis" style="width: 20px">{{ index + 1 }}.</span>
        <v-text-field
          v-model="pollOptions[index]" :placeholder="`Option ${index + 1} (max 100)`" density="compact" variant="outlined" hide-details
          maxlength="100"
        />
        <v-btn
          icon="mdi-close-circle" size="small" variant="text" color="error"
          :disabled="pollOptions.length <= 2" @click="removeOption(index)"
        />
      </div>
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">POLL QUESTION</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="What should we improve?" variant="outlined" rows="4" auto-grow
        maxlength="255" counter :error-messages="errors.body"
      />
    </v-card>

    <v-card class="pa-4">
      <div class="text-caption text-medium-emphasis mb-1">FOOTER (OPTIONAL)</div>
      <v-text-field
        v-model="footer" v-bind="footerAttrs" placeholder="e.g. Reply STOP to unsubscribe"
        variant="outlined" density="comfortable" maxlength="60" counter :error-messages="errors.footer"
      />
    </v-card>

    <template #preview>
      <TemplatePhonePreview :body-text="body" :footer-text="footer">
        <div v-if="pollOptions.some((o) => o.trim())" class="mt-2">
          <div v-for="(opt, i) in pollOptions.filter((o) => o.trim())" :key="i" class="d-flex align-center ga-2 text-caption py-1">
            <v-icon icon="mdi-circle-outline" size="14" />{{ opt }}
          </div>
        </div>
      </TemplatePhonePreview>
    </template>
  </TemplateFormShell>
</template>
