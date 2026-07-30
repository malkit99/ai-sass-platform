<script setup>
import { botNodeGroups } from '@core/utils/botFlowNodeTypes'

// Matches screenshots 84/85 (Inputs and Integrations are 2-column grids)
// vs. 86/87 (Logic and Events are single-column lists).
const twoColumnGroups = ['Inputs', 'Integrations']

function onDragStart(event, item) {
  if (! item.working) {
    event.preventDefault()

    return
  }

  event.dataTransfer.setData('application/bot-flow-node', JSON.stringify(item))
  event.dataTransfer.effectAllowed = 'move'
}
</script>

<template>
  <div class="bot-palette">
    <div v-for="group in botNodeGroups" :key="group.label" class="mb-4">
      <div class="palette-group-label">{{ group.label.toUpperCase() }}</div>
      <div class="palette-grid" :class="{ 'palette-grid-2col': twoColumnGroups.includes(group.label) }">
        <v-tooltip v-for="item in group.types" :key="item.value" :text="item.working ? item.label : `${item.label} — coming soon`" location="top">
          <template #activator="{ props: tooltipProps }">
            <div
              v-bind="tooltipProps" class="palette-item" :class="{ 'palette-item-locked': ! item.working }"
              :draggable="item.working" @dragstart="onDragStart($event, item)"
            >
              <span class="palette-item-icon" :style="{ background: `${item.color}1A`, color: item.color }">
                <v-icon :icon="item.icon" size="18" />
              </span>
              <span class="palette-item-label text-truncate">{{ item.label }}</span>
              <v-icon v-if="! item.working" icon="mdi-lock-outline" size="13" class="ml-auto flex-shrink-0" />
            </div>
          </template>
        </v-tooltip>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bot-palette {
  width: 260px;
  flex-shrink: 0;
  height: 100%;
  overflow-y: auto;
  padding: 8px;
}

.palette-group-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .04em;
  color: rgba(var(--v-theme-on-surface), .5);
  padding: 4px 4px 8px;
}

.palette-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
}

.palette-grid-2col {
  grid-template-columns: 1fr 1fr;
}

.palette-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: grab;
  background: rgb(var(--v-theme-surface));
  min-width: 0;
}

.palette-item:hover:not(.palette-item-locked) {
  border-color: rgb(var(--v-theme-primary));
}

.palette-item-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  flex-shrink: 0;
}

.palette-item-label {
  font-size: 13.5px;
  font-weight: 500;
}

.palette-item-locked {
  opacity: .5;
  cursor: not-allowed;
}
</style>
