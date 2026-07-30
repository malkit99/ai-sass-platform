// Matches the reference app's Smart Form Builder palette (screenshot 92).
// "choices" types carry an `options` array; all but heading/paragraph carry
// a submitted value and can be marked required — see WhatsappForm::FIELD_TYPES
// (backend) for the authoritative type list this must stay in sync with.
// `color` follows the same per-item "multicolor" convention already used by
// the main app sidebar (see @core/utils/modules.js / @layouts/components/NavDrawer.vue).
export const formFieldGroups = [
  {
    label: 'Structure',
    types: [
      { value: 'heading', label: 'Heading', icon: 'mdi-format-header-1', color: '#5E35B1' },
      { value: 'paragraph', label: 'Paragraph', icon: 'mdi-text', color: '#5C6BC0' },
    ],
  },
  {
    label: 'Basic Fields',
    types: [
      { value: 'text', label: 'Text Field', icon: 'mdi-form-textbox', color: '#1E88E5' },
      { value: 'email', label: 'Email', icon: 'mdi-email-outline', color: '#00897B' },
      { value: 'whatsapp', label: 'WhatsApp Number', icon: 'mdi-whatsapp', color: '#25D366' },
      { value: 'number', label: 'Number', icon: 'mdi-numeric', color: '#43A047' },
      { value: 'textarea', label: 'Text Area', icon: 'mdi-text-long', color: '#26A69A' },
    ],
  },
  {
    label: 'Choices',
    types: [
      { value: 'dropdown', label: 'Dropdown', icon: 'mdi-form-dropdown', color: '#FB8C00' },
      { value: 'radio', label: 'Radio Buttons', icon: 'mdi-radiobox-marked', color: '#F4511E' },
      { value: 'checkboxes', label: 'Checkboxes', icon: 'mdi-checkbox-marked-outline', color: '#FDD835' },
    ],
  },
  {
    label: 'Advanced',
    types: [
      { value: 'date', label: 'Date Picker', icon: 'mdi-calendar-outline', color: '#E53935' },
      { value: 'time', label: 'Time Picker', icon: 'mdi-clock-outline', color: '#D81B60' },
      { value: 'file', label: 'File Upload', icon: 'mdi-paperclip', color: '#6D4C41' },
    ],
  },
]

export const displayOnlyTypes = ['heading', 'paragraph']
export const choiceTypes = ['dropdown', 'radio', 'checkboxes']

export function fieldTypeMeta(type) {
  for (const group of formFieldGroups) {
    const match = group.types.find((t) => t.value === type)
    if (match) return match
  }

  return { value: type, label: type, icon: 'mdi-help-box-outline' }
}
