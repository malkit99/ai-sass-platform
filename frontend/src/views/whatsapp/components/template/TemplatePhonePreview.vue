<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth/auth'
import { templateTypeMeta, mediaTemplateTypes } from '@core/utils/whatsappTemplateTypes'

const props = defineProps({
  // Live-editing forms pass these individually as the user types.
  icon: { type: String, default: null },
  mediaType: { type: String, default: null }, // image|video|audio|document
  mediaUrl: { type: String, default: null },
  headerText: { type: String, default: '' },
  bodyText: { type: String, default: '' },
  footerText: { type: String, default: '' },
  // Saved-record previews (template list card / quick-preview dialog) pass a
  // full template object instead — everything below is derived from it,
  // including the type-specific extras (buttons/poll/list/carousel), so
  // those two call sites don't have to duplicate this per-type logic.
  template: { type: Object, default: null },
  // Renders just the bubble, no phone frame — used for the compact card
  // preview in the template list.
  compact: { type: Boolean, default: false },
})

const auth = useAuthStore()

const meta = computed(() => (props.template ? templateTypeMeta(props.template.type) : null))
const isMediaType = computed(() => meta.value && mediaTemplateTypes.includes(props.template.type))

const resolvedIcon = computed(() => (props.template ? meta.value.icon : props.icon))
const resolvedMediaType = computed(() => (props.template ? (isMediaType.value ? meta.value.mediaKind : null) : props.mediaType))
const resolvedMediaUrl = computed(() => (props.template ? (isMediaType.value ? props.template.media_url : null) : props.mediaUrl))
const resolvedHeaderText = computed(() => {
  if (!props.template) return props.headerText
  return props.template.config?.header_type === 'text' ? props.template.config?.header_text : ''
})
const resolvedBodyText = computed(() => (props.template ? props.template.body : props.bodyText))
const resolvedFooterText = computed(() => (props.template ? props.template.footer : props.footerText))

// Header media (interactive_buttons' own header_type=image/video/document) is
// a second, independent media slot from the type's own media_url.
const headerMediaType = computed(() => {
  const type = props.template?.config?.header_type
  return ['image', 'video', 'document'].includes(type) ? type : null
})
const headerMediaUrl = computed(() => (headerMediaType.value ? props.template.config.header_media_url : null))

const buttonIcon = { reply: 'mdi-reply-outline', call: 'mdi-phone-outline', url: 'mdi-open-in-new' }

const previewText = computed(() => resolvedBodyText.value || 'Preview...')
const previewTime = computed(() => new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }))
const senderName = computed(() => auth.user?.name || 'Business Account')
</script>

