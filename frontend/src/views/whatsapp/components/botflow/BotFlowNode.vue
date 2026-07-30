<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import { botNodeVisuals } from '@core/utils/botFlowNodeTypes'

const props = defineProps({
  id: { type: String, required: true },
  type: { type: String, required: true },
  data: { type: Object, default: () => ({}) },
  selected: { type: Boolean, default: false },
})

const visual = computed(() => botNodeVisuals[props.type] ?? { label: props.type?.toUpperCase(), icon: 'mdi-help-box-outline', color: '#546E7A' })

const preview = computed(() => {
  const d = props.data ?? {}

  switch (props.type) {
    case 'start':
      return 'Start'
    case 'end':
      return 'End of flow'
    case 'text':
    case 'input':
    case 'buttons':
    case 'list':
      return d.body || '—'
    case 'condition':
      return `{{${d.variable_name || '…'}}} ${d.operator || 'equals'} ${d.value ?? ''}`
    case 'set_variable':
      return `${d.variable_name || '…'} = ${d.value ?? ''}`
    case 'webhook':
      return d.url || '—'
    case 'ai_reply':
      return d.credential_id ? `Connection #${d.credential_id}` : 'No connection selected'
    case 'wait':
      return `${d.seconds ?? 0}s`
    case 'jump':
      return `→ ${d.target_node_id || '…'}`
    default:
      return ''
  }
})

const hasTarget = computed(() => props.type !== 'start')
const isCondition = computed(() => props.type === 'condition')
const hasSingleSource = computed(() => props.type !== 'end' && !isCondition.value)
</script>

<template>
  <div class="bot-node" :class="{ 'bot-node-selected': selected }">
    <Handle v-if="hasTarget" type="target" :position="Position.Left" />

    <div class="bot-node-header" :style="{ background: visual.color }">
      <v-icon :icon="visual.icon" size="14" color="white" />
      <span>{{ visual.label }}</span>
    </div>
    <div class="bot-node-body">{{ preview }}</div>

    <Handle v-if="hasSingleSource" type="source" :position="Position.Right" />
    <template v-if="isCondition">
      <Handle id="true" type="source" :position="Position.Right" style="top: 35%" class="handle-true" />
      <Handle id="false" type="source" :position="Position.Right" style="top: 65%" class="handle-false" />
    </template>
  </div>
</template>

<style scoped>
.bot-node {
  min-width: 200px;
  max-width: 240px;
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
  overflow: hidden;
}

.bot-node-selected {
  outline: 2px solid rgb(var(--v-theme-primary));
}

.bot-node-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
  color: white;
}

.bot-node-body {
  padding: 10px;
  font-size: 12.5px;
  color: rgba(var(--v-theme-on-surface), .8);
  word-break: break-word;
  max-height: 60px;
  overflow: hidden;
}

.handle-true {
  background: rgb(var(--v-theme-success));
}

.handle-false {
  background: rgb(var(--v-theme-error));
}
</style>
