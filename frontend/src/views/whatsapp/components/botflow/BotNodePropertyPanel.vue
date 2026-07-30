<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { botNodeVisuals } from '@core/utils/botFlowNodeTypes'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  node: { type: Object, default: null },
  allNodes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update', 'delete', 'close'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const form = ref({})

const visual = computed(() => botNodeVisuals[props.node?.type] ?? {})

onMounted(() => whatsapp.fetchBotCredentials())

const providerOptions = [
  { title: 'OpenAI', value: 'openai' }, { title: 'Anthropic', value: 'anthropic' }, { title: 'Groq', value: 'groq' },
  { title: 'DeepSeek', value: 'deepseek' }, { title: 'Together', value: 'together' }, { title: 'OpenRouter', value: 'openrouter' },
  { title: 'Mistral', value: 'mistral' }, { title: 'Perplexity', value: 'perplexity' },
]
const credentialOptions = computed(() => whatsapp.botCredentials.map((c) => ({
  title: `${c.label} (${providerOptions.find((p) => p.value === c.provider)?.title ?? c.provider})`,
  value: c.id,
})))

const showAddCredential = ref(false)
const newCredential = ref({ provider: 'openai', label: '', api_key: '' })
const savingCredential = ref(false)

// Declared after showAddCredential — this fires immediately at setup time
// (immediate: true), so it must run after every ref it touches exists,
// not just before it's used in the template.
watch(() => props.node?.id, () => {
  form.value = JSON.parse(JSON.stringify(props.node?.data ?? {}))
  showAddCredential.value = false
}, { immediate: true })

function openAddCredential() {
  newCredential.value = { provider: form.value.preferred_provider || 'openai', label: '', api_key: '' }
  showAddCredential.value = true
}

async function saveCredential() {
  if (! newCredential.value.label.trim() || ! newCredential.value.api_key.trim()) {
    alertStore.warning('Give the connection a label and an API key.')

    return
  }

  savingCredential.value = true
  try {
    const created = await whatsapp.createBotCredential(newCredential.value)
    form.value.credential_id = created.id
    showAddCredential.value = false
    alertStore.success('Connection saved.')
  } catch (e) {
    alertStore.error(e.response?.data?.message ?? 'Failed to save this connection.')
  } finally {
    savingCredential.value = false
  }
}

const operatorOptions = [
  { title: 'Equals', value: 'equals' },
  { title: 'Contains', value: 'contains' },
  { title: 'Greater than', value: 'gt' },
  { title: 'Less than', value: 'lt' },
]

const fieldTypeOptions = [
  { title: 'Text', value: 'text' }, { title: 'Number', value: 'number' }, { title: 'Email', value: 'email' },
  { title: 'Phone', value: 'phone' }, { title: 'Date', value: 'date' }, { title: 'Time', value: 'time' }, { title: 'Website', value: 'website' },
]

const jumpTargets = computed(() => props.allNodes
  .filter((n) => n.id !== props.node?.id)
  .map((n) => ({ title: `${botNodeVisuals[n.type]?.label ?? n.type} (${n.id})`, value: n.id })))

function addButton() {
  form.value.buttons = [...(form.value.buttons ?? []), '']
}
function removeButton(index) {
  form.value.buttons = (form.value.buttons ?? []).filter((_, i) => i !== index)
}

// Plain computeds instead of `?.`/`??` directly inside template bindings —
// this project's bundled template linter mis-parses those operators there
// even though Vue's own compiler handles them fine (a tooling quirk, not a
// real bug — same issue already worked around in BotFlowCanvas.vue).
const buttonsList = computed(() => form.value.buttons || [])
const sectionRowsText = computed(() => {
  const sections = form.value.sections || []
  const rows = sections.length ? sections[0].rows || [] : []

  return rows.map((r) => r.title).join('\n')
})
function updateSectionRows(value) {
  form.value.sections = [{ title: 'Options', rows: value.split('\n').map((t) => t.trim()).filter(Boolean).map((title) => ({ title })) }]
}

