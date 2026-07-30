<script setup>
import { ref } from 'vue'
import FormsPanel from './FormsPanel.vue'
import FormBuilderPanel from './FormBuilderPanel.vue'
import FormLeadsPanel from './FormLeadsPanel.vue'

const view = ref('list') // 'list' | 'builder' | 'leads'
const editing = ref(null)
const viewingLeadsFor = ref(null)

function create() {
  editing.value = null
  view.value = 'builder'
}

function edit(form) {
  editing.value = form
  view.value = 'builder'
}

function leads(form) {
  viewingLeadsFor.value = form
  view.value = 'leads'
}

function onSaved() {
  view.value = 'list'
  editing.value = null
}
</script>

<template>
  <FormsPanel v-if="view === 'list'" @create="create" @edit="edit" @leads="leads" />
  <FormBuilderPanel v-else-if="view === 'builder'" :editing="editing" @back="view = 'list'" @saved="onSaved" />
  <FormLeadsPanel v-else :form="viewingLeadsFor" @back="view = 'list'" />
</template>
