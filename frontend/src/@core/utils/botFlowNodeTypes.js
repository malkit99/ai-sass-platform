// Matches the reference app's Bot Builder palette (screenshots 60-62) —
// every category/item from the reference is listed here so the palette
// looks identical, but only `working: true` items are actually draggable
// onto the canvas; the rest render locked ("coming soon"). See
// BotFlowExecutor (backend) for the authoritative list of node types that
// actually execute — must stay in sync with the `working` flags here.
// `color` follows the same per-item "multicolor" convention already used by
// the main app sidebar and Form Builder's own field palette.
export const botNodeGroups = [
  {
    label: 'Inputs',
    types: [
      { value: 'input:text', nodeType: 'input', fieldType: 'text', label: 'Text', icon: 'mdi-form-textbox', color: '#F4511E', working: true },
      { value: 'input:number', nodeType: 'input', fieldType: 'number', label: 'Number', icon: 'mdi-pound', color: '#F4511E', working: true },
      { value: 'input:email', nodeType: 'input', fieldType: 'email', label: 'Email', icon: 'mdi-email-outline', color: '#F4511E', working: true },
      { value: 'input:website', nodeType: 'input', fieldType: 'website', label: 'Website', icon: 'mdi-link-variant', color: '#F4511E', working: true },
      { value: 'input:date', nodeType: 'input', fieldType: 'date', label: 'Date', icon: 'mdi-calendar-outline', color: '#F4511E', working: true },
      { value: 'input:time', nodeType: 'input', fieldType: 'time', label: 'Time', icon: 'mdi-clock-outline', color: '#F4511E', working: true },
      { value: 'input:phone', nodeType: 'input', fieldType: 'phone', label: 'Phone', icon: 'mdi-phone-outline', color: '#F4511E', working: true },
      { value: 'buttons', nodeType: 'buttons', label: 'Buttons', icon: 'mdi-gesture-tap-button', color: '#F4511E', working: true },
      { value: 'pic_choice', label: 'Pic choice', icon: 'mdi-image-multiple-outline', color: '#F4511E', working: false },
      { value: 'payment', label: 'Payment', icon: 'mdi-credit-card-outline', color: '#F4511E', working: false },
      { value: 'rating', label: 'Rating', icon: 'mdi-star-outline', color: '#F4511E', working: false },
      { value: 'file', label: 'File', icon: 'mdi-paperclip', color: '#F4511E', working: false, premium: true },
      { value: 'cards', label: 'Cards', icon: 'mdi-cards-outline', color: '#F4511E', working: false },
    ],
  },
  {
    // Per-item colors matching screenshot 86 exactly — this category is
    // NOT uniformly teal in the reference, each item has its own color.
    label: 'Logic',
    types: [
      { value: 'set_variable', nodeType: 'set_variable', label: 'Set variable', icon: 'mdi-auto-fix', color: '#26A69A', working: true },
      { value: 'condition', nodeType: 'condition', label: 'Condition', icon: 'mdi-filter-outline', color: '#757575', working: true },
      { value: 'redirect', label: 'Redirect', icon: 'mdi-open-in-new', color: '#5C6BC0', working: false },
      { value: 'script', label: 'Script', icon: 'mdi-code-tags', color: '#5C6BC0', working: false },
      { value: 'typebot', label: 'Typebot', icon: 'mdi-robot-outline', color: '#42A5F5', working: false },
      { value: 'wait', nodeType: 'wait', label: 'Wait', icon: 'mdi-timer-sand', color: '#FFA726', working: true },
      { value: 'ab_test', label: 'AB Test', icon: 'mdi-swap-horizontal', color: '#EC407A', working: false },
      { value: 'webhook', nodeType: 'webhook', label: 'Webhook', icon: 'mdi-webhook', color: '#26A69A', working: true },
      { value: 'jump', nodeType: 'jump', label: 'Jump', icon: 'mdi-debug-step-into', color: '#7E57C2', working: true },
      { value: 'return', label: 'Return', icon: 'mdi-keyboard-return', color: '#757575', working: false },
    ],
  },
  {
    // Per-item colors matching screenshot 87 exactly.
    label: 'Events',
    types: [
      { value: 'start', nodeType: 'start', label: 'Start', icon: 'mdi-flag', color: '#43A047', working: true },
      { value: 'command', label: 'Command', icon: 'mdi-console-line', color: '#5C6BC0', working: false },
      { value: 'reply', label: 'Reply', icon: 'mdi-reply', color: '#42A5F5', working: false },
      { value: 'invalid', label: 'Invalid', icon: 'mdi-close', color: '#E53935', working: false },
      { value: 'end', nodeType: 'end', label: 'End', icon: 'mdi-stop', color: '#E53935', working: true },
    ],
  },
  {
    label: 'AI & Integrations',
    types: [
      { value: 'ai_reply', nodeType: 'ai_reply', label: 'AI Reply', icon: 'mdi-creation', color: '#8E24AA', working: true },
      { value: 'list_menu', nodeType: 'list', label: 'List Menu', icon: 'mdi-format-list-bulleted', color: '#8E24AA', working: true },
    ],
  },
  {
    // 2-column grid (screenshot 84), each with its own brand-ish color —
    // not a uniform grey like the rest of this category previously was.
    label: 'Integrations',
    types: [
      { value: 'sheets', label: 'Sheets', icon: 'mdi-google-spreadsheet', color: '#0F9D58', working: false },
      { value: 'analytics', label: 'Analytics', icon: 'mdi-chart-box-outline', color: '#FB8C00', working: false },
      // Fundamentally "POST JSON to a URL" — the same generic Webhook node
      // handles all four for real, no separate integration needed.
      { value: 'http_request', nodeType: 'webhook', label: 'HTTP req...', icon: 'mdi-lightning-bolt-outline', color: '#FFA000', working: true },
      { value: 'email', label: 'Email', icon: 'mdi-email-send-outline', color: '#1E88E5', working: false },
      { value: 'zapier', nodeType: 'webhook', label: 'Zapier', icon: 'mdi-lightning-bolt', color: '#FF4A00', working: true },
      { value: 'make', nodeType: 'webhook', label: 'Make.com', icon: 'mdi-cog-sync-outline', color: '#7E57C2', working: true },
      { value: 'pabbly', nodeType: 'webhook', label: 'Pabbly', icon: 'mdi-connection', color: '#FF7043', working: true },
      { value: 'chatwoot', label: 'Chatwoot', icon: 'mdi-message-processing-outline', color: '#1F93FF', working: false },
      { value: 'pixel', label: 'Pixel', icon: 'mdi-facebook', color: '#3F51B5', working: false },
      // Real AI Reply node (BotFlowExecutor::callAiProvider) — dropping any
      // of these 8 gives the same node, pre-hinting its provider so the
      // property panel's credential picker defaults sensibly.
      { value: 'openai', nodeType: 'ai_reply', provider: 'openai', label: 'OpenAI', icon: 'mdi-brain', color: '#10A37F', working: true },
      { value: 'calcom', label: 'Cal.com', icon: 'mdi-calendar-clock-outline', color: '#212121', working: false },
      { value: 'chatnode', label: 'ChatNode', icon: 'mdi-chat-outline', color: '#2196F3', working: false },
      { value: 'qr_code', label: 'QR code', icon: 'mdi-qrcode', color: '#616161', working: false },
      { value: 'dify', label: 'Dify.AI', icon: 'mdi-alpha-d-circle-outline', color: '#6366F1', working: false },
      { value: 'mistral', nodeType: 'ai_reply', provider: 'mistral', label: 'Mistral', icon: 'mdi-weather-windy', color: '#FA520F', working: true },
      { value: 'elevenlabs', label: 'ElevenLabs', icon: 'mdi-waveform', color: '#000000', working: false },
      { value: 'anthropic', nodeType: 'ai_reply', provider: 'anthropic', label: 'Anthropic', icon: 'mdi-alpha-a-circle-outline', color: '#D97757', working: true },
      { value: 'together', nodeType: 'ai_reply', provider: 'together', label: 'Together', icon: 'mdi-account-group-outline', color: '#546E7A', working: true },
      { value: 'openrouter', nodeType: 'ai_reply', provider: 'openrouter', label: 'OpenRouter', icon: 'mdi-router-network', color: '#6366F1', working: true },
      { value: 'nocodb', label: 'NocoDB', icon: 'mdi-database-outline', color: '#7C3AED', working: false },
      { value: 'segment', label: 'Segment', icon: 'mdi-chart-donut', color: '#52BD94', working: false },
      { value: 'groq', nodeType: 'ai_reply', provider: 'groq', label: 'Groq', icon: 'mdi-speedometer', color: '#F55036', working: true },
      { value: 'zendesk', label: 'Zendesk', icon: 'mdi-lifebuoy', color: '#03363D', working: false },
      { value: 'posthog', label: 'Posthog', icon: 'mdi-chart-line', color: '#F9A825', working: false },
      { value: 'perplexity', nodeType: 'ai_reply', provider: 'perplexity', label: 'Perplexity', icon: 'mdi-magnify', color: '#20808D', working: true },
      { value: 'deepseek', nodeType: 'ai_reply', provider: 'deepseek', label: 'DeepSeek', icon: 'mdi-magnify-scan', color: '#4D6BFE', working: true },
      { value: 'blink', label: 'Blink', icon: 'mdi-flash-outline', color: '#00C853', working: false },
      { value: 'gmail', label: 'Gmail', icon: 'mdi-gmail', color: '#EA4335', working: false, beta: true },
      { value: 'woocommerce', label: 'WooCommerce', icon: 'mdi-storefront-outline', color: '#7F54B3', working: false },
    ],
  },
]

