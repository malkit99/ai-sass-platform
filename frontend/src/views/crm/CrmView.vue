<script setup>
import { computed, onMounted, reactive, ref, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrmStore } from '@/stores/crm/crm'
import AppButton from '@/components/AppButton.vue'
import CrmStatsRow from './components/CrmStatsRow.vue'
import CrmFilters from './components/CrmFilters.vue'
import CrmKanbanBoard from './components/CrmKanbanBoard.vue'
import NewLeadDialog from './components/NewLeadDialog.vue'

const crm = useCrmStore()
const { t } = useI18n()

const showNewLead = ref(false)
const targetStageId = ref(null)
const saving = ref(false)
const search = ref('')
const filter = ref('all') // all | hot | recent

onMounted(async () => {
  await crm.fetchPipeline()
  await crm.fetchLeads()
})

const filteredLeads = computed(() => {
  let leads = crm.leads

  if (filter.value === 'hot') leads = leads.filter((l) => l.is_hot)
  if (filter.value === 'recent') {
    const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000
    leads = leads.filter((l) => new Date(l.last_activity_at).getTime() >= sevenDaysAgo)
  }

  if (search.value.trim()) {
    const q = search.value.trim().toLowerCase()
    leads = leads.filter((l) =>
      [l.name, l.phone, l.email].some((field) => field?.toLowerCase().includes(q)),
    )
  }

  return leads
})

// vuedraggable needs a real, stable, mutable array per column bound with true
// v-model — a one-way :model-value rebuilt from a computed on every render
// fights Sortable's own DOM mutations and causes drops to silently revert.
// `columns[stageId]` is that per-column array; it's resynced from the store
// whenever the underlying leads data changes, and Sortable mutates it directly
// during a drag (with `@change` firing the API call as a side effect).
const columns = reactive({})

function syncColumns() {
  const stages = crm.pipeline?.stages ?? []
  for (const stage of stages) {
    columns[stage.id] = filteredLeads.value.filter((l) => l.stage_id === stage.id)
  }
}

// watchEffect (not watch(filteredLeads, ...)) because filteredLeads returns the
// same `crm.leads` array reference when no search/filter is active — moveLead
// mutates a lead in place, so the computed's cached reference never changes and
// a reference-based watch would never re-fire. watchEffect re-tracks whatever
// reactive properties syncColumns actually reads, so in-place mutations count.
watchEffect(syncColumns)

const stats = computed(() => {
  const wonStage = crm.pipeline?.stages.find((s) => s.name === 'Won')
  const revenue = crm.leads
    .flatMap((l) => l.deals ?? [])
    .filter((d) => d.status === 'won')
    .reduce((sum, d) => sum + Number(d.value), 0)

  return {
    total: crm.leads.length,
    hot: crm.leads.filter((l) => l.is_hot).length,
    won: wonStage ? crm.leads.filter((l) => l.stage_id === wonStage.id).length : 0,
    revenue,
    // Unread depends on the messaging/omnichannel module (Phase 1 continuation) —
    // no conversations/messages exist yet, so this is honestly 0 until then.
    unread: 0,
  }
})

function openNewLead(stageId = null) {
  targetStageId.value = stageId
  showNewLead.value = true
}

async function submitNewLead(values) {
  saving.value = true
  try {
    await crm.createLead({ ...values, stage_id: targetStageId.value })
    showNewLead.value = false
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-container
    fluid
    class="pa-4 pa-sm-6 d-flex flex-column"
    style="min-height: calc(100vh - var(--v-layout-top, 64px) - var(--v-layout-bottom, 0px))"
  >
    <div class="d-flex flex-wrap align-center ga-3 mb-4 flex-shrink-0">
      <v-avatar color="primary" rounded="lg" class="flex-shrink-0 d-none d-sm-flex"><v-icon>mdi-view-column-outline</v-icon></v-avatar>
      <div class="flex-grow-1 d-none d-sm-block" style="min-width: 0">
        <h2 class="text-h5 text-truncate">{{ crm.pipeline?.name ?? t('crm.pipelineFallback') }}</h2>
        <div class="text-caption text-medium-emphasis">{{ t('crm.subtitle') }}</div>
      </div>
      <AppButton prepend-icon="mdi-plus" class="flex-shrink-0" @click="openNewLead()">{{ t('crm.newLead') }}</AppButton>
    </div>

    <CrmStatsRow :stats="stats" />

    <CrmFilters
      :search="search"
      :filter="filter"
      @update:search="search = $event"
      @update:filter="filter = $event"
    />

    <CrmKanbanBoard v-if="crm.pipeline" :pipeline="crm.pipeline" :columns="columns" @add-lead="openNewLead" />

    <NewLeadDialog v-model="showNewLead" :saving="saving" @submit="submitNewLead" />
  </v-container>
</template>
