// Matches the reference app's Templates submenu (screenshot 36). Media types
// map to this platform's existing single/media send path; the interactive
// types (buttons/lists/poll/carousel) are modeled via `config` but not yet
// wired to an actual send path — see 11-unofficial-whatsapp.md.
export const templateTypes = [
  { value: 'text', label: 'Text Message', icon: 'mdi-message-text-outline', badge: 'TEXT' },
  { value: 'text_image', label: 'Text + Image', icon: 'mdi-image-outline', badge: 'IMAGE', mediaKind: 'image' },
  { value: 'text_video', label: 'Text + Video', icon: 'mdi-video-outline', badge: 'VIDEO', mediaKind: 'video' },
  { value: 'text_document', label: 'Text + Document', icon: 'mdi-file-document-outline', badge: 'DOCUMENT', mediaKind: 'document' },
  { value: 'text_audio', label: 'Text + Audio', icon: 'mdi-music-note', badge: 'AUDIO', mediaKind: 'audio' },
  { value: 'text_buttons', label: 'Text + Buttons', icon: 'mdi-keyboard-outline', badge: 'BUTTONS' },
  { value: 'text_lists', label: 'Text + Lists', icon: 'mdi-format-list-bulleted', badge: 'LIST' },
  { value: 'text_poll', label: 'Text + Poll', icon: 'mdi-poll', badge: 'POLL' },
  { value: 'interactive_buttons', label: 'Interactive Buttons', icon: 'mdi-gesture-tap-button', badge: 'INTERACTIVE' },
  { value: 'text_carousel', label: 'Text + Carousel', icon: 'mdi-view-carousel-outline', badge: 'CAROUSEL' },
]

export const mediaTemplateTypes = ['text_image', 'text_video', 'text_document', 'text_audio']
export const buttonTemplateTypes = ['text_buttons', 'interactive_buttons']

export function templateTypeMeta(value) {
  return templateTypes.find((t) => t.value === value) ?? templateTypes[0]
}
