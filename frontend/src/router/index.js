import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth/auth'
import DefaultLayout from '@layouts/DefaultLayout.vue'
import authRoutes from './routes/auth'
import dashboardRoutes from './routes/dashboard'
import crmRoutes from './routes/crm'
import whatsappRoutes from './routes/whatsapp'
import fileManagerRoutes from './routes/file-manager'
import publicRoutes from './routes/public'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    ...authRoutes,
    ...publicRoutes,
    {
      path: '/',
      component: DefaultLayout,
      meta: { requiresAuth: true },
      children: [...dashboardRoutes, ...crmRoutes, ...whatsappRoutes, ...fileManagerRoutes],
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
