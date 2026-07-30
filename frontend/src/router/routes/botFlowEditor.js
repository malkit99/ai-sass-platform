// Deliberately outside DefaultLayout (see router/index.js) — the flow
// editor (screenshots 59-62) is a dedicated full-screen experience with no
// main app nav rail and no WhatsApp feature sidebar, unlike every other
// WhatsApp panel which stays nested inside WhatsappView/DefaultLayout.
export default [
  {
    path: '/whatsapp/bot-builder/:id',
    name: 'bot-flow-editor',
    component: () => import('@/views/whatsapp/BotFlowEditorView.vue'),
    meta: { requiresAuth: true },
    props: true,
  },
]
