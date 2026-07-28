<script setup>
import { watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/AppDialog.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const leadSchema = toTypedSchema(
  yup.object({
    name: yup.string().required(t('crm.nameRequired')),
    phone: yup.string().nullable(),
    email: yup.string().nullable().email(t('crm.emailInvalid')),
    description: yup.string().nullable(),
  }),
)

const { defineField, handleSubmit, errors, resetForm } = useForm({
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

const submit = handleSubmit((values) => {
  emit('submit', values)
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
        v-model="phone"
        v-bind="phoneAttrs"
        :label="t('crm.phone')"
        :placeholder="t('crm.phonePlaceholder')"
        :error-messages="errors.phone"
        class="mb-2"
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
      <AppButton variant="outlined" @click="$emit('update:modelValue', false)">{{ t('crm.close') }}</AppButton>
      <AppButton variant="flat" :loading="saving" @click="submit">{{ t('crm.create') }}</AppButton>
    </template>
  </AppDialog>
</template>