// Visual metadata for the canvas node card itself (header color/icon/label),
// keyed by the real executed node `type` — distinct from the palette items
// above, several of which map to the same node `type` (e.g. every Input
// variant is `type: 'input'`, differentiated by `data.field_type`).
export const botNodeVisuals = {
  start: { label: 'START', icon: 'mdi-flag', color: '#43A047' },
  text: { label: 'TEXT', icon: 'mdi-message-text-outline', color: '#1E88E5' },
  input: { label: 'INPUT', icon: 'mdi-form-textbox', color: '#F4511E' },
  buttons: { label: 'BUTTONS', icon: 'mdi-gesture-tap-button', color: '#F4511E' },
  list: { label: 'LIST MENU', icon: 'mdi-format-list-bulleted', color: '#8E24AA' },
  condition: { label: 'CONDITION', icon: 'mdi-filter-outline', color: '#757575' },
  set_variable: { label: 'SET VARIABLE', icon: 'mdi-auto-fix', color: '#26A69A' },
  webhook: { label: 'WEBHOOK', icon: 'mdi-webhook', color: '#26A69A' },
  ai_reply: { label: 'AI REPLY', icon: 'mdi-creation', color: '#8E24AA' },
  wait: { label: 'WAIT', icon: 'mdi-timer-sand', color: '#FFA726' },
  jump: { label: 'JUMP', icon: 'mdi-debug-step-into', color: '#7E57C2' },
  end: { label: 'END', icon: 'mdi-stop', color: '#E53935' },
}

