<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireConfirm } from '@core/plugins/sweetalert'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'

const emit = defineEmits(['create', 'edit', 'leads'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const statsForm = ref(null)

onMounted(() => {
  whatsapp.fetchForms()
  whatsapp.fetchFormsDashboard()
})

const stats = computed(() => whatsapp.formsDashboard ?? { active_forms: 0, recent_leads: 0, generated_revenue: 0 })

function publicUrl(form) {
  return `${window.location.origin}/f/${form.slug}`
}

function formatDate(value) {
  return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })
}

function copyLink(form) {
  navigator.clipboard.writeText(publicUrl(form))
  alertStore.info('Form link copied.')
}

function viewForm(form) {
  window.open(publicUrl(form), '_blank')
}

async function remove(form) {
  const confirmed = await fireConfirm('Delete this form?', `"${form.name}" and its captured submissions will be permanently removed.`)
  if (!confirmed) return

  await whatsapp.deleteForm(form.id)
  await whatsapp.fetchFormsDashboard()
  alertStore.info('Form deleted.')
}
</script>

<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" sm="4">
        <v-card class="pa-4">
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase">Active Forms</div>
              <div class="text-h4 font-weight-bold">{{ stats.active_forms }}</div>
            </div>
            <v-avatar color="primary" variant="tonal" size="44"><v-icon icon="mdi-rocket-launch-outline" /></v-avatar>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card class="pa-4">
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase">Recent Leads</div>
              <div class="text-h4 font-weight-bold">{{ stats.recent_leads }}</div>
            </div>
            <v-avatar color="success" variant="tonal" size="44"><v-icon icon="mdi-account-plus-outline" /></v-avatar>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card class="pa-4">
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase">Generated Revenue</div>
              <div class="text-h4 font-weight-bold">{{ Number(stats.generated_revenue).toFixed(2) }}</div>
            </div>
            <v-avatar color="info" variant="tonal" size="44"><v-icon icon="mdi-currency-usd" /></v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">Lead Generation Forms</h2>
        <div class="text-caption text-medium-emphasis">Manage your WhatsApp automation forms and lead captures.</div>
      </div>
      <AppButton prepend-icon="mdi-plus-circle-outline" @click="$emit('create')">Create New Form</AppButton>
    </div>

    <v-row v-if="whatsapp.forms.length">
      <v-col v-for="form in whatsapp.forms" :key="form.id" cols="12" sm="6" md="4">
        <v-card class="pa-4">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center ga-2">
              <v-avatar color="primary" variant="tonal" size="40"><v-icon icon="mdi-file-document-outline" /></v-avatar>
              <v-chip :color="form.status === 'active' ? 'success' : 'default'" size="small" variant="flat">
                {{ form.status === 'active' ? 'LIVE' : 'DRAFT' }}
              </v-chip>
            </div>
            <v-menu>
              <template #activator="{ props: menuProps }">
                <v-btn icon="mdi-dots-horizontal" size="small" variant="text" v-bind="menuProps" />
              </template>
              <v-list density="compact">
                <v-list-item prepend-icon="mdi-pencil-outline" title="Edit" @click="$emit('edit', form)" />
                <v-list-item prepend-icon="mdi-delete-outline" title="Delete" base-color="error" @click="remove(form)" />
              </v-list>
            </v-menu>
          </div>

          <div class="text-subtitle-1 font-weight-medium mb-1">{{ form.name }}</div>
          <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-4 text-truncate">
            <v-icon icon="mdi-link-variant" size="14" />/f/{{ form.slug }}
          </div>

          <div class="d-flex flex-wrap ga-2">
            <v-btn size="small" variant="outlined" prepend-icon="mdi-chart-bar" class="flex-grow-1" @click="statsForm = form">Stats</v-btn>
            <v-btn size="small" variant="outlined" prepend-icon="mdi-share-variant-outline" class="flex-grow-1" @click="copyLink(form)">Share</v-btn>
            <v-btn size="small" variant="outlined" prepend-icon="mdi-account-multiple-outline" class="flex-grow-1" @click="$emit('leads', form)">Leads</v-btn>
            <v-btn
              size="small" variant="outlined" prepend-icon="mdi-open-in-new" class="flex-grow-1"
              :disabled="form.status !== 'active'" @click="viewForm(form)"
            >
              View
            </v-btn>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-folder-outline" size="48" class="mb-2" />
      <div class="text-h6">No Forms Created Yet</div>
      <div class="text-body-2 text-medium-emphasis mb-4">Create your first lead capture form to start collecting data.</div>
      <AppButton prepend-icon="mdi-auto-fix" @click="$emit('create')">Create First Form</AppButton>
    </v-card>

    <AppDialog :model-value="!!statsForm" title="Form Stats" @update:model-value="statsForm = null">
      <template v-if="statsForm">
        <div class="text-subtitle-1 font-weight-medium mb-3">{{ statsForm.name }}</div>
        <v-row>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">Submissions</div>
            <div class="text-h5 font-weight-bold">{{ statsForm.submissions_count }}</div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">Status</div>
            <div class="text-h6">{{ statsForm.status === 'active' ? 'Live' : 'Draft' }}</div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">Created</div>
            <div class="text-body-1">{{ formatDate(statsForm.created_at) }}</div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">Fields</div>
            <div class="text-body-1">{{ statsForm.fields?.length ?? 0 }}</div>
          </v-col>
        </v-row>
      </template>
    </AppDialog>
  </div>
</template>
