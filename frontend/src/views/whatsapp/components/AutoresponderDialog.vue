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

const channelOptions = [{ id: null, display_name: 'All WhatsApp accounts' }]

const schema = toTypedSchema(
  yup.object({
    channel_id: yup.number().nullable(),
    enabled: yup.boolean(),
    message_type: yup.string().oneOf(['text', 'media']).required(),
    body: yup.string().nullable().when('message_type', { is: 'text', then: (s) => s.required('Message is required') }),
    media_url: yup.string().nullable().url('Enter a valid URL').when('message_type', { is: 'media', then: (s) => s.required('Media URL is required') }),
  }),
)

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { channel_id: null, enabled: true, message_type: 'text', body: '', media_url: '' },
})

const [channelId, channelIdAttrs] = defineField('channel_id')
const [enabled] = defineField('enabled')
const [type, typeAttrs] = defineField('message_type')
const [body, bodyAttrs] = defineField('body')
const [mediaUrl, mediaUrlAttrs] = defineField('media_url')

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return

    resetForm({
      values: props.editing
        ? {
            channel_id: props.editing.channel_id,
            enabled: props.editing.enabled,
            message_type: props.editing.message_type,
            body: props.editing.body ?? '',
            media_url: props.editing.media_url ?? '',
          }
        : { channel_id: null, enabled: true, message_type: 'text', body: '', media_url: '' },
    })
  },
)

const submit = handleSubmit(async (values) => {
  saving.value = true
  try {
    if (props.editing) {
      await whatsapp.updateAutoresponder(props.editing.id, values)
      alertStore.success('Autoresponder updated.')
    } else {
      await whatsapp.createAutoresponder(values)
      alertStore.success('Autoresponder created.')
    }
    emit('update:modelValue', false)
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
    } else {
      alertStore.error(e.response?.data?.message ?? 'Failed to save autoresponder.')
    }
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <AppDialog :model-value="modelValue" title="Autoresponder" @update:model-value="$emit('update:modelValue', $event)">
    <v-form @submit.prevent="submit">
      <v-select
        v-model="channelId" v-bind="channelIdAttrs" label="Applies to" :items="[...channelOptions, ...channels]"
        item-title="display_name" item-value="id" :error-messages="errors.channel_id" class="mb-2"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item v-bind="itemProps" :title="item.raw.display_name" />
        </template>
        <template #selection="{ item }">{{ item.raw.display_name }}</template>
      </v-select>

      <v-switch v-model="enabled" label="Enabled" color="primary" density="comfortable" class="mb-2" />

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
