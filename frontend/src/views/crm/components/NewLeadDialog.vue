<script setup>
import { ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useI18n } from 'vue-i18n'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { useCrmStore } from '@/stores/crm/crm'
import { useAlertStore } from '@/stores/alert/alert'
import { fireSuccess } from '@core/plugins/sweetalert'
import AppDialog from '@/components/AppDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  stageId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const crm = useCrmStore()
const alertStore = useAlertStore()
const saving = ref(false)

const leadSchema = toTypedSchema(
  yup.object({
    name: yup.string().required(t('crm.nameRequired')),
    // Digits only, no leading "+" — country code + number (e.g. 919876543210),
    // since WhatsApp (Cloud API and unofficial clients alike) identifies
    // contacts by that exact format, not a free-form phone string. Validated
    // with libphonenumber (the same Google library WhatsApp itself uses) so
    // a wrong-length or invalid-for-that-country number is actually caught,
    // not just "looks like some digits" — a wrong number here is a lost lead.
    phone: yup.string().nullable().test(
      'valid-phone',
      'Enter a valid mobile number with country code (no + sign)',
      (value) => {
        if (!value) return true
        return parsePhoneNumberFromString(`+${value}`)?.isValid() ?? false
      },
    ),
    email: yup.string().nullable().email(t('crm.emailInvalid')),
    description: yup.string().nullable(),
  }),
)

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: leadSchema,
  initialValues: { name: '', phone: '', email: '', description: '' },
})

const [name, nameAttrs] = defineField('name')
const [phone, phoneAttrs] = defineField('phone')
const [email, emailAttrs] = defineField('email')
const [description, descriptionAttrs] = defineField('description')

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) resetForm()
  },
)

const submit = handleSubmit(async (values) => {
  saving.value = true
  try {
    await crm.createLead({ ...values, stage_id: props.stageId })
    emit('update:modelValue', false)
    fireSuccess('Lead created', `${values.name} has been added to the pipeline.`)
  } catch (e) {
    if (e.response?.status === 422) {
      // Laravel's validation error shape: { message, errors: { field: [msg, ...] } }.
      // Map straight onto the matching form fields instead of a generic toast,
      // so the user sees exactly which input needs fixing.
      const serverErrors = e.response.data?.errors ?? {}
      setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
      alertStore.warning('Please fix the highlighted fields.')
    } else {
      alertStore.error(e.response?.data?.message ?? 'Something went wrong creating the lead. Please try again.')
    }
  } finally {
    saving.value = false
  }
})
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('crm.newLead')" @update:model-value="$emit('update:modelValue', $event)">
    <v-form @submit.prevent="submit">
      <v-text-field
        v-model="name"
        v-bind="nameAttrs"
        :label="t('crm.name')"
        :placeholder="t('crm.namePlaceholder')"
        :error-messages="errors.name"
        class="mb-2"
      />
      <v-text-field
        :model-value="phone"
        v-bind="phoneAttrs"
        type="tel"
        :label="t('crm.phone')"
        placeholder="e.g. 919876543210"
        hint="Country code + number, digits only — no + sign (e.g. 91 for India)"
        persistent-hint
        :error-messages="errors.phone"
        class="mb-2"
        @update:model-value="(val) => (phone = val.replace(/\D/g, ''))"
      />
      <v-text-field
        v-model="email"
        v-bind="emailAttrs"
        :label="t('crm.email')"
        type="email"
        :placeholder="t('crm.emailPlaceholder')"
        :error-messages="errors.email"
        class="mb-2"
      />
      <v-textarea
        v-model="description"
        v-bind="descriptionAttrs"
        :label="t('crm.description')"
        :placeholder="t('crm.descriptionPlaceholder')"
        :error-messages="errors.description"
        rows="3"
        auto-grow
      />
    </v-form>

    <template #actions>
      <AppButton variant="outlined" :disabled="saving" @click="$emit('update:modelValue', false)">{{ t('crm.close') }}</AppButton>
      <AppButton variant="flat" :loading="saving" @click="submit">{{ t('crm.create') }}</AppButton>
    </template>
  </AppDialog>
</template>
