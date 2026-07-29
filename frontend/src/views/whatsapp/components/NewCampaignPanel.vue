<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import { fireSuccess } from '@core/plugins/sweetalert'
import { templateTypeMeta } from '@core/utils/whatsappTemplateTypes'
import AppButton from '@/components/AppButton.vue'
import MediaPickerDialog from './template/MediaPickerDialog.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
})

const emit = defineEmits(['back', 'created', 'connect-channel'])

// Types the bridge can actually send today — matches WhatsappTemplate::ALL_SENDABLE_TYPES.
// Picking any other template type below is still allowed (so the filter/radio
// UI is fully browsable) but is rejected with a clear message on submit
// instead of silently doing nothing — currently just carousel, which needs a
// protocol this bridge doesn't support at all.
const SENDABLE_TYPES = [
  'text', 'text_image', 'text_video', 'text_document', 'text_audio',
  'text_buttons', 'text_lists', 'text_poll', 'interactive_buttons',
]
const INTERVAL_OPTIONS = Array.from({ length: 3600 }, (_, i) => i + 1)

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const checkingStatus = ref(true)
const contactGroupId = ref(null)
const showMediaPicker = ref(false)

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }

const messageTab = ref('text') // 'text' | 'media' | 'text_template' | 'buttons' | 'list' | 'poll' | 'template'
const selectedTemplateId = ref(null)

const scheduledAt = ref('') // datetime-local string, empty = run now
const allowedHours = ref([]) // empty = any hour
const recurringEnabled = ref(false)
const recurringFrequency = ref('daily')

const HOUR_OPTIONS = Array.from({ length: 24 }, (_, i) => ({ title: String(i), value: i }))
const DAYTIME_HOURS = Array.from({ length: 13 }, (_, i) => i + 6) // 6:00-18:00
const NIGHTTIME_HOURS = [19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5]
const ODD_HOURS = HOUR_OPTIONS.map((h) => h.value).filter((h) => h % 2 === 1)
const EVEN_HOURS = HOUR_OPTIONS.map((h) => h.value).filter((h) => h % 2 === 0)

function applyHourPreset(preset) {
  allowedHours.value = { daytime: DAYTIME_HOURS, nighttime: NIGHTTIME_HOURS, odd: ODD_HOURS, even: EVEN_HOURS }[preset]
}

onMounted(async () => {
  if (!whatsapp.contactGroups.length) whatsapp.fetchContactGroups()
  if (!whatsapp.templates.length) whatsapp.fetchTemplates()

  // The store's cached status can be stale (e.g. after a bridge restart that
  // never fired a disconnect webhook) — refresh each channel's live status
  // before trusting the list, same as SendMessagePanel.
  checkingStatus.value = true
  try {
    await Promise.all(
      props.channels.map(async (c) => {
        try {
          const status = await whatsapp.fetchStatus(c.id)
          whatsapp.updateChannelStatus(c.id, status)
        } catch {
          // Bridge unreachable — leave the cached status as-is rather than
          // failing the whole page over one instance.
        }
      }),
    )
  } finally {
    checkingStatus.value = false
  }
})

watch(messageTab, () => { selectedTemplateId.value = null })

const selectedGroup = computed(() => whatsapp.contactGroups.find((g) => g.id === contactGroupId.value) ?? null)

const filteredTemplates = computed(() => {
  if (messageTab.value === 'text_template') return whatsapp.templates.filter((t) => t.type === 'text')
  if (messageTab.value === 'buttons') return whatsapp.templates.filter((t) => ['text_buttons', 'interactive_buttons'].includes(t.type))
  if (messageTab.value === 'list') return whatsapp.templates.filter((t) => t.type === 'text_lists')
  if (messageTab.value === 'poll') return whatsapp.templates.filter((t) => t.type === 'text_poll')
  if (messageTab.value === 'template') return whatsapp.templates

  return []
})

const selectedTemplate = computed(() => filteredTemplates.value.find((t) => t.id === selectedTemplateId.value) ?? null)
const selectedTemplateUnsendable = computed(() => selectedTemplate.value && !SENDABLE_TYPES.includes(selectedTemplate.value.type))

