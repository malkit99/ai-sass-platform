<script setup>
import draggable from 'vuedraggable'
import { useI18n } from 'vue-i18n'
import { useCrmStore } from '@/stores/crm/crm'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import LeadCard from './LeadCard.vue'

const props = defineProps({
  pipeline: { type: Object, required: true },
  columns: { type: Object, required: true },
})

defineEmits(['add-lead'])

const crm = useCrmStore()
const alertStore = useAlertStore()
const { t } = useI18n()

async function onDeleteLead(lead) {
  try {
    await crm.deleteLead(lead.id)
    alertStore.success(`${lead.name} was deleted.`)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Could not delete this lead. Please try again.')
  }
}

async function onMoveLead(lead, stageId) {
  const targetStage = props.pipeline.stages.find((s) => s.id === stageId)
  try {
    await crm.moveLead(lead.id, stageId)
    alertStore.success(`${lead.name} moved to ${targetStage?.name ?? 'new stage'}.`)
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Could not move this lead. Please try again.')
  }
}

const stageColors = {
  'New Lead': 'blue',
  Contacted: 'amber',
  Qualified: 'orange',
  Won: 'green',
  Lost: 'grey',
}

function stageColor(name) {
  return stageColors[name] ?? 'blue-grey'
}

function nextStage(stage) {
  const stages = props.pipeline.stages
  const index = stages.findIndex((s) => s.id === stage.id)
  return stages[index + 1] ?? null
}

function prevStage(stage) {
  const stages = props.pipeline.stages
  const index = stages.findIndex((s) => s.id === stage.id)
  return stages[index - 1] ?? null
}

function onDragChange(event, stage) {
  if (event.added) {
    onMoveLead(event.added.element, stage.id)
  }
}
</script>

<template>
  <div class="d-flex ga-4 kanban-scroll flex-grow-1" style="overflow-x: auto; min-height: 0; overscroll-behavior-x: contain">
    <v-card
      v-for="stage in pipeline.stages"
      :key="stage.id"
      width="280"
      class="flex-shrink-0 d-flex flex-column"
      style="height: 100%; min-height: 600px"
      variant="outlined"
    >
      <v-card-title class="d-flex align-center text-subtitle-1 flex-shrink-0">
        <v-icon :color="stageColor(stage.name)" size="12" class="mr-2">mdi-circle</v-icon>
        {{ stage.name.toUpperCase() }}
        <v-spacer />
        <v-chip size="small">{{ (columns[stage.id] ?? []).length }}</v-chip>
      </v-card-title>
      <v-divider class="flex-shrink-0" />

      <div class="flex-grow-1 kanban-scroll" style="overflow-y: auto; min-height: 460px; max-height: 640px">
        <v-card-text>
          <draggable
            v-model="columns[stage.id]"
            item-key="id"
            group="leads"
            class="drag-area"
            ghost-class="drag-ghost"
            @change="onDragChange($event, stage)"
          >
            <template #item="{ element: lead }">
              <LeadCard
                :lead="lead"
                :prev-stage="prevStage(stage)"
                :next-stage="nextStage(stage)"
                @hot="crm.toggleHot(lead)"
                @prev="(stageId) => onMoveLead(lead, stageId)"
                @next="(stageId) => onMoveLead(lead, stageId)"
                @delete="onDeleteLead(lead)"
              />
            </template>
          </draggable>
          <p v-if="(columns[stage.id] ?? []).length === 0" class="text-caption text-medium-emphasis text-center mt-4">
            {{ t('crm.noLeads') }}
          </p>
        </v-card-text>
      </div>

      <v-divider class="flex-shrink-0" />
      <div class="pa-2 flex-shrink-0">
        <AppButton variant="outlined" block class="border-dashed" prepend-icon="mdi-plus" @click="$emit('add-lead', stage.id)">
          {{ t('crm.addLead') }}
        </AppButton>
      </div>
    </v-card>
  </div>
</template>

<style scoped>
.kanban-scroll {
  padding-bottom: 8px;
}

/* Sortable collapses an empty list container to 0 height, so a drop on an
   empty column actually lands on the sibling "No leads" text instead of the
   drag-area, and Sortable never sees it as a valid target. Keep a minimum
   droppable height even when the column has no cards. */
.drag-area {
  min-height: 60px;
}

.drag-ghost {
  opacity: 0.4;
}
</style>
