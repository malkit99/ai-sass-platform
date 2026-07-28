import { defineStore } from 'pinia'
import api from '@core/utils/api'

export const useCrmStore = defineStore('crm', {
  state: () => ({
    pipeline: null,
    leads: [],
    loading: false,
  }),
  actions: {
    async fetchPipeline() {
      const { data } = await api.get('/api/pipelines')
      this.pipeline = data[0] ?? null
    },
    async fetchLeads() {
      this.loading = true
      try {
        const { data } = await api.get('/api/leads')
        this.leads = data
      } finally {
        this.loading = false
      }
    },
    async createLead(payload) {
      const { data } = await api.post('/api/leads', payload)
      this.leads.unshift(data)
    },
    async moveLead(leadId, stageId) {
      const { data } = await api.patch(`/api/leads/${leadId}`, { stage_id: stageId })
      const index = this.leads.findIndex((l) => l.id === leadId)
      if (index !== -1) this.leads[index] = data
    },
    async toggleHot(lead) {
      const { data } = await api.patch(`/api/leads/${lead.id}`, { is_hot: !lead.is_hot })
      const index = this.leads.findIndex((l) => l.id === lead.id)
      if (index !== -1) this.leads[index] = data
    },
    async deleteLead(leadId) {
      await api.delete(`/api/leads/${leadId}`)
      this.leads = this.leads.filter((l) => l.id !== leadId)
    },
  },
  getters: {
    leadsByStage: (state) => (stageId) => state.leads.filter((l) => l.stage_id === stageId),
  },
})
