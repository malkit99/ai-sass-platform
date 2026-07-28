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

const props = defineProps({ editing: { type: Object, default: null } })
const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const cards = ref(props.editing?.config?.cards ?? [])
const pickerForCard = ref(null)

const schema = toTypedSchema(
  yup.object({
    name: yup.string().required('Template name is required'),
    body: yup.string().nullable(),
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

function addCard() {
  cards.value.push({ title: '', body: '', image_url: '', buttons: [] })
}

function removeCard(index) {
  cards.value.splice(index, 1)
}

function addButton(card) {
  if (card.buttons.length >= 2) {
    alertStore.warning('Up to 2 buttons per carousel card.')
    return
  }
  card.buttons.push({ type: 'reply', label: '', value: '' })
}

function removeButton(card, index) {
  card.buttons.splice(index, 1)
}

function openPicker(index) {
  pickerForCard.value = index
}

function onMediaSelected(url) {
  if (pickerForCard.value === null) return
  cards.value[pickerForCard.value].image_url = url
  pickerForCard.value = null
}

const submit = handleSubmit(async (values) => {
  if (!cards.value.length) {
    alertStore.warning('Add at least one card.')
    return
  }

  saving.value = true
  try {
    const payload = { ...values, type: 'text_carousel', config: { cards: cards.value } }
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
  <TemplateFormShell badge="CAROUSEL" :editing="!!editing" :saving="saving" @back="$emit('back')" @save="submit">
    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">TEMPLATE NAME *</div>
      <v-text-field
        v-model="name" v-bind="nameAttrs" placeholder="Enter a descriptive name (e.g. New Arrivals)"
        variant="outlined" density="comfortable" :error-messages="errors.name"
      />
    </v-card>

    <v-card v-for="(card, index) in cards" :key="index" class="mb-4" variant="outlined">
      <div class="d-flex align-center justify-space-between pa-3 carousel-card-header">
        <span class="text-overline text-medium-emphasis">CAROUSEL CARD</span>
        <v-btn icon="mdi-delete-outline" size="small" variant="tonal" color="error" @click="removeCard(index)" />
      </div>

      <v-row class="pa-4" no-gutters>
        <v-col cols="12" sm="4" class="pr-sm-4 mb-3 mb-sm-0">
          <div class="select-media-box d-flex flex-column align-center justify-center" @click="openPicker(index)">
            <v-img v-if="card.image_url" :src="card.image_url" height="90" width="100%" cover class="rounded mb-1" />
            <template v-else>
              <v-icon icon="mdi-image-outline" size="32" color="medium-emphasis" />
              <div class="text-caption font-weight-medium mt-1">Select Media</div>
            </template>
          </div>
        </v-col>

        <v-col cols="12" sm="8">
          <div class="text-caption text-medium-emphasis mb-1">CARD TITLE</div>
          <v-text-field v-model="card.title" placeholder="Title" density="compact" variant="outlined" class="mb-3" />

          <div class="text-caption text-medium-emphasis mb-1">DESCRIPTION</div>
          <v-textarea v-model="card.body" placeholder="Body" density="compact" variant="outlined" rows="3" auto-grow class="mb-3" />

          <TemplateButtonRow
            v-for="(button, bIndex) in card.buttons" :key="bIndex" :model-value="button"
            @update:model-value="card.buttons[bIndex] = $event" @remove="removeButton(card, bIndex)"
          />
          <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="addButton(card)">Add Button</v-btn>
        </v-col>
      </v-row>
    </v-card>

    <v-btn block color="success" variant="flat" size="large" prepend-icon="mdi-plus" class="mb-4" @click="addCard">
      Add Carousel Card
    </v-btn>

    <MediaPickerDialog
      :model-value="pickerForCard !== null" type="image"
      @update:model-value="pickerForCard = null" @selected="onMediaSelected"
    />

    <v-card class="pa-4 mb-4">
      <div class="text-caption text-medium-emphasis mb-1">MESSAGE CONTENT</div>
      <v-textarea
        v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="4" auto-grow
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
        <div v-if="cards.length" class="d-flex ga-2 mt-2" style="overflow-x: auto">
          <v-card v-for="(card, i) in cards" :key="i" width="120" variant="tonal" class="pa-2 flex-shrink-0">
            <v-img v-if="card.image_url" :src="card.image_url" height="60" cover class="rounded mb-1" />
            <div v-else class="d-flex align-center justify-center rounded mb-1" style="height: 60px; background: rgba(0, 0, 0, 0.06)">
              <v-icon icon="mdi-image-outline" size="20" color="medium-emphasis" />
            </div>
            <div class="text-caption font-weight-medium text-truncate">{{ card.title || 'Title' }}</div>
            <div class="text-caption text-truncate text-medium-emphasis">{{ card.body || 'Body' }}</div>
          </v-card>
        </div>
      </TemplatePhonePreview>
    </template>
  </TemplateFormShell>
</template>

<style scoped>
.carousel-card-header {
  background: rgba(0, 0, 0, 0.02);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.select-media-box {
  height: 100%;
  min-height: 110px;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  cursor: pointer;
  padding: 8px;
}
</style>
