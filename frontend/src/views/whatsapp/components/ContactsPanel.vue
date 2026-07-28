<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import ContactGroupDetail from './ContactGroupDetail.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const PER_PAGE = 6

const view = ref('groups') // 'groups' | 'form' | 'detail'
const search = ref('')
const page = ref(1)
const openGroup = ref(null)

const editingGroup = ref(null)
const formName = ref('')
const formStatus = ref('enable')
const saving = ref(false)

onMounted(() => whatsapp.fetchContactGroups())

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return whatsapp.contactGroups
  return whatsapp.contactGroups.filter((g) => g.name.toLowerCase().includes(term))
})

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const paged = computed(() => {
  const start = (page.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

watch(filtered, () => {
  if (page.value > pageCount.value) page.value = 1
})

function createGroup() {
  editingGroup.value = null
  formName.value = ''
  formStatus.value = 'enable'
  view.value = 'form'
}

function editGroup(group) {
  editingGroup.value = group
  formName.value = group.name
  formStatus.value = group.status
  view.value = 'form'
}

async function submitForm() {
  if (!formName.value.trim()) {
    alertStore.warning('Group contact name is required.')
    return
  }

  saving.value = true
  try {
    if (editingGroup.value) {
      await whatsapp.updateContactGroup(editingGroup.value.id, { name: formName.value, status: formStatus.value })
      alertStore.success('Contact group updated.')
    } else {
      await whatsapp.createContactGroup({ name: formName.value, status: formStatus.value })
      alertStore.success('Contact group created.')
    }
    view.value = 'groups'
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save contact group.')
  } finally {
    saving.value = false
  }
}

async function deleteGroup(group) {
  const confirmed = await fireConfirm('Delete this contact group?', `"${group.name}" and all ${group.contacts_count ?? 0} of its contacts will be permanently removed.`)
  if (!confirmed) return

  await whatsapp.deleteContactGroup(group.id)
  alertStore.info('Contact group deleted.')
}

function viewContacts(group) {
  openGroup.value = group
  view.value = 'detail'
}

function backToGroups() {
  view.value = 'groups'
  openGroup.value = null
  whatsapp.fetchContactGroups()
}
</script>

<template>
  <div>
    <template v-if="view === 'groups'">
      <div class="d-flex flex-wrap align-center ga-3 mb-4">
        <div class="flex-grow-1">
          <h2 class="text-h5">Contacts</h2>
          <div class="text-caption text-medium-emphasis">Organize numbers into groups for bulk messaging</div>
        </div>
        <v-text-field
          v-model="search" placeholder="Search groups…" prepend-inner-icon="mdi-magnify"
          variant="outlined" density="comfortable" hide-details style="max-width: 260px"
        />
        <v-btn icon="mdi-plus" color="primary" @click="createGroup" />
      </div>

      <v-row v-if="paged.length">
        <v-col v-for="group in paged" :key="group.id" cols="12" sm="6" md="4">
          <v-card class="pa-4">
            <div class="d-flex align-start justify-space-between mb-3">
              <div>
                <div class="text-body-1 font-weight-medium">{{ group.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ group.contacts_count ?? 0 }} contacts</div>
              </div>
              <v-avatar color="primary" variant="tonal" size="44"><v-icon icon="mdi-account-box-outline" /></v-avatar>
            </div>
            <div class="d-flex ga-2">
              <v-btn icon="mdi-pencil-outline" size="small" variant="tonal" @click="editGroup(group)" />
              <v-btn icon="mdi-view-list-outline" size="small" variant="tonal" @click="viewContacts(group)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="tonal" color="error" @click="deleteGroup(group)" />
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-card v-else variant="tonal" class="pa-8 text-center">
        <v-icon icon="mdi-account-box-outline" size="48" class="mb-2" />
        <div class="text-h6">No contact groups yet</div>
        <div class="text-body-2 text-medium-emphasis">Create a group, then import contacts into it for bulk messaging.</div>
      </v-card>

      <div v-if="filtered.length" class="d-flex justify-center mt-4">
        <v-pagination v-model="page" :length="pageCount" density="comfortable" total-visible="5" />
      </div>
    </template>

    <template v-else-if="view === 'form'">
      <div class="d-flex justify-end mb-4">
        <AppButton variant="outlined" prepend-icon="mdi-arrow-left" @click="view = 'groups'">Back</AppButton>
      </div>

      <v-card class="pa-6">
        <div class="text-h6 mb-4">{{ editingGroup ? 'Update' : 'Create' }}</div>

        <div class="text-caption text-medium-emphasis mb-1">STATUS</div>
        <v-radio-group v-model="formStatus" inline class="mb-4">
          <v-radio label="Enable" value="enable" />
          <v-radio label="Disable" value="disable" />
        </v-radio-group>

        <div class="text-caption text-medium-emphasis mb-1">GROUP CONTACT NAME</div>
        <v-text-field v-model="formName" variant="outlined" density="comfortable" placeholder="e.g. Test Demo" class="mb-4" />

        <div class="d-flex justify-space-between">
          <AppButton variant="outlined" @click="view = 'groups'">Back</AppButton>
          <AppButton :loading="saving" @click="submitForm">Submit</AppButton>
        </div>
      </v-card>
    </template>

    <ContactGroupDetail v-else-if="view === 'detail'" :group="openGroup" @back="backToGroups" />
  </div>
</template>
