import { defineStore } from 'pinia'
import api, { ensureCsrfCookie } from '@core/utils/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    ready: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
  },
  actions: {
    async login(email, password) {
      await ensureCsrfCookie()
      const { data } = await api.post('/api/login', { email, password })
      this.user = data.user
    },
    async logout() {
      await api.post('/api/logout')
      this.user = null
    },
    async fetchUser() {
      try {
        const { data } = await api.get('/api/user')
        this.user = data
      } catch {
        this.user = null
      } finally {
        this.ready = true
      }
    },
  },
})