<template>
  <div :class="compact ? '' : 'phone-frame mx-auto'">
    <div v-if="!compact" class="phone-header d-flex align-center ga-2">
      <v-avatar size="32" color="white" variant="flat"><v-icon icon="mdi-whatsapp" color="success" /></v-avatar>
      <div>
        <div class="text-body-2 font-weight-medium text-white">{{ senderName }}</div>
        <div class="text-caption" style="color: rgba(255, 255, 255, 0.8)">Business Account</div>
      </div>
    </div>
    <div :class="compact ? '' : 'phone-body'">
      <div class="chat-bubble">
        <template v-if="headerMediaUrl">
          <img v-if="headerMediaType === 'image'" :src="headerMediaUrl" class="media-preview mb-2" alt="" />
          <video v-else-if="headerMediaType === 'video'" :src="headerMediaUrl" controls class="media-preview mb-2" />
          <div v-else class="d-flex align-center ga-2 mb-2 pa-2 media-doc-chip">
            <v-icon icon="mdi-file-document-outline" size="24" color="medium-emphasis" />
            <span class="text-caption text-truncate">{{ headerMediaUrl.split('/').pop() }}</span>
          </div>
        </template>
        <template v-else-if="resolvedMediaUrl">
          <img v-if="resolvedMediaType === 'image'" :src="resolvedMediaUrl" class="media-preview mb-2" alt="" />
          <video v-else-if="resolvedMediaType === 'video'" :src="resolvedMediaUrl" controls class="media-preview mb-2" />
          <audio v-else-if="resolvedMediaType === 'audio'" :src="resolvedMediaUrl" controls class="w-100 mb-2" />
          <div v-else class="d-flex align-center ga-2 mb-2 pa-2 media-doc-chip">
            <v-icon :icon="resolvedIcon ?? 'mdi-file-document-outline'" size="24" color="medium-emphasis" />
            <span class="text-caption text-truncate">{{ resolvedMediaUrl.split('/').pop() }}</span>
          </div>
        </template>
        <v-icon v-else-if="resolvedIcon" :icon="resolvedIcon" size="32" color="medium-emphasis" class="mb-2" />

        <div v-if="resolvedHeaderText" class="text-body-2 font-weight-bold">{{ resolvedHeaderText }}</div>

        <div class="text-body-2" :class="{ 'text-truncate': compact }" style="white-space: pre-wrap">{{ previewText }}</div>

        <div v-if="resolvedFooterText" class="text-caption text-medium-emphasis mt-1">{{ resolvedFooterText }}</div>

        <slot />

        <template v-if="template">
          <!-- text_buttons (plain string labels) or interactive_buttons (typed objects) -->
          <div v-if="template.config?.buttons?.length" class="d-flex flex-column ga-1 mt-2">
            <div
              v-for="(b, i) in template.config.buttons" :key="i"
              class="d-flex align-center justify-center ga-1 text-caption py-1 preview-list-button"
            >
              <v-icon v-if="typeof b === 'object'" :icon="buttonIcon[b.type]" size="14" />
              {{ typeof b === 'object' ? b.label : b }}
            </div>
          </div>

          <div v-if="template.config?.poll_options?.length" class="mt-2">
            <div v-for="(opt, i) in template.config.poll_options" :key="i" class="d-flex align-center ga-2 text-caption py-1">
              <v-icon icon="mdi-circle-outline" size="14" />{{ opt }}
            </div>
          </div>

          <div v-if="template.config?.sections?.length" class="d-flex align-center justify-center ga-2 text-caption py-2 mt-2 preview-list-button">
            <v-icon icon="mdi-format-list-bulleted" size="16" />{{ template.config.button_text || 'View Options' }}
          </div>

          <div v-if="template.config?.cards?.length" class="d-flex ga-2 mt-2" style="overflow-x: auto">
            <v-card v-for="(card, i) in template.config.cards" :key="i" width="110" variant="tonal" class="pa-2 flex-shrink-0">
              <v-img v-if="card.image_url" :src="card.image_url" height="55" cover class="rounded mb-1" />
              <div v-else class="d-flex align-center justify-center rounded mb-1" style="height: 55px; background: rgba(0, 0, 0, 0.06)">
                <v-icon icon="mdi-image-outline" size="18" color="medium-emphasis" />
              </div>
              <div class="text-caption font-weight-medium text-truncate">{{ card.title || 'Title' }}</div>
              <div class="text-caption text-truncate text-medium-emphasis">{{ card.body }}</div>
            </v-card>
          </div>
        </template>

        <div v-if="!compact" class="text-caption text-medium-emphasis text-right mt-1">{{ previewTime }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.phone-frame {
  max-width: 300px;
  border: 10px solid #1e1e1e;
  border-radius: 32px;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.phone-header {
  background: #075e54;
  padding: 12px;
}

.phone-body {
  min-height: 360px;
  background: repeating-linear-gradient(45deg, rgba(0, 0, 0, 0.02), rgba(0, 0, 0, 0.02) 10px, transparent 10px, transparent 20px), #e5ddd5;
  padding: 16px;
}

.chat-bubble {
  background: #fff;
  border-radius: 8px;
  padding: 10px 12px;
  max-width: 100%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.media-preview {
  display: block;
  width: 100%;
  height: auto;
  max-height: 260px;
  object-fit: contain;
  background: rgba(0, 0, 0, 0.03);
  border-radius: 6px;
}

.media-doc-chip {
  background: rgba(0, 0, 0, 0.04);
  border-radius: 6px;
}

.preview-list-button {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  color: #00a5f4;
}
</style>
