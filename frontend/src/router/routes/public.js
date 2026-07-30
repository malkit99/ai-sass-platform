export default [
  {
    path: '/f/:slug',
    name: 'public-form',
    component: () => import('@/views/public/PublicFormView.vue'),
  },
]
