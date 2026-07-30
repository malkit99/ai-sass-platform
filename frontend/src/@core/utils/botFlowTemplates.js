// Bot Builder's Templates Marketplace (screenshots 57-58) — a real shell
// seeded with genuinely usable starter flows built from the v1 node set
// only. No fake Premium/paywall tier here (unlike the reference) — this
// app has no billing/plan-tier system to gate a paywall against, so every
// template is simply free and usable.
export const botFlowTemplates = [
  {
    key: 'welcome-faq',
    name: 'Welcome & FAQ',
    category: 'Support',
    icon: 'mdi-hand-wave-outline',
    color: '#1E88E5',
    description: 'Greets a new contact and routes them to the right answer with quick-reply buttons.',
    triggerKeywords: ['hi', 'hello', 'help'],
    flowDefinition: {
      nodes: [
        { id: 'start', type: 'start', position: { x: 40, y: 200 }, data: {} },
        { id: 'welcome', type: 'text', position: { x: 300, y: 200 }, data: { body: "Hi! 👋 Welcome, how can I help you today?" } },
        { id: 'menu', type: 'buttons', position: { x: 560, y: 200 }, data: { body: 'Choose a topic:', buttons: ['Pricing', 'Support'], variable_name: 'topic' } },
        { id: 'cond', type: 'condition', position: { x: 820, y: 200 }, data: { variable_name: 'topic', operator: 'contains', value: 'pricing' } },
        { id: 'pricing', type: 'text', position: { x: 1080, y: 100 }, data: { body: 'Our plans start at $19/mo — reply with any question and our team will follow up.' } },
        { id: 'support', type: 'text', position: { x: 1080, y: 300 }, data: { body: "We're on it! Describe your issue and our team will get back to you shortly." } },
        { id: 'end', type: 'end', position: { x: 1340, y: 200 }, data: {} },
      ],
      edges: [
        { id: 'e1', source: 'start', target: 'welcome' },
        { id: 'e2', source: 'welcome', target: 'menu' },
        { id: 'e3', source: 'menu', target: 'cond' },
        { id: 'e4', source: 'cond', target: 'pricing', sourceHandle: 'true' },
        { id: 'e5', source: 'cond', target: 'support', sourceHandle: 'false' },
        { id: 'e6', source: 'pricing', target: 'end' },
        { id: 'e7', source: 'support', target: 'end' },
      ],
    },
  },
  {
    key: 'lead-capture',
    name: 'Lead Capture',
    category: 'Marketing',
    icon: 'mdi-account-plus-outline',
    color: '#8E24AA',
    description: 'Collects a new contact’s name, email and phone number for follow-up.',
    triggerKeywords: ['start', 'quote'],
    flowDefinition: {
      nodes: [
        { id: 'start', type: 'start', position: { x: 40, y: 200 }, data: {} },
        { id: 'intro', type: 'text', position: { x: 300, y: 200 }, data: { body: "Thanks for reaching out! Let's get a few details." } },
        { id: 'name', type: 'input', position: { x: 560, y: 200 }, data: { body: "What's your name?", variable_name: 'name', field_type: 'text' } },
        { id: 'email', type: 'input', position: { x: 820, y: 200 }, data: { body: 'And your email address?', variable_name: 'email', field_type: 'email' } },
        { id: 'phone', type: 'input', position: { x: 1080, y: 200 }, data: { body: 'Best phone number to reach you?', variable_name: 'phone', field_type: 'phone' } },
        { id: 'thanks', type: 'text', position: { x: 1340, y: 200 }, data: { body: 'Thanks, {{name}}! Our team will reach out shortly.' } },
        { id: 'end', type: 'end', position: { x: 1600, y: 200 }, data: {} },
      ],
      edges: [
        { id: 'e1', source: 'start', target: 'intro' },
        { id: 'e2', source: 'intro', target: 'name' },
        { id: 'e3', source: 'name', target: 'email' },
        { id: 'e4', source: 'email', target: 'phone' },
        { id: 'e5', source: 'phone', target: 'thanks' },
        { id: 'e6', source: 'thanks', target: 'end' },
      ],
    },
  },
  {
    key: 'appointment-info',
    name: 'Appointment Info',
    category: 'Sales',
    icon: 'mdi-calendar-check-outline',
    color: '#43A047',
    description: 'Lets a contact tell you whether they want to book, reschedule, or cancel.',
    triggerKeywords: ['appointment', 'booking'],
    flowDefinition: {
      nodes: [
        { id: 'start', type: 'start', position: { x: 40, y: 200 }, data: {} },
        { id: 'intro', type: 'text', position: { x: 300, y: 200 }, data: { body: "Hi! What would you like to do?" } },
        { id: 'menu', type: 'buttons', position: { x: 560, y: 200 }, data: { body: 'Choose an option:', buttons: ['Book', 'Reschedule'], variable_name: 'action' } },
        { id: 'cond', type: 'condition', position: { x: 820, y: 200 }, data: { variable_name: 'action', operator: 'contains', value: 'book' } },
        { id: 'book', type: 'text', position: { x: 1080, y: 100 }, data: { body: "Great — what date and time works for you?" } },
        { id: 'reschedule', type: 'text', position: { x: 1080, y: 300 }, data: { body: 'No problem — share your booking reference and a preferred new time.' } },
        { id: 'end', type: 'end', position: { x: 1340, y: 200 }, data: {} },
      ],
      edges: [
        { id: 'e1', source: 'start', target: 'intro' },
        { id: 'e2', source: 'intro', target: 'menu' },
        { id: 'e3', source: 'menu', target: 'cond' },
        { id: 'e4', source: 'cond', target: 'book', sourceHandle: 'true' },
        { id: 'e5', source: 'cond', target: 'reschedule', sourceHandle: 'false' },
        { id: 'e6', source: 'book', target: 'end' },
        { id: 'e7', source: 'reschedule', target: 'end' },
      ],
    },
  },
  {
    key: 'order-status',
    name: 'Order Status Lookup',
    category: 'E-commerce',
    icon: 'mdi-package-variant-closed',
    color: '#E53935',
    description: 'Asks for an order ID and looks it up through your own order-status webhook.',
    triggerKeywords: ['order', 'track'],
    flowDefinition: {
      nodes: [
        { id: 'start', type: 'start', position: { x: 40, y: 200 }, data: {} },
        { id: 'intro', type: 'text', position: { x: 300, y: 200 }, data: { body: "I can check that for you." } },
        { id: 'orderId', type: 'input', position: { x: 560, y: 200 }, data: { body: "What's your order ID?", variable_name: 'order_id', field_type: 'text' } },
        { id: 'lookup', type: 'webhook', position: { x: 820, y: 200 }, data: { url: 'https://example.com/api/order-status', body_template: '{"order_id":"{{order_id}}"}', response_variable: 'status' } },
        { id: 'reply', type: 'text', position: { x: 1080, y: 200 }, data: { body: 'Order {{order_id}} status: {{status}}' } },
        { id: 'end', type: 'end', position: { x: 1340, y: 200 }, data: {} },
      ],
      edges: [
        { id: 'e1', source: 'start', target: 'intro' },
        { id: 'e2', source: 'intro', target: 'orderId' },
        { id: 'e3', source: 'orderId', target: 'lookup' },
        { id: 'e4', source: 'lookup', target: 'reply' },
        { id: 'e5', source: 'reply', target: 'end' },
      ],
    },
  },
]

export const botFlowTemplateCategories = ['All', ...new Set(botFlowTemplates.map((t) => t.category))]
