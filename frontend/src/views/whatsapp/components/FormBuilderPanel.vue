<script setup>
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { formFieldGroups, displayOnlyTypes, choiceTypes, fieldTypeMeta } from '@core/utils/whatsappFormFieldTypes'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'

const props = defineProps({
  editing: { type: Object, default: null },
})

const emit = defineEmits(['back', 'saved'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const showConfig = ref(false)
const configTab = ref('general')

const name = ref(props.editing?.name ?? '')
const active = ref(props.editing?.status === 'active')
const channelId = ref(props.editing?.channel_id ?? null)
const slug = ref(props.editing?.slug ?? '')
const successMessage = ref(props.editing?.success_message ?? '')
const successAction = ref(props.editing?.success_action ?? 'message')
const successRedirectUrl = ref(props.editing?.success_redirect_url ?? '')

const fields = ref(
  props.editing?.fields?.length
    ? JSON.parse(JSON.stringify(props.editing.fields))
    : [],
)

// Only General/WhatsApp/"Create Lead" are backed by real behavior — see
// FormPublicController's docblock. assign_to/ai_qualification/payment_enabled/
// ivr_enabled are stored but never acted on (no round-robin assignment, AI,
// Commerce, or IVR system exists in this codebase yet) — kept as inert
// dropdowns matching the reference screenshots rather than removed, so
// nothing here claims to do more than it does.
const automation = ref({
  recaptcha_enabled: props.editing?.automation_config?.recaptcha_enabled ?? false,
  admin_notify_enabled: props.editing?.automation_config?.admin_notify_enabled ?? false,
  admin_notify_phone: props.editing?.automation_config?.admin_notify_phone ?? '',
  admin_notify_message: props.editing?.automation_config?.admin_notify_message ?? 'New form submission: {data}',
  user_reply_enabled: props.editing?.automation_config?.user_reply_enabled ?? false,
  user_reply_message: props.editing?.automation_config?.user_reply_message ?? 'Hi {name}, thanks for your response!',
  create_lead: props.editing?.automation_config?.create_lead ?? 'instant',
  assign_to: props.editing?.automation_config?.assign_to ?? 'unassigned',
  ai_qualification: props.editing?.automation_config?.ai_qualification ?? 'disabled',
  payment_enabled: props.editing?.automation_config?.payment_enabled ?? false,
  ivr_enabled: props.editing?.automation_config?.ivr_enabled ?? false,
})

if (!whatsapp.channels.length) whatsapp.fetchChannels()

let nextId = 1
function addField(type) {
  fields.value.push({
    id: `f${Date.now()}_${nextId++}`,
    type,
    label: fieldTypeMeta(type).label,
    placeholder: '',
    required: false,
    options: choiceTypes.includes(type) ? ['Option 1'] : undefined,
  })
}

function removeField(index) {
  fields.value.splice(index, 1)
}

function isDisplayOnly(type) {
  return displayOnlyTypes.includes(type)
}

function isChoice(type) {
  return choiceTypes.includes(type)
}

const hasWhatsappField = computed(() => fields.value.some((f) => f.type === 'whatsapp'))

const publicUrl = computed(() => (props.editing ? `${window.location.origin}/f/${props.editing.slug}` : ''))
const embedCode = computed(() => `<iframe src="${publicUrl.value}" width="100%" height="640" frameborder="0"></iframe>`)

function copyLink() {
  navigator.clipboard.writeText(publicUrl.value)
  alertStore.info('Form link copied.')
}
function copyEmbedCode() {
  navigator.clipboard.writeText(embedCode.value)
  alertStore.info('Embed code copied.')
}
function openForm() {
  window.open(publicUrl.value, '_blank')
}

const submit = async () => {
  if (!name.value.trim()) {
    alertStore.warning('Enter a form name.')
    return
  }
  if (!fields.value.length) {
    alertStore.warning('Add at least one element to the form.')
    return
  }

  const payload = {
    name: name.value,
    status: active.value ? 'active' : 'draft',
    channel_id: channelId.value,
    slug: slug.value || null,
    fields: fields.value.map((f) => ({
      id: f.id,
      type: f.type,
      label: f.label,
      placeholder: f.placeholder || null,
      required: !!f.required,
      options: isChoice(f.type) ? (f.options ?? []).filter(Boolean) : undefined,
    })),
    success_message: successMessage.value || null,
    success_action: successAction.value,
    success_redirect_url: successAction.value === 'redirect' ? successRedirectUrl.value : null,
    automation_config: automation.value,
  }

  saving.value = true
  try {
    if (props.editing) {
      await whatsapp.updateForm(props.editing.id, payload)
      alertStore.success('Form updated.')
    } else {
      await whatsapp.createForm(payload)
      alertStore.success('Form created.')
    }
    emit('saved')
  } catch (e) {
    if (e.response?.status === 422) {
      alertStore.error('Check the form — some fields are invalid.')
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save form.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="builder d-flex" style="height: 100%">
    <div class="palette pa-4 flex-shrink-0">
      <div v-for="group in formFieldGroups" :key="group.label" class="mb-4">
        <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold mb-1">{{ group.label }}</div>
        <v-list nav density="compact">
          <v-list-item
            v-for="type in group.types" :key="type.value" :title="type.label"
            rounded="lg" @click="addField(type.value)"
          >
            <template #prepend>
              <v-icon :icon="type.icon" :color="type.color" />
            </template>
          </v-list-item>
        </v-list>
      </div>
    </div>

    <div class="flex-grow-1 d-flex flex-column">
      <div class="d-flex align-center ga-3 pa-4" style="border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))">
        <v-btn icon="mdi-arrow-left" variant="text" @click="$emit('back')" />
        <div class="flex-grow-1">
          <div class="text-h6">Smart Form Builder</div>
          <div class="text-caption text-medium-emphasis">Design, automate &amp; capture</div>
        </div>
        <v-switch v-model="active" label="Active" color="success" density="comfortable" hide-details class="mr-2" />
        <AppButton variant="tonal" prepend-icon="mdi-lightning-bolt-outline" @click="showConfig = true">Automations</AppButton>
        <AppButton :loading="saving" prepend-icon="mdi-cloud-upload-outline" @click="submit">Save Changes</AppButton>
      </div>

      <div class="flex-grow-1 pa-6" style="overflow-y: auto">
        <v-card class="pa-6 mx-auto mb-4" max-width="820">
          <v-text-field
            v-model="name" placeholder="Enter Form Name" variant="plain" density="comfortable"
            class="text-h6 text-center mb-1"
          />
          <div class="text-caption text-medium-emphasis text-center mb-4">Drag and drop elements here to build your form</div>
          <v-divider class="mb-4" />

          <draggable v-model="fields" item-key="id" ghost-class="drag-ghost" handle=".drag-handle">
            <template #item="{ element: field, index }">
              <v-card variant="outlined" class="pa-4 mb-3">
                <div class="d-flex align-start ga-2">
                  <v-icon icon="mdi-drag-vertical" class="drag-handle mt-2" style="cursor: grab" />
                  <v-icon :icon="fieldTypeMeta(field.type).icon" class="mt-2" />
                  <div class="flex-grow-1">
                    <template v-if="isDisplayOnly(field.type)">
                      <v-text-field
                        v-model="field.label" :label="fieldTypeMeta(field.type).label" variant="outlined"
                        density="compact" hide-details class="mb-2"
                      />
                    </template>
                    <template v-else>
                      <div class="d-flex ga-2 mb-2">
                        <v-text-field v-model="field.label" label="Label" variant="outlined" density="compact" hide-details />
                        <v-text-field v-model="field.placeholder" label="Placeholder" variant="outlined" density="compact" hide-details />
                      </div>
                      <v-checkbox v-model="field.required" label="Required" density="compact" hide-details class="mb-1" />

                      <template v-if="isChoice(field.type)">
                        <div class="text-caption text-medium-emphasis mb-1">OPTIONS</div>
                        <v-combobox
                          v-model="field.options" multiple chips closable-chips variant="outlined" density="compact"
                          hint="Press enter after each option" persistent-hint
                        />
                      </template>
                    </template>
                  </div>
                  <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="removeField(index)" />
                </div>
              </v-card>
            </template>
          </draggable>

          <v-card v-if="!fields.length" variant="tonal" class="pa-8 text-center">
            <v-icon icon="mdi-plus-circle-outline" size="40" class="mb-2" />
            <div class="text-body-1">Click elements on the left to start</div>
          </v-card>
        </v-card>

        <v-card v-if="editing" class="pa-6 mx-auto" max-width="820">
          <div class="d-flex align-center ga-2 mb-3">
            <v-icon icon="mdi-vector-link" color="primary" />
            <span class="text-subtitle-1 font-weight-medium">Public Form Link</span>
          </div>
          <div class="d-flex ga-2 mb-3">
            <v-text-field :model-value="publicUrl" readonly variant="outlined" density="comfortable" />
            <v-btn icon="mdi-content-copy" color="primary" variant="flat" @click="copyLink" />
          </div>
          <div class="d-flex flex-wrap ga-2">
            <AppButton variant="tonal" color="success" prepend-icon="mdi-open-in-new" @click="openForm">
              Open Form
            </AppButton>
            <AppButton variant="tonal" prepend-icon="mdi-code-tags" @click="copyEmbedCode">Get Embed Code</AppButton>
          </div>
        </v-card>
      </div>
    </div>

    <AppDialog v-model="showConfig" title="Global Form Configuration" max-width="620">
      <v-tabs v-model="configTab" class="mb-4">
        <v-tab value="general">General</v-tab>
        <v-tab value="whatsapp">WhatsApp</v-tab>
        <v-tab value="crm">CRM &amp; AI</v-tab>
        <v-tab value="payment">Payment</v-tab>
        <v-tab value="ivr">IVR Call</v-tab>
      </v-tabs>

      <v-window v-model="configTab">
        <v-window-item value="general">
          <div class="text-caption text-medium-emphasis mb-1">FORM SLUG (UNIQUE URL)</div>
          <v-text-field v-model="slug" placeholder="lead-form" variant="outlined" density="comfortable" class="mb-4" />

          <div class="text-caption text-medium-emphasis mb-1">WHATSAPP ACCOUNT</div>
          <v-select
            v-model="channelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
            placeholder="Select account" variant="outlined" density="comfortable" class="mb-4" clearable
          />

          <div class="text-caption text-medium-emphasis mb-1">SUCCESS ACTION</div>
          <v-select
            v-model="successAction"
            :items="[{ title: 'Show Message', value: 'message' }, { title: 'Redirect to URL', value: 'redirect' }]"
            variant="outlined" density="comfortable" class="mb-2"
          />
          <v-textarea
            v-if="successAction === 'message'" v-model="successMessage" placeholder="Thank you! Your submission has been received."
            variant="outlined" rows="2" auto-grow class="mb-4"
          />
          <v-text-field
            v-else v-model="successRedirectUrl" placeholder="https://example.com/thanks" variant="outlined"
            density="comfortable" class="mb-4"
          />

          <v-switch v-model="automation.recaptcha_enabled" label="Enable reCAPTCHA" color="primary" density="comfortable" hide-details />
        </v-window-item>

        <v-window-item value="whatsapp">
          <v-alert v-if="!channelId" type="warning" variant="tonal" density="compact" class="mb-4">
            Select a WhatsApp account under the General tab first — neither automation below can send anything without one.
          </v-alert>

          <v-card variant="outlined" class="pa-4 mb-4">
            <div class="d-flex align-center ga-2 mb-2">
              <v-icon icon="mdi-bell-outline" color="primary" />
              <span class="text-subtitle-2 font-weight-medium">Admin Notification</span>
            </div>
            <v-switch v-model="automation.admin_notify_enabled" label="Notify an admin number on submission" color="primary" density="comfortable" />
            <template v-if="automation.admin_notify_enabled">
              <v-text-field
                v-model="automation.admin_notify_phone" placeholder="Admin WhatsApp Number" variant="outlined"
                density="comfortable" class="mb-2"
              />
              <v-textarea
                v-model="automation.admin_notify_message" placeholder="New form submission: {data}" variant="outlined"
                rows="2" auto-grow hint="{data} = all submitted fields, {name} = first text field" persistent-hint
              />
            </template>
          </v-card>

          <v-card variant="outlined" class="pa-4">
            <div class="d-flex align-center ga-2 mb-2">
              <v-icon icon="mdi-account-check-outline" color="success" />
              <span class="text-subtitle-2 font-weight-medium">User Auto-Reply</span>
            </div>
            <v-switch
              v-model="automation.user_reply_enabled" label="Auto-reply to the submitter" color="primary"
              density="comfortable" :disabled="!hasWhatsappField"
            />
            <v-alert v-if="!hasWhatsappField" type="warning" variant="tonal" density="compact" class="mb-2">
              This toggle is disabled because the form has no "WhatsApp Number" field yet — add one from the
              Basic Fields palette so there's a number to reply to.
            </v-alert>
            <v-textarea
              v-if="automation.user_reply_enabled && hasWhatsappField" v-model="automation.user_reply_message"
              placeholder="Hi {name}, thanks for your response!" variant="outlined" rows="2" auto-grow
              hint="{name} = first text field's value" persistent-hint
            />
          </v-card>
        </v-window-item>

        <v-window-item value="crm">
          <v-card variant="outlined" class="pa-4 mb-4">
            <div class="d-flex align-center ga-2 mb-3">
              <v-icon icon="mdi-database-outline" color="info" />
              <span class="text-subtitle-2 font-weight-medium">CRM Settings</span>
            </div>
            <v-row>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis mb-1">CREATE LEAD</div>
                <v-select
                  v-model="automation.create_lead"
                  :items="[{ title: 'Instant', value: 'instant' }, { title: 'Disabled', value: 'disabled' }]"
                  variant="outlined" density="comfortable"
                />
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis mb-1">ASSIGN TO</div>
                <v-select
                  v-model="automation.assign_to" :items="[{ title: 'Unassigned', value: 'unassigned' }]"
                  variant="outlined" density="comfortable" disabled
                  hint="Lead assignment isn't built yet" persistent-hint
                />
              </v-col>
            </v-row>
          </v-card>

          <v-card variant="outlined" class="pa-4">
            <div class="d-flex align-center ga-2 mb-2">
              <v-icon icon="mdi-robot-outline" />
              <span class="text-subtitle-2 font-weight-medium">AI Qualification</span>
            </div>
            <v-select
              v-model="automation.ai_qualification" :items="[{ title: 'Disabled', value: 'disabled' }]"
              variant="outlined" density="comfortable" disabled hint="AI qualification isn't built yet" persistent-hint
            />
          </v-card>
        </v-window-item>

        <v-window-item value="payment">
          <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
            Payment will be required before form submission completes.
          </v-alert>
          <div class="text-caption text-medium-emphasis mb-1">ENABLE PAYMENT?</div>
          <v-select
            v-model="automation.payment_enabled" :items="[{ title: 'No', value: false }]"
            variant="outlined" density="comfortable" disabled hint="Payment collection isn't built yet" persistent-hint
          />
        </v-window-item>

        <v-window-item value="ivr">
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            Automatically trigger a voice call when form is submitted.
          </v-alert>
          <div class="text-caption text-medium-emphasis mb-1">ENABLE AUTO-CALL?</div>
          <v-select
            v-model="automation.ivr_enabled" :items="[{ title: 'No Call', value: false }]"
            variant="outlined" density="comfortable" disabled hint="IVR/voice calling isn't built yet" persistent-hint
          />
        </v-window-item>
      </v-window>

      <template #actions>
        <AppButton variant="flat" block @click="showConfig = false">Apply Advanced Automations</AppButton>
      </template>
    </AppDialog>
  </div>
</template>

<style scoped>
.palette {
  width: 240px;
  /* Bounded independently of the outer page's own scroll (WhatsappView's
     container scrolls the whole page, not per-panel), so this actually
     gets a scrollbar once the field list overflows instead of just
     growing the page. */
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.palette :deep(.v-list-item-title) {
  font-weight: 700;
}

.drag-ghost {
  opacity: 0.4;
}
</style>