// Buttons/list are sent via WhatsApp's native interactive-message protocol
// (the same one that renders quick-reply/list bubbles in the app itself) —
// switched to this after the older Business-template format silently failed
// (WhatsApp accepted it server-side, campaign showed "sent", nothing arrived
// on the recipient's phone). Should now deliver, but flagging it in the UI
// until confirmed on more than one test send.
const RECENTLY_FIXED_TYPES = ['text_buttons', 'interactive_buttons', 'text_lists']
const selectedTemplateBestEffort = computed(() => selectedTemplate.value && RECENTLY_FIXED_TYPES.includes(selectedTemplate.value.type))

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().required('Select a WhatsApp account'),
    name: yup.string().required('Campaign name is required'),
    // Requiredness for body/media_url depends on which MESSAGE TYPE tab is
    // active, which isn't something yup's schema can see — validated manually
    // in submit() instead, so a hidden field's stale value can't silently
    // block submission with no visible error.
    body: yup.string().nullable(),
    media_url: yup.string().nullable().url('Enter a valid URL'),
    spintax_enabled: yup.boolean(),
    warm_up_mode: yup.boolean(),
    emoji_randomizer: yup.boolean(),
    min_interval_seconds: yup.number().min(1).max(3600).required(),
    max_interval_seconds: yup.number().min(yup.ref('min_interval_seconds'), 'Must be ≥ min interval').max(3600).required(),
  }),
)

const { defineField, handleSubmit, errors, setErrors } = useForm({
  validationSchema: schema,
  initialValues: {
    channel_id: props.channels[0]?.id ?? null, name: '', body: '', media_url: '',
    spintax_enabled: false, warm_up_mode: false, emoji_randomizer: false, min_interval_seconds: 5, max_interval_seconds: 15,
  },
})

const [channelId, channelIdAttrs] = defineField('channel_id')
const [name, nameAttrs] = defineField('name')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')
const [spintaxEnabled] = defineField('spintax_enabled')
const [warmUpMode] = defineField('warm_up_mode')
const [emojiRandomizer] = defineField('emoji_randomizer')
const [minInterval, minIntervalAttrs] = defineField('min_interval_seconds')
const [maxInterval, maxIntervalAttrs] = defineField('max_interval_seconds')

const selectedChannel = computed(() => props.channels.find((c) => c.id === channelId.value) ?? null)
const selectedChannelDisconnected = computed(() => selectedChannel.value && selectedChannel.value.status !== 'connected')

