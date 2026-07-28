<script setup>
import { ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import AppDialog from '@/components/AppDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  channels: { type: Array, default: () => [] },
  editing: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()
const saving = ref(false)
const keywords = ref([])

const channelOptions = [{ id: null, display_name: 'All WhatsApp accounts' }]

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().nullable(),
    enabled: yup.boolean(),
    target: yup.string().oneOf(['all', 'individual', 'group']).required(),
    match_type: yup.string().oneOf(['contains', 'exact']).required(),
    name: yup.string().required('Rule name is required'),
    message_type: yup.string().oneOf(['text', 'media']).required(),
    body: yup.string().nullable().when('message_type', { is: 'text', then: (s) => s.required('Message is required') }),
    media_url: yup.string().nullable().url('Enter a valid URL').when('message_type', { is: 'media', then: (s) => s.required('Media URL is required') }),
  }),
)

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { channel_id: null, enabled: true, target: 'all', match_type: 'contains', name: '', message_type: 'text', body: '', media_url: '' },
})

const [channelId, channelIdAttrs] = defineField('channel_id')
const [enabled] = defineField('enabled')
const [target, targetAttrs] = defineField('target')
const [matchType, matchTypeAttrs] = defineField('match_type')
const [name, nameAttrs] = defineField('name')
const [type, typeAttrs] = defineField('message_type')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return

    keywords.value = props.editing?.keywords ?? []
    resetForm({
      values: props.editing
        ? {
            channel_id: props.editing.channel_id,
            enabled: props.editing.enabled,
            target: props.editing.target,
            match_type: props.editing.match_type,
            name: props.editing.name,
            message_type: props.editing.message_type,
            body: props.editing.body ?? '',
            media_url: props.editing.media_url ?? '',
          }
        : { channel_id: null, enabled: true, target: 'all', match_type: 'contains', name: '', message_type: 'text', body: '', media_url: '' },
    })
  },
)

const submit = handleSubmit(async (values) => {
  if (!keywords.value.length) {
    alertStore.warning('Add at least one keyword.')
    return
  }

  saving.value = true
  try {
    const payload = { ...values, keywords: keywords.value }
    if (props.editing) {
      await whatsapp.updateChatbotRule(props.editing.id, payload)
      alertStore.success('Chatbot rule updated.')
    } else {
      await whatsapp.createChatbotRule(payload)
      alertStore.success('Chatbot rule created.')
    }
    emit('update:modelValue', false)
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save chatbot rule.')
    }
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <AppDialog :model-value="modelValue" title="Chatbot Rule" max-width="560" @update:model-value="$emit('update:modelValue', $event)">
    <v-form @submit.prevent="submit">
      <v-text-field v-model="name" v-bind="nameAttrs" label="Rule name" :error-messages="errors.name" class="mb-2" />

      <v-select
        v-model="channelId" v-bind="channelIdAttrs" label="Applies to" :items="[...channelOptions, ...channels]"
        item-title="display_name" item-value="id" class="mb-2"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item v-bind="itemProps" :title="item.raw.display_name" />
        </template>
        <template #selection="{ item }">{{ item.raw.display_name }}</template>
      </v-select>

      <v-switch v-model="enabled" label="Enabled" color="primary" density="comfortable" class="mb-2" />

      <div class="d-flex ga-3 mb-2">
        <v-select v-model="target" v-bind="targetAttrs" label="Send to" :items="['all', 'individual', 'group']" />
        <v-select v-model="matchType" v-bind="matchTypeAttrs" label="Match type" :items="['contains', 'exact']" />
      </div>

      <v-combobox
        v-model="keywords" label="Keywords" multiple chips closable-chips
        hint="Press enter after each keyword" persistent-hint class="mb-4"
      />

      <v-btn-toggle v-model="type" v-bind="typeAttrs" mandatory density="comfortable" class="mb-4">
        <v-btn value="text">Text</v-btn>
        <v-btn value="media">Media</v-btn>
      </v-btn-toggle>

      <v-textarea
        v-if="type === 'text'" v-model="body" v-bind="bodyAttrs" label="Reply message"
        hint="Supports spintax: {Hi|Hello|Hola} there" persistent-hint rows="3" auto-grow :error-messages="errors.body"
      />
      <template v-else>
        <v-text-field v-model="mediaUrl" v-bind="mediaUrlAttrs" label="Media URL" :error-messages="errors.media_url" class="mb-2" />
        <v-textarea v-model="body" label="Caption (optional)" rows="2" auto-grow />
      </template>
    </v-form>

    <template #actions>
      <AppButton variant="outlined" :disabled="saving" @click="$emit('update:modelValue', false)">Close</AppButton>
      <AppButton variant="flat" :loading="saving" @click="submit">Save</AppButton>
    </template>
  </AppDialog>
</template>