function save() {
  emit('update', props.node.id, { ...form.value })
}
</script>

<template>
  <div v-if="node" class="pa-4">
    <div class="d-flex align-center justify-space-between mb-3">
      <div class="d-flex align-center ga-2">
        <v-avatar :color="visual.color" size="28" rounded>
          <v-icon :icon="visual.icon" size="14" color="white" />
        </v-avatar>
        <span class="font-weight-medium">{{ visual.label }}</span>
      </div>
      <v-btn icon="mdi-close" size="small" variant="text" @click="$emit('close')" />
    </div>

    <template v-if="node.type === 'text' || node.type === 'input' || node.type === 'buttons' || node.type === 'list'">
      <div class="text-caption text-medium-emphasis mb-1">MESSAGE</div>
      <v-textarea v-model="form.body" variant="outlined" density="comfortable" rows="3" auto-grow class="mb-3" hint="Use {{variable}} to insert a captured value" persistent-hint />
    </template>

    <template v-if="node.type === 'input'">
      <div class="text-caption text-medium-emphasis mb-1">FIELD TYPE</div>
      <v-select v-model="form.field_type" :items="fieldTypeOptions" variant="outlined" density="comfortable" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">SAVE ANSWER AS VARIABLE</div>
      <v-text-field v-model="form.variable_name" variant="outlined" density="comfortable" placeholder="e.g. name" class="mb-3" />
    </template>

    <template v-if="node.type === 'buttons'">
      <div class="text-caption text-medium-emphasis mb-1">BUTTONS (max 3)</div>
      <div v-for="(btn, i) in buttonsList" :key="i" class="d-flex ga-2 mb-2">
        <v-text-field v-model="form.buttons[i]" variant="outlined" density="comfortable" hide-details />
        <v-btn icon="mdi-close" size="small" variant="text" @click="removeButton(i)" />
      </div>
      <v-btn v-if="buttonsList.length < 3" variant="tonal" size="small" prepend-icon="mdi-plus" class="mb-3" @click="addButton">Add button</v-btn>
      <div class="text-caption text-medium-emphasis mb-1">SAVE REPLY AS VARIABLE</div>
      <v-text-field v-model="form.variable_name" variant="outlined" density="comfortable" placeholder="e.g. choice" class="mb-3" />
    </template>

    <template v-if="node.type === 'list'">
      <div class="text-caption text-medium-emphasis mb-1">LIST BUTTON TEXT</div>
      <v-text-field v-model="form.button_text" variant="outlined" density="comfortable" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">OPTIONS (one per line)</div>
      <v-textarea
        :model-value="sectionRowsText"
        variant="outlined" density="comfortable" rows="4" class="mb-3"
        @update:model-value="updateSectionRows"
      />
      <div class="text-caption text-medium-emphasis mb-1">SAVE REPLY AS VARIABLE</div>
      <v-text-field v-model="form.variable_name" variant="outlined" density="comfortable" placeholder="e.g. choice" class="mb-3" />
    </template>

    <template v-if="node.type === 'condition'">
      <div class="text-caption text-medium-emphasis mb-1">VARIABLE</div>
      <v-text-field v-model="form.variable_name" variant="outlined" density="comfortable" placeholder="e.g. name" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">OPERATOR</div>
      <v-select v-model="form.operator" :items="operatorOptions" variant="outlined" density="comfortable" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">VALUE</div>
      <v-text-field v-model="form.value" variant="outlined" density="comfortable" class="mb-3" />
      <div class="text-caption text-medium-emphasis">Connect the green (True) and red (False) handles to branch the flow.</div>
    </template>

    <template v-if="node.type === 'set_variable'">
      <div class="text-caption text-medium-emphasis mb-1">VARIABLE NAME</div>
      <v-text-field v-model="form.variable_name" variant="outlined" density="comfortable" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">VALUE</div>
      <v-text-field v-model="form.value" variant="outlined" density="comfortable" hint="Can reference {{other_variable}}" persistent-hint class="mb-3" />
    </template>

    <template v-if="node.type === 'webhook'">
      <div class="text-caption text-medium-emphasis mb-1">URL</div>
      <v-text-field v-model="form.url" variant="outlined" density="comfortable" placeholder="https://…" class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">BODY (JSON, optional)</div>
      <v-textarea v-model="form.body_template" variant="outlined" density="comfortable" rows="3" hint="Can reference {{variable}}" persistent-hint class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">SAVE RESPONSE AS VARIABLE (optional)</div>
      <v-text-field v-model="form.response_variable" variant="outlined" density="comfortable" class="mb-3" />
    </template>

    <template v-if="node.type === 'ai_reply'">
      <div class="text-caption text-medium-emphasis mb-1">CONNECTION</div>
      <v-select
        v-model="form.credential_id" :items="credentialOptions" variant="outlined" density="comfortable"
        placeholder="Choose a connection…" class="mb-1"
      />
      <v-btn v-if="! showAddCredential" variant="text" size="small" prepend-icon="mdi-plus" class="mb-3" @click="openAddCredential">
        Add connection
      </v-btn>

      <v-card v-if="showAddCredential" variant="outlined" class="pa-3 mb-3">
        <div class="text-caption text-medium-emphasis mb-1">PROVIDER</div>
        <v-select v-model="newCredential.provider" :items="providerOptions" variant="outlined" density="comfortable" class="mb-2" />
        <div class="text-caption text-medium-emphasis mb-1">LABEL</div>
        <v-text-field v-model="newCredential.label" variant="outlined" density="comfortable" placeholder="e.g. My OpenAI key" class="mb-2" />
        <div class="text-caption text-medium-emphasis mb-1">API KEY</div>
        <v-text-field v-model="newCredential.api_key" type="password" variant="outlined" density="comfortable" class="mb-2" />
        <div class="d-flex ga-2">
          <AppButton size="small" :loading="savingCredential" @click="saveCredential">Save</AppButton>
          <v-btn size="small" variant="text" @click="showAddCredential = false">Cancel</v-btn>
        </div>
      </v-card>

      <div class="text-caption text-medium-emphasis mb-1">SYSTEM PROMPT</div>
      <v-textarea v-model="form.system_prompt" variant="outlined" density="comfortable" rows="2" auto-grow class="mb-3" />
      <div class="text-caption text-medium-emphasis mb-1">USER PROMPT</div>
      <v-textarea
        v-model="form.user_prompt" variant="outlined" density="comfortable" rows="2" auto-grow class="mb-3"
        hint="{{last_message}} is whatever the user just sent" persistent-hint
      />
      <div class="text-caption text-medium-emphasis mb-1">SAVE REPLY AS VARIABLE (optional)</div>
      <v-text-field v-model="form.response_variable" variant="outlined" density="comfortable" class="mb-3" />
    </template>

    <template v-if="node.type === 'wait'">
      <div class="text-caption text-medium-emphasis mb-1">SECONDS</div>
      <v-text-field v-model.number="form.seconds" type="number" min="1" variant="outlined" density="comfortable" class="mb-3" />
    </template>

    <template v-if="node.type === 'jump'">
      <div class="text-caption text-medium-emphasis mb-1">JUMP TO NODE</div>
      <v-select v-model="form.target_node_id" :items="jumpTargets" variant="outlined" density="comfortable" class="mb-3" />
    </template>

    <template v-if="node.type === 'start' || node.type === 'end'">
      <div class="text-body-2 text-medium-emphasis">{{ node.type === 'start' ? 'Every flow begins here.' : 'The conversation flow ends here.' }}</div>
    </template>

    <div class="d-flex ga-2 mt-3">
      <v-btn v-if="node.type !== 'start'" color="primary" block @click="save">Apply</v-btn>
      <v-btn v-if="node.type !== 'start' && node.type !== 'end'" icon="mdi-delete-outline" color="error" variant="tonal" @click="$emit('delete', node.id)" />
    </div>
  </div>

  <div v-else class="pa-6 text-center text-body-2 text-medium-emphasis">
    Select a node to edit its settings.
  </div>
</template>