const submit = handleSubmit(async (values) => {
  if (selectedChannelDisconnected.value) {
    alertStore.warning('This WhatsApp account is disconnected. Reconnect it first.')
    return
  }

  let payload = { ...values }
  let recipientCount = 0

  if (messageTab.value === 'text' || messageTab.value === 'media') {
    if (messageTab.value === 'text' && !body.value) {
      setErrors({ body: 'Message is required' })
      return
    }
    if (messageTab.value === 'media' && !mediaUrl.value) {
      setErrors({ media_url: 'Media URL is required' })
      return
    }

    payload.message_type = messageTab.value
  } else {
    if (!selectedTemplateId.value) {
      alertStore.warning('Select a template.')
      return
    }
    if (selectedTemplateUnsendable.value) {
      alertStore.warning('This template type can\'t be sent in a bulk campaign yet.')
      return
    }

    payload = { ...payload, message_type: 'template', template_id: selectedTemplateId.value }
  }

  if (scheduledAt.value) payload.scheduled_at = new Date(scheduledAt.value).toISOString()
  if (allowedHours.value.length) payload.allowed_hours = allowedHours.value
  if (recurringEnabled.value) payload.recurring_frequency = recurringFrequency.value

  // Contact group is the only recipient source — {{name}}/{{phone}}/custom
  // variables resolve per-recipient from the contact table, which a manually
  // typed phone list has no rows in.
  if (!contactGroupId.value) {
    alertStore.warning('Select a contact group.')
    return
  }

  payload = { ...payload, contact_group_id: contactGroupId.value }
  recipientCount = selectedGroup.value?.contacts_count ?? 0

  saving.value = true
  try {
    const campaign = await whatsapp.createCampaign(payload)
    fireSuccess('Campaign started', `"${campaign.name}" is sending to ${campaign.recipients?.length ?? recipientCount} recipient(s).`)
    emit('created')
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to start campaign.')
    }
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <div>
    <div class="campaign-banner d-flex align-center ga-4 pa-6 mb-4">
      <v-avatar color="white" variant="tonal" size="48"><v-icon icon="mdi-layers-outline" color="white" /></v-avatar>
      <div>
        <div class="text-h6 text-white">New Bulk Campaign</div>
        <div class="text-body-2" style="color: rgba(255, 255, 255, 0.85)">Send to multiple recipients with anti-ban pacing</div>
      </div>
    </div>

    <v-card class="pa-6">
      <v-form @submit.prevent="submit">
        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis mb-1">
          SELECT WHATSAPP ACCOUNT
          <v-progress-circular v-if="checkingStatus" size="12" width="2" indeterminate class="ml-1" />
        </div>
        <v-select
          v-model="channelId" v-bind="channelIdAttrs" :items="channels" placeholder="Select an instance"
          item-title="display_name" item-value="id" variant="outlined" density="comfortable" :error-messages="errors.channel_id" class="mb-2"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps">
              <template #title>{{ item.raw.display_name }}</template>
              <template #append>
                <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ item.raw.status }}</v-chip>
              </template>
            </v-list-item>
          </template>
          <template #selection="{ item }">{{ item.raw.display_name }}</template>
        </v-select>

        <v-alert v-if="selectedChannelDisconnected" type="warning" variant="tonal" density="compact" class="mb-4">
          <div class="d-flex align-center justify-space-between ga-2">
            <span class="text-body-2">This account is disconnected — you can't run a campaign from it right now.</span>
            <AppButton size="small" variant="flat" color="warning" @click="$emit('connect-channel', selectedChannel)">
              Connect now
            </AppButton>
          </div>
        </v-alert>

        <div class="text-caption text-medium-emphasis mb-1">CAMPAIGN NAME</div>
        <v-text-field
          v-model="name" v-bind="nameAttrs" placeholder="e.g. July Promo" variant="outlined" density="comfortable"
          :error-messages="errors.name" class="mb-4"
        />

        <div class="text-caption text-medium-emphasis mb-1">RECIPIENTS — CONTACT GROUP</div>
        <v-select
          v-model="contactGroupId" :items="whatsapp.contactGroups" item-title="name" item-value="id"
          placeholder="Select contact group" variant="outlined" density="comfortable" class="mb-1"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps">
              <template #append><v-chip size="x-small" variant="tonal">{{ item.raw.contacts_count ?? 0 }}</v-chip></template>
            </v-list-item>
          </template>
        </v-select>
        <div v-if="selectedGroup" class="text-caption text-medium-emphasis mb-4">
          {{ selectedGroup.contacts_count ?? 0 }} contacts in this group — numbers marked invalid are skipped automatically.
          Template variables ({{ '\{\{name\}\}' }}, {{ '\{\{phone\}\}' }}, custom params) fill from each contact's imported data.
        </div>
        <div v-else class="text-caption text-medium-emphasis mb-4">
          No group selected yet — import contacts via CSV/Excel under Contacts first.
        </div>

        <div class="text-caption text-medium-emphasis mb-1">MESSAGE TYPE</div>
        <v-btn-toggle v-model="messageTab" mandatory density="compact" class="mb-4 message-type-toggle flex-wrap" divided>
          <v-btn size="small" value="text" prepend-icon="mdi-format-text">Text</v-btn>
          <v-btn size="small" value="media" prepend-icon="mdi-image-outline">Media</v-btn>
          <v-btn size="small" value="text_template" prepend-icon="mdi-text-box-outline">Text template</v-btn>
          <v-btn size="small" value="buttons" prepend-icon="mdi-gesture-tap-button">Buttons</v-btn>
          <v-btn size="small" value="list" prepend-icon="mdi-format-list-bulleted">List message</v-btn>
          <v-btn size="small" value="poll" prepend-icon="mdi-poll">Poll</v-btn>
          <v-btn size="small" value="template" prepend-icon="mdi-file-document-multiple-outline">Template</v-btn>
        </v-btn-toggle>

        <template v-if="messageTab === 'text'">
          <div class="text-caption text-medium-emphasis mb-1">MESSAGE</div>
          <v-textarea
            v-model="body" v-bind="bodyAttrs" placeholder="Hi {{name}}, ..." variant="outlined" rows="3" auto-grow
            hint="Supports spintax: {Hi|Hello|Hola} there" persistent-hint :error-messages="errors.body" class="mb-4"
          />
        </template>
        <template v-else-if="messageTab === 'media'">
          <div class="text-caption text-medium-emphasis mb-1">MEDIA URL</div>
          <div class="d-flex ga-2 mb-4">
            <v-text-field
              v-model="mediaUrl" v-bind="mediaUrlAttrs" placeholder="https://…" variant="outlined" density="comfortable"
              :error-messages="errors.media_url"
            />
            <AppButton variant="tonal" prepend-icon="mdi-folder-open-outline" @click="showMediaPicker = true">Browse</AppButton>
          </div>
          <MediaPickerDialog v-model="showMediaPicker" type="image" @selected="mediaUrl = $event" />
          <div class="text-caption text-medium-emphasis mb-1">CAPTION (OPTIONAL)</div>
          <v-textarea v-model="body" placeholder="Supports spintax" variant="outlined" rows="2" auto-grow class="mb-4" />
        </template>

        <template v-else>
          <v-alert v-if="!filteredTemplates.length" type="info" variant="tonal" density="compact" class="mb-4">
            No saved templates of this type yet — create one under Templates first.
          </v-alert>
          <v-radio-group v-else v-model="selectedTemplateId" class="mb-2">
            <v-card v-for="t in filteredTemplates" :key="t.id" variant="outlined" class="d-flex align-center pa-3 mb-2">
              <v-radio :value="t.id" density="comfortable" hide-details />
              <div class="ml-2 flex-grow-1">
                <div class="text-body-2 font-weight-medium">{{ t.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ templateTypeMeta(t.type).label }}</div>
              </div>
            </v-card>
          </v-radio-group>
          <v-alert v-if="selectedTemplateUnsendable" type="warning" variant="tonal" density="compact" class="mb-4">
            Bulk-sending {{ templateTypeMeta(selectedTemplate.type).label.toLowerCase() }} campaigns isn't available yet — coming in a future update.
          </v-alert>
          <v-alert v-else-if="selectedTemplateBestEffort" type="info" variant="tonal" density="compact" class="mb-4">
            {{ templateTypeMeta(selectedTemplate.type).label }} messages use WhatsApp's native interactive-message format on this unofficial connection —
            recently switched from an older format that silently failed to deliver. Confirm it arrives on a real device before relying on it for a large campaign.
          </v-alert>
        </template>

        <v-checkbox v-model="spintaxEnabled" label="Enable spintax randomization" density="comfortable" hide-details class="mb-1" />
        <v-checkbox
          v-model="warmUpMode" label="Warm-up mode (ramps this number's daily send cap up gradually: 20/day → 300/day by day 5, to reduce ban risk)"
          density="comfortable" hide-details class="mb-1"
        />
        <v-checkbox
          v-model="emojiRandomizer" label="Enable emoji randomizer (adds a random emoji to each message)"
          density="comfortable" hide-details class="mb-4"
        />

        <div class="text-caption text-medium-emphasis mb-1">TIME POST</div>
        <v-text-field
          v-model="scheduledAt" type="datetime-local" variant="outlined" density="comfortable"
          hint="Leave empty to start sending immediately" persistent-hint class="mb-4"
        />

        <div class="d-flex ga-3 mb-6">
          <div class="flex-grow-1">
            <div class="text-caption text-medium-emphasis mb-1">RANDOM MESSAGE INTERVAL BY MINIMUM (SECOND)</div>
            <v-autocomplete
              v-model="minInterval" v-bind="minIntervalAttrs" :items="INTERVAL_OPTIONS" placeholder="Select min second"
              variant="outlined" density="comfortable" :error-messages="errors.min_interval_seconds"
            />
          </div>
          <div class="flex-grow-1">
            <div class="text-caption text-medium-emphasis mb-1">RANDOM MESSAGE INTERVAL BY MAXIMUM (SECOND)</div>
            <v-autocomplete
              v-model="maxInterval" v-bind="maxIntervalAttrs" :items="INTERVAL_OPTIONS" placeholder="Select max second"
              variant="outlined" density="comfortable" :error-messages="errors.max_interval_seconds"
            />
          </div>
        </div>

        <div class="text-overline text-medium-emphasis mb-2">SCHEDULE TIME</div>
        <div class="d-flex ga-4 mb-3">
          <a class="preset-link" @click.prevent="applyHourPreset('daytime')">Daytime</a>
          <a class="preset-link" @click.prevent="applyHourPreset('nighttime')">Nighttime</a>
          <a class="preset-link" @click.prevent="applyHourPreset('odd')">Odd</a>
          <a class="preset-link" @click.prevent="applyHourPreset('even')">Even</a>
        </div>
        <v-select
          v-model="allowedHours" :items="HOUR_OPTIONS" multiple chips closable-chips
          variant="outlined" density="comfortable" placeholder="Any hour"
        />
        <div class="text-caption text-medium-emphasis mt-1">
          The schedule allows you to set up a unique schedule by time for your campaign to run.
        </div>
        <div class="text-caption text-error mb-4">Set empty to campaign run anytime.</div>

        <v-checkbox v-model="recurringEnabled" label="Enable Recurring Schedule" density="comfortable" hide-details class="mb-1" />
        <div class="text-caption text-medium-emphasis mb-3 ml-8">
          Automatically re-send this campaign on a recurring basis (daily, weekly, or monthly).
        </div>
        <v-select
          v-if="recurringEnabled" v-model="recurringFrequency"
          :items="[{ title: 'Daily', value: 'daily' }, { title: 'Weekly', value: 'weekly' }, { title: 'Monthly', value: 'monthly' }]"
          variant="outlined" density="comfortable" class="mb-4" style="max-width: 240px"
        />

        <div class="d-flex justify-space-between">
          <AppButton variant="outlined" :disabled="saving" @click="$emit('back')">Back</AppButton>
          <AppButton :loading="saving" prepend-icon="mdi-send" @click="submit">Start Campaign</AppButton>
        </div>
      </v-form>
    </v-card>
  </div>
</template>

<style scoped>
.campaign-banner {
  border-radius: 12px;
  background: linear-gradient(120deg, #6a4cf0, #d24cd6);
}

.message-type-toggle :deep(.v-btn) {
  text-transform: none;
}

.preset-link {
  color: rgb(var(--v-theme-primary));
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.preset-link:hover {
  text-decoration: underline;
}
</style>
