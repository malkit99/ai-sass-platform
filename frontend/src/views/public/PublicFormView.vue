<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { ensureCsrfCookie } from '@core/utils/api'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { displayOnlyTypes } from '@core/utils/whatsappFormFieldTypes'

const route = useRoute()
const whatsapp = useWhatsappStore()

const loading = ref(true)
const notFound = ref(false)
const form = ref(null)
const values = ref({})
const files = ref({})
const errors = ref({})
const submitting = ref(false)
const submitted = ref(false)

onMounted(async () => {
  try {
    // Sanctum's statefulApi() middleware treats any same-origin request as a
    // stateful one requiring CSRF protection, even for routes outside the
    // auth:sanctum group — a visitor landing directly on this public page
    // (no login flow, which is the only other place this gets called) never
    // had a CSRF cookie set, so submit() would 419 without this.
    await ensureCsrfCookie()

    form.value = await whatsapp.fetchPublicForm(route.params.slug)
    for (const field of form.value.fields) {
      if (!displayOnlyTypes.includes(field.type)) {
        values.value[field.id] = field.type === 'checkboxes' ? [] : ''
      }
    }
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

const inputFields = computed(() => (form.value?.fields ?? []).filter((f) => !displayOnlyTypes.includes(f.type)))

function onFileChange(field, event) {
  files.value[field.id] = event.target.files?.[0] ?? null
}

function validate() {
  const newErrors = {}

  for (const field of inputFields.value) {
    const value = field.type === 'file' ? files.value[field.id] : values.value[field.id]
    const empty = field.type === 'checkboxes' ? !(value?.length) : !value

    if (field.required && empty) {
      newErrors[field.id] = `${field.label} is required`
      continue
    }
    if (empty) continue

    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      newErrors[field.id] = 'Enter a valid email address'
    }
    if (field.type === 'whatsapp' && !(parsePhoneNumberFromString(`+${value}`)?.isValid() ?? false)) {
      newErrors[field.id] = 'Enter a valid number with country code (no + sign)'
    }
  }

  errors.value = newErrors
  return Object.keys(newErrors).length === 0
}

async function submit() {
  if (!validate()) return

  submitting.value = true
  try {
    const payload = new FormData()
    for (const field of inputFields.value) {
      if (field.type === 'file') {
        if (files.value[field.id]) payload.append(field.id, files.value[field.id])
      } else if (field.type === 'checkboxes') {
        for (const v of values.value[field.id] ?? []) payload.append(`${field.id}[]`, v)
      } else if (values.value[field.id]) {
        payload.append(field.id, values.value[field.id])
      }
    }

    await whatsapp.submitPublicForm(route.params.slug, payload)

    if (form.value.success_action === 'redirect' && form.value.success_redirect_url) {
      window.location.href = form.value.success_redirect_url
      return
    }

    submitted.value = true
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors ?? {}
      errors.value = Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]]))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="public-form-page d-flex align-center justify-center pa-4">
    <v-progress-circular v-if="loading" indeterminate color="primary" size="40" />

    <v-card v-else-if="notFound" class="pa-8 text-center" max-width="480">
      <v-icon icon="mdi-file-remove-outline" size="48" class="mb-2" />
      <div class="text-h6">Form not available</div>
      <div class="text-body-2 text-medium-emphasis">This form doesn't exist or isn't published.</div>
    </v-card>

    <v-card v-else-if="submitted" class="pa-8 text-center" max-width="480">
      <v-icon icon="mdi-check-circle-outline" color="success" size="48" class="mb-2" />
      <div class="text-h6">Thank you!</div>
      <div class="text-body-2 text-medium-emphasis">{{ form.success_message || 'Your submission has been received.' }}</div>
    </v-card>

    <v-card v-else class="pa-8" max-width="560" width="100%">
      <v-form @submit.prevent="submit">
        <template v-for="field in form.fields">
          <h2 v-if="field.type === 'heading'" :key="field.id" class="text-h5 mb-2">{{ field.label }}</h2>
          <p v-else-if="field.type === 'paragraph'" :key="field.id" class="text-body-2 text-medium-emphasis mb-4">{{ field.label }}</p>

          <v-text-field
            v-else-if="['text', 'email', 'whatsapp', 'number'].includes(field.type)" :key="field.id"
            v-model="values[field.id]" :label="field.label" :placeholder="field.placeholder || undefined"
            :type="field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'"
            variant="outlined" density="comfortable" class="mb-3" :error-messages="errors[field.id]"
          />

          <v-textarea
            v-else-if="field.type === 'textarea'" :key="field.id" v-model="values[field.id]" :label="field.label"
            :placeholder="field.placeholder || undefined" variant="outlined" rows="3" auto-grow class="mb-3"
            :error-messages="errors[field.id]"
          />

          <v-select
            v-else-if="field.type === 'dropdown'" :key="field.id" v-model="values[field.id]" :items="field.options ?? []"
            :label="field.label" variant="outlined" density="comfortable" class="mb-3" :error-messages="errors[field.id]"
          />

          <div v-else-if="field.type === 'radio'" :key="field.id" class="mb-3">
            <div class="text-body-2 mb-1">{{ field.label }}</div>
            <v-radio-group v-model="values[field.id]" hide-details :error-messages="errors[field.id]">
              <v-radio v-for="opt in field.options ?? []" :key="opt" :label="opt" :value="opt" />
            </v-radio-group>
          </div>

          <div v-else-if="field.type === 'checkboxes'" :key="field.id" class="mb-3">
            <div class="text-body-2 mb-1">{{ field.label }}</div>
            <v-checkbox
              v-for="opt in field.options ?? []" :key="opt" v-model="values[field.id]" :label="opt" :value="opt"
              density="compact" hide-details
            />
          </div>

          <v-text-field
            v-else-if="field.type === 'date'" :key="field.id" v-model="values[field.id]" :label="field.label" type="date"
            variant="outlined" density="comfortable" class="mb-3" :error-messages="errors[field.id]"
          />

          <v-text-field
            v-else-if="field.type === 'time'" :key="field.id" v-model="values[field.id]" :label="field.label" type="time"
            variant="outlined" density="comfortable" class="mb-3" :error-messages="errors[field.id]"
          />

          <div v-else-if="field.type === 'file'" :key="field.id" class="mb-3">
            <div class="text-body-2 mb-1">{{ field.label }}</div>
            <input type="file" @change="onFileChange(field, $event)">
            <div v-if="errors[field.id]" class="text-caption text-error">{{ errors[field.id] }}</div>
          </div>
        </template>

        <v-btn type="submit" color="primary" block size="large" :loading="submitting" class="mt-2">Submit</v-btn>
      </v-form>
    </v-card>
  </div>
</template>

<style scoped>
.public-form-page {
  min-height: 100vh;
  background: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
