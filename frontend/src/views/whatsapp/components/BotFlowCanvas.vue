<script setup>
import { computed, onMounted, ref } from 'vue'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { MiniMap } from '@vue-flow/minimap'
import '@vue-flow/core/dist/style.css'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'
import BotFlowPalette from './botflow/BotFlowPalette.vue'
import BotFlowNode from './botflow/BotFlowNode.vue'
import BotNodePropertyPanel from './botflow/BotNodePropertyPanel.vue'
import { newNodeId, defaultNodeData } from '@core/utils/botFlowNodeTypes'

const props = defineProps({
  bot: { type: Object, required: true },
})

const emit = defineEmits(['back'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const name = ref(props.bot.name)
const status = ref(props.bot.status)
const triggerKeywords = ref((props.bot.trigger_keywords || []).join(', '))
const saving = ref(false)

const {
  nodes, edges, addNodes, addEdges, removeNodes, findNode, updateNodeData,
  onConnect, onNodeClick, onPaneClick, onNodeContextMenu, screenToFlowCoordinate, toObject,
} = useVueFlow()

const wrapperEl = ref(null)
const selectedNodeId = ref(null)
const selectedNode = computed(() => (selectedNodeId.value ? findNode(selectedNodeId.value) : null))

// Drawer opens the instant a node is clicked and closes on backdrop-click/
// Esc/close-button — clearing selectedNodeId in either direction keeps the
// two in sync without a separate "drawer open" flag to fall out of step.
const drawerOpen = computed({
  get: () => selectedNodeId.value !== null,
  set: (value) => {
    if (! value) selectedNodeId.value = null
  },
})

onMounted(() => {
  addNodes(props.bot.flow_definition?.nodes ?? [])
  addEdges(props.bot.flow_definition?.edges ?? [])
})

onConnect((connection) => addEdges([connection]))
onNodeClick(({ node }) => { selectedNodeId.value = node.id })
onPaneClick(() => {
  selectedNodeId.value = null
  contextMenu.value.open = false
})

// Right-click menu (Config/Duplicate/Delete) — a plain positioned element,
// not v-menu, for the same reason the property panel isn't
// v-navigation-drawer: Vuetify's overlay click-outside detection doesn't
// reliably see clicks landing inside vue-flow's canvas.
const contextMenu = ref({ open: false, x: 0, y: 0, nodeId: null })
const contextMenuNode = computed(() => (contextMenu.value.nodeId ? findNode(contextMenu.value.nodeId) : null))
const contextMenuNodeType = computed(() => contextMenuNode.value ? contextMenuNode.value.type : null)
const canDuplicateContextNode = computed(() => contextMenuNodeType.value !== 'start')
const canDeleteContextNode = computed(() => ! ['start', 'end'].includes(contextMenuNodeType.value))

onNodeContextMenu(({ event, node }) => {
  event.preventDefault()
  contextMenu.value = { open: true, x: event.clientX, y: event.clientY, nodeId: node.id }
})

function closeContextMenu() {
  contextMenu.value.open = false
}

function menuConfig() {
  selectedNodeId.value = contextMenu.value.nodeId
  closeContextMenu()
}

function menuDuplicate() {
  const original = contextMenuNode.value
  closeContextMenu()
  if (! original || original.type === 'start') return

  addNodes([{
    id: newNodeId(original.type),
    type: original.type,
    position: { x: original.position.x + 40, y: original.position.y + 40 },
    data: JSON.parse(JSON.stringify(original.data ?? {})),
  }])
}

function menuDelete() {
  const id = contextMenu.value.nodeId
  closeContextMenu()
  if (! id || ['start', 'end'].includes(contextMenuNode.value?.type)) return

  onDeleteNode(id)
}

function onDragOver(event) {
  event.preventDefault()
  event.dataTransfer.dropEffect = 'move'
}

function onDrop(event) {
  const raw = event.dataTransfer.getData('application/bot-flow-node')
  if (! raw) return

  const item = JSON.parse(raw)
  if (! item.working) return

  const bounds = wrapperEl.value.getBoundingClientRect()
  const position = screenToFlowCoordinate({ x: event.clientX - bounds.left, y: event.clientY - bounds.top })

  addNodes([{
    id: newNodeId(item.nodeType),
    type: item.nodeType,
    position,
    data: defaultNodeData(item),
  }])
}

function onUpdateNode(id, data) {
  updateNodeData(id, data, { replace: true })
}

function onDeleteNode(id) {
  removeNodes([id])
  if (selectedNodeId.value === id) selectedNodeId.value = null
}

async function save() {
  if (! name.value.trim()) {
    alertStore.warning('Give the bot a name first.')

    return
  }

  saving.value = true
  try {
    const { nodes: n, edges: e } = toObject()
    await whatsapp.updateBotFlow(props.bot.id, {
      channel_id: props.bot.channel_id,
      name: name.value,
      status: status.value,
      trigger_keywords: triggerKeywords.value.split(',').map((k) => k.trim()).filter(Boolean),
      flow_definition: { nodes: n, edges: e },
    })
    alertStore.success('Bot saved.')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save this bot.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="d-flex flex-column" style="height: 100%">
    <div class="d-flex flex-wrap align-center ga-3 mb-3">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$emit('back')" />
      <v-text-field v-model="name" variant="outlined" density="comfortable" hide-details style="max-width: 260px" placeholder="Bot name" />
      <v-text-field
        v-model="triggerKeywords" variant="outlined" density="comfortable" hide-details style="max-width: 320px"
        placeholder="Trigger keywords (comma separated)" prepend-inner-icon="mdi-key-outline"
      />
      <v-switch
        v-model="status" true-value="active" false-value="draft" color="success" density="comfortable"
        hide-details :label="status === 'active' ? 'Active' : 'Draft'" class="ml-2"
      />
      <v-spacer />
      <AppButton :loading="saving" prepend-icon="mdi-content-save" @click="save">Save</AppButton>
    </div>

    <div class="d-flex flex-grow-1" style="min-height: 0">
      <BotFlowPalette />

      <div ref="wrapperEl" class="flex-grow-1 canvas-wrapper" @dragover="onDragOver" @drop="onDrop">
        <VueFlow :nodes="nodes" :edges="edges" :default-viewport="{ zoom: 1 }" :min-zoom="0.2" :max-zoom="2" fit-view-on-init>
          <template #node-start="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-text="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-input="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-buttons="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-list="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-condition="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-set_variable="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-webhook="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-ai_reply="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-wait="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-jump="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>
          <template #node-end="nodeProps">
            <BotFlowNode v-bind="nodeProps" />
          </template>

          <Background pattern-color="#aaa" :gap="16" />
          <Controls />
          <MiniMap pannable zoomable />
        </VueFlow>
      </div>
    </div>

    <div v-if="drawerOpen" class="node-panel-scrim" @click="selectedNodeId = null" />

    <v-card class="node-panel" :class="{ 'node-panel-open': drawerOpen }" rounded="0">
      <BotNodePropertyPanel :node="selectedNode" :all-nodes="nodes" @update="onUpdateNode" @delete="onDeleteNode" @close="selectedNodeId = null" />
    </v-card>

    <div v-if="contextMenu.open" class="context-menu-scrim" @click="closeContextMenu" @contextmenu.prevent="closeContextMenu" />

    <v-card
      v-if="contextMenu.open" class="context-menu" elevation="6"
      :style="{ top: `${contextMenu.y}px`, left: `${contextMenu.x}px` }"
    >
      <v-list density="compact" nav>
        <v-list-item prepend-icon="mdi-cog-outline" title="Config" @click="menuConfig" />
        <v-list-item
          prepend-icon="mdi-content-copy" title="Duplicate"
          :disabled="! canDuplicateContextNode" @click="menuDuplicate"
        />
        <v-list-item
          prepend-icon="mdi-delete-outline" title="Delete" base-color="error"
          :disabled="! canDeleteContextNode" @click="menuDelete"
        />
      </v-list>
    </v-card>
  </div>
</template>

<style scoped>
.canvas-wrapper {
  position: relative;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

/*
 * Plain self-managed panel + scrim instead of v-navigation-drawer —
 * Vuetify's temporary-drawer scrim/click:outside detection didn't reliably
 * close on a click landing inside vue-flow's canvas (vue-flow manages its
 * own pointer events for pan/zoom/drag), and even with :scrim="false" its
 * own invisible click-catching backdrop still seemed to swallow the click
 * before our own handler ever ran. This owns both elements directly with
 * no framework-internal overlay machinery in between, so there's nothing
 * else that can intercept the click first.
 */
.node-panel-scrim {
  position: fixed;
  inset: 0;
  z-index: 1004;
}

.node-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 320px;
  z-index: 1005;
  overflow-y: auto;
  transform: translateX(100%);
  transition: transform .2s ease;
}

.node-panel-open {
  transform: translateX(0);
}

.context-menu-scrim {
  position: fixed;
  inset: 0;
  z-index: 1006;
}

.context-menu {
  position: fixed;
  z-index: 1007;
  min-width: 180px;
}
</style>
