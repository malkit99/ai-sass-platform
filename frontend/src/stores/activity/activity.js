import { defineStore } from 'pinia'
import api from '@core/utils/api'
import { useAlertStore } from '@/stores/alert/alert'

export const useActivityStore = defineStore('activity', {
  state: () => ({
    logs: [],
    loading: false,
  }),
  actions: {
    async fetchLogs() {
      this.loading = true
      try {
        const { data } = await api.get('/api/activity-logs')
        this.logs = data
      } catch (e) {
        useAlertStore().error(e.response?.data?.message ?? 'Could not load the activity log.')
      } finally {
        this.loading = false
      }
    },
  },
})
