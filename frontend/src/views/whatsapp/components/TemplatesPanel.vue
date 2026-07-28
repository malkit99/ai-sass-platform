<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import TemplateTypeGrid from './TemplateTypeGrid.vue'
import TemplateTypeList from './TemplateTypeList.vue'
import TextMessageForm from './template/TextMessageForm.vue'
import TextImageForm from './template/TextImageForm.vue'
import TextVideoForm from './template/TextVideoForm.vue'
import TextDocumentForm from './template/TextDocumentForm.vue'
import TextAudioForm from './template/TextAudioForm.vue'
import TextButtonsForm from './template/TextButtonsForm.vue'
import TextListsForm from './template/TextListsForm.vue'
import TextPollForm from './template/TextPollForm.vue'
import InteractiveButtonsForm from './template/InteractiveButtonsForm.vue'
import TextCarouselForm from './template/TextCarouselForm.vue'

const formComponents = {
  text: TextMessageForm,
  text_image: TextImageForm,
  text_video: TextVideoForm,
  text_document: TextDocumentForm,
  text_audio: TextAudioForm,
  text_buttons: TextButtonsForm,
  text_lists: TextListsForm,
  text_poll: TextPollForm,
  interactive_buttons: InteractiveButtonsForm,
  text_carousel: TextCarouselForm,
}

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const view = ref('types') // 'types' | 'list' | 'form'
const selectedType = ref(null)
const editingTemplate = ref(null)

onMounted(() => whatsapp.fetchTemplates())

const activeFormComponent = computed(() => formComponents[selectedType.value] ?? null)

function selectType(type) {
  selectedType.value = type
  view.value = 'list'
}

function backToTypes() {
  view.value = 'types'
  selectedType.value = null
}

function createTemplate() {
  editingTemplate.value = null
  view.value = 'form'
}

function editTemplate(template) {
  editingTemplate.value = template
  view.value = 'form'
}

function backToList() {
  view.value = 'list'
  editingTemplate.value = null
}

function onSaved() {
  view.value = 'list'
  editingTemplate.value = null
}

async function deleteTemplate(template) {
  const confirmed = await fireConfirm('Delete this template?', `"${template.name}" will be permanently removed.`)
  if (!confirmed) return

  await whatsapp.deleteTemplate(template.id)
  alertStore.info('Template deleted.')
}
</script>

<template>
  <div>
    <template v-if="view === 'types'">
      <div class="mb-4">
        <h2 class="text-h5">Templates</h2>
        <div class="text-caption text-medium-emphasis">Create and manage advanced message templates</div>
      </div>
      <TemplateTypeGrid :templates="whatsapp.templates" @select="selectType" />
    </template>

    <TemplateTypeList
      v-else-if="view === 'list'"
      :type="selectedType"
      :templates="whatsapp.templates"
      @back="backToTypes"
      @create="createTemplate"
      @edit="editTemplate"
      @delete="deleteTemplate"
    />

    <component
      :is="activeFormComponent"
      v-else-if="view === 'form'"
      :editing="editingTemplate"
      @back="backToList"
      @saved="onSaved"
    />
  </div>
</template>
