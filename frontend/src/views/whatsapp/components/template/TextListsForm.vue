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

// Mirrors Meta's real interactive list message shape: a button that opens the
// list, made up of one or more titled sections, each with its own rows
// (title/description/id) — see screenshot 71.
const sections = ref(
  props.editing?.config?.sections?.length
    ? JSON.parse(JSON.stringify(props.editing.config.sections))
    : [{ title: 'Section 1', rows: [{ title: '', description: '', id: '' }] }],
)

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    body: yup.string().required('Message is required').max(1024, 'Message must be 1024 characters or fewer'),
    footer: yup.string().nullable().max(60, 'Footer must be 60 characters or fewer'),
    button_text: yup.string().required('Button text is required').max(20, 'Button text must be 20 characters or fewer'),
  }),
)

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: schema,
  initialValues: {
    name: props.editing?.name ?? '',
    body: props.editing?.body ?? '',
    footer: props.editing?.footer ?? '',
    button_text: props.editing?.config?.button_text ?? 'View Options',
  },
})

const [name, nameAttrs] = defineField('name')
const [body, bodyAttrs] = defineField('body')
const [footer, footerAttrs] = defineField('footer')
const [buttonText, buttonTextAttrs] = defineField('button_text')

const variableHint = 'Use {{name}}, {{phone}}, or {{param1}}–{{param20}} (contact import columns) for variables.'

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

const submit = handleSubmit(async (values) => {
  const cleanSections = sections.value
    .map((s) => ({ ...s, rows: s.rows.filter((r) => r.title.trim()) }))
    .filter((s) => s.rows.length)

  if (!cleanSections.length) {
    alertStore.warning('Add at least one section with at least one row.')
    return
  }
  if (cleanSections.length > 10) {
    alertStore.warning('WhatsApp allows up to 10 sections per list message.')
    return
  }
  if (cleanSections.reduce((sum, s) => sum + s.rows.length, 0) > 10) {
    alertStore.warning('WhatsApp allows up to 10 rows in total across all sections.')
    return
  }
  if (cleanSections.some((s) => s.title.length > 24 || s.rows.some((r) => r.title.length > 24 || (r.description ?? '').length > 72))) {
    alertStore.warning('Section/row titles must be 24 characters or fewer, row descriptions 72 or fewer.')
    return
  }

  saving.value = true
  try {
    const config = { button_text: values.button_text, sections: cleanSections }
    const payload = { name: values.name, body: values.body, footer: values.footer, type: 'text_lists', config }
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
  <TemplateFormShell badge="LIST" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. Service Menu)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">MESSAGE CONTENT</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="4" auto-grow maxlength="1024" counter
        :error-messages="errors.body"
      />
      <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mt-1">
        <v-icon icon="mdi-information-outline" size="14" />
        {{ variableHint }}
      </div>
    </v-card>

    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">FOOTER (OPTIONAL)</div>
      <v-text-field
        v-model="footer" v-bind="footerAttrs" placeholder="Optional footer"
        variant="outlined" density="comfortable" maxlength="60" counter :error-messages="errors.footer" class="mb-3"
      />

      <div class="text-caption text-medium-emphasis mb-1">BUTTON TEXT *</div>
      <v-text-field
        v-model="buttonText" v-bind="buttonTextAttrs" placeholder="View Options"
        variant="outlined" density="comfortable" maxlength="20" counter :error-messages="errors.button_text"
      />
    </v-card>

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

    <v-btn variant="text" color="success" prepend-icon="mdi-plus" @click="addSection">Add Section</v-btn>

    <template #preview>
      <TemplatePhonePreview :body-text="body" :footer-text="footer">
        <div class="d-flex align-center justify-center ga-2 text-caption py-2 mt-2 preview-list-button">
          <v-icon icon="mdi-format-list-bulleted" size="16" />{{ buttonText || 'View Options' }}
        </div>
      </TemplatePhonePreview>
    </template>
  </TemplateFormShell>
</template>

<style scoped>
.preview-list-button {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  color: #00a5f4;
}
</style>
