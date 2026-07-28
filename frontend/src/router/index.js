import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth/auth'
import DefaultLayout from '@layouts/DefaultLayout.vue'
import authRoutes from './routes/auth'
import dashboardRoutes from './routes/dashboard'
import crmRoutes from './routes/crm'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    ...authRoutes,
    {
      path: '/',
      component: DefaultLayout,
      meta: { requiresAuth: true },
      children: [...dashboardRoutes, ...crmRoutes],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.ready) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