let counter = 0
export function newNodeId(nodeType) {
  counter += 1

  return `${nodeType}-${Date.now()}-${counter}`
}

/**
 * The `data` object a freshly-dropped node of this type starts with —
 * matches exactly what BotFlowExecutor (backend) reads per node type.
 */
export function defaultNodeData(paletteItem) {
  switch (paletteItem.nodeType) {
    case 'input':
      return { body: 'Your question here', variable_name: 'answer', field_type: paletteItem.fieldType }
    case 'text':
      return { body: 'Type your message…' }
    case 'buttons':
      return { body: 'Choose an option', buttons: ['Option 1', 'Option 2'], variable_name: 'choice' }
    case 'list':
      return { body: 'Choose an option', button_text: 'Choose', sections: [{ title: 'Options', rows: [{ title: 'Option 1' }] }], variable_name: 'choice' }
    case 'condition':
      return { variable_name: '', operator: 'equals', value: '' }
    case 'set_variable':
      return { variable_name: '', value: '' }
    case 'webhook':
      return { url: '', body_template: '', response_variable: '' }
    case 'ai_reply':
      return {
        credential_id: null, preferred_provider: paletteItem.provider ?? null,
        system_prompt: 'You are a helpful assistant.', user_prompt: '{{last_message}}', response_variable: 'ai_response',
      }
    case 'wait':
      return { seconds: 5 }
    case 'jump':
      return { target_node_id: '' }
    default:
      return {}
  }
}
