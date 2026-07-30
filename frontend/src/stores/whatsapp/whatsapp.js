import { defineStore } from 'pinia'
import api from '@core/utils/api'

export const useWhatsappStore = defineStore('whatsapp', {
  state: () => ({
    channels: [],
    channelLimit: null,
    loading: false,
    campaigns: [],
    autoresponders: [],
    chatbotRules: [],
    templates: [],
    shortLinks: [],
    groups: [],
    callResponderSettings: null,
    callHistory: [],
    forms: [],
    formsDashboard: null,
    formSubmissions: [],
    dashboard: null,
    contactGroups: [],
    contacts: [],
    contactsMeta: null,
    contactsLoading: false,
    messageHistory: [],
    messageHistoryMeta: null,
    messageHistoryLoading: false,
  }),
  actions: {
    async fetchDashboard() {
      const { data } = await api.get('/api/whatsapp/dashboard')
      this.dashboard = data
    },
    async fetchChannels() {
      this.loading = true
      try {
        const { data } = await api.get('/api/whatsapp/channels')
        this.channels = data.channels
        this.channelLimit = data.limit
      } finally {
        this.loading = false
      }
    },
    async createChannel(name) {
      const { data } = await api.post('/api/whatsapp/channels', { name })
      this.channels.unshift(data)
      return data
    },
    async renameChannel(channelId, name) {
      const { data } = await api.patch(`/api/whatsapp/channels/${channelId}`, { name })
      const index = this.channels.findIndex((c) => c.id === channelId)
      if (index !== -1) this.channels[index] = data
      return data
    },
    async connectChannel(channelId) {
      const { data } = await api.post(`/api/whatsapp/channels/${channelId}/connect`)
      const channel = this.channels.find((c) => c.id === channelId)
      if (channel) channel.status = data.status
    },
    async fetchQr(channelId) {
      const { data } = await api.get(`/api/whatsapp/channels/${channelId}/qr`)
      return data.qr
    },
    async requestPairingCode(channelId, phoneNumber) {
      const { data } = await api.post(`/api/whatsapp/channels/${channelId}/pairing-code`, {
        phone_number: phoneNumber,
      })
      return data.code
    },
    async fetchStatus(channelId) {
      const { data } = await api.get(`/api/whatsapp/channels/${channelId}/status`)
      return data.status
    },
    async disconnectChannel(channelId) {
      await api.post(`/api/whatsapp/channels/${channelId}/disconnect`)
      const channel = this.channels.find((c) => c.id === channelId)
      if (channel) channel.status = 'disconnected'
    },
    async deleteChannel(channelId) {
      await api.delete(`/api/whatsapp/channels/${channelId}`)
      this.channels = this.channels.filter((c) => c.id !== channelId)
    },
    async sendMessage(payload) {
      const { data } = await api.post('/api/whatsapp/messages', payload)
      return data
    },
    updateChannelStatus(channelId, status) {
      const channel = this.channels.find((c) => c.id === channelId)
      if (channel) channel.status = status
    },

    async fetchMessageHistory(channelId, { search = '', direction = null, type = null, status = null, page = 1 } = {}) {
      this.messageHistoryLoading = true
      try {
        const { data } = await api.get('/api/whatsapp/messages/history', {
          params: { channel_id: channelId, search: search || undefined, direction, type, status, page },
        })
        this.messageHistory = data.data
        this.messageHistoryMeta = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
      } finally {
        this.messageHistoryLoading = false
      }
    },

    async fetchCampaigns() {
      const { data } = await api.get('/api/whatsapp/campaigns')
      this.campaigns = data
    },
    async fetchCampaign(campaignId) {
      const { data } = await api.get(`/api/whatsapp/campaigns/${campaignId}`)
      return data
    },
    async createCampaign(payload) {
      const { data } = await api.post('/api/whatsapp/campaigns', payload)
      this.campaigns.unshift(data)
      return data
    },
    async updateCampaign(campaignId, payload) {
      const { data } = await api.patch(`/api/whatsapp/campaigns/${campaignId}`, payload)
      const index = this.campaigns.findIndex((c) => c.id === campaignId)
      if (index !== -1) this.campaigns[index] = { ...this.campaigns[index], ...data }
      return data
    },
    async pauseCampaign(campaignId) {
      const { data } = await api.post(`/api/whatsapp/campaigns/${campaignId}/pause`)
      const campaign = this.campaigns.find((c) => c.id === campaignId)
      if (campaign) campaign.status = data.status
    },
    async resumeCampaign(campaignId) {
      const { data } = await api.post(`/api/whatsapp/campaigns/${campaignId}/resume`)
      const campaign = this.campaigns.find((c) => c.id === campaignId)
      if (campaign) campaign.status = data.status
    },
    async deleteCampaign(campaignId) {
      await api.delete(`/api/whatsapp/campaigns/${campaignId}`)
      this.campaigns = this.campaigns.filter((c) => c.id !== campaignId)
    },

    async fetchAutoresponders() {
      const { data } = await api.get('/api/whatsapp/autoresponders')
      this.autoresponders = data
    },
    async createAutoresponder(payload) {
      const { data } = await api.post('/api/whatsapp/autoresponders', payload)
      this.autoresponders.unshift(data)
      return data
    },
    async updateAutoresponder(id, payload) {
      const { data } = await api.patch(`/api/whatsapp/autoresponders/${id}`, payload)
      const index = this.autoresponders.findIndex((a) => a.id === id)
      if (index !== -1) this.autoresponders[index] = data
      return data
    },
    async deleteAutoresponder(id) {
      await api.delete(`/api/whatsapp/autoresponders/${id}`)
      this.autoresponders = this.autoresponders.filter((a) => a.id !== id)
    },

    async fetchChatbotRules() {
      const { data } = await api.get('/api/whatsapp/chatbot-rules')
      this.chatbotRules = data
    },
    async createChatbotRule(payload) {
      const { data } = await api.post('/api/whatsapp/chatbot-rules', payload)
      this.chatbotRules.unshift(data)
      return data
    },
    async updateChatbotRule(id, payload) {
      const { data } = await api.patch(`/api/whatsapp/chatbot-rules/${id}`, payload)
      const index = this.chatbotRules.findIndex((r) => r.id === id)
      if (index !== -1) this.chatbotRules[index] = data
      return data
    },
    async deleteChatbotRule(id) {
      await api.delete(`/api/whatsapp/chatbot-rules/${id}`)
      this.chatbotRules = this.chatbotRules.filter((r) => r.id !== id)
    },

    async fetchShortLinks(channelId) {
      const { data } = await api.get('/api/whatsapp/short-links', { params: { channel_id: channelId } })
      this.shortLinks = data
    },
    async createShortLink(payload) {
      const { data } = await api.post('/api/whatsapp/short-links', payload)
      this.shortLinks.unshift(data)
      return data
    },
    async deleteShortLink(id) {
      await api.delete(`/api/whatsapp/short-links/${id}`)
      this.shortLinks = this.shortLinks.filter((l) => l.id !== id)
    },

    async fetchGroups(channelId) {
      const { data } = await api.get('/api/whatsapp/groups', { params: { channel_id: channelId } })
      this.groups = data
    },
    async exportGroupParticipants(group) {
      let response
      try {
        response = await api.get(`/api/whatsapp/groups/${group.id}/export`, { responseType: 'blob' })
      } catch (e) {
        // responseType: 'blob' means a failed request's body also arrives as
        // a Blob, not parsed JSON — e.response.data.message would otherwise
        // always be undefined and callers would only ever see a generic
        // fallback error, never the bridge's actual failure reason (e.g.
        // "Instance not connected").
        if (e.response?.data instanceof Blob) {
          try {
            e.response.data = JSON.parse(await e.response.data.text())
          } catch {
            // Non-JSON error body (e.g. an HTML error page) — leave as-is.
          }
        }
        throw e
      }

      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = `${group.name || group.group_jid}-participants.csv`
      link.click()
      URL.revokeObjectURL(url)
    },

    async fetchCallResponderSettings(channelId) {
      const { data } = await api.get('/api/whatsapp/call-responder/settings', { params: { channel_id: channelId } })
      this.callResponderSettings = data
      return data
    },
    async saveCallResponderSettings(payload) {
      const { data } = await api.put('/api/whatsapp/call-responder/settings', payload)
      this.callResponderSettings = data
      return data
    },
    async fetchCallHistory(channelId) {
      const { data } = await api.get('/api/whatsapp/call-responder/history', { params: { channel_id: channelId } })
      this.callHistory = data
    },

    async fetchForms() {
      const { data } = await api.get('/api/whatsapp/forms')
      this.forms = data
    },
    async fetchFormsDashboard() {
      const { data } = await api.get('/api/whatsapp/forms/dashboard')
      this.formsDashboard = data
    },
    async createForm(payload) {
      const { data } = await api.post('/api/whatsapp/forms', payload)
      this.forms.unshift(data)
      return data
    },
    async updateForm(id, payload) {
      const { data } = await api.patch(`/api/whatsapp/forms/${id}`, payload)
      const index = this.forms.findIndex((f) => f.id === id)
      if (index !== -1) this.forms[index] = data
      return data
    },
    async deleteForm(id) {
      await api.delete(`/api/whatsapp/forms/${id}`)
      this.forms = this.forms.filter((f) => f.id !== id)
    },
    async fetchFormSubmissions(formId) {
      const { data } = await api.get(`/api/whatsapp/forms/${formId}/submissions`)
      this.formSubmissions = data
    },
    async exportFormSubmissions(form) {
      let response
      try {
        response = await api.get(`/api/whatsapp/forms/${form.id}/submissions/export`, { responseType: 'blob' })
      } catch (e) {
        // See exportGroupParticipants — responseType: 'blob' means a failed
        // request's error body also arrives as a Blob, not parsed JSON.
        if (e.response?.data instanceof Blob) {
          try {
            e.response.data = JSON.parse(await e.response.data.text())
          } catch {
            // Non-JSON error body — leave as-is.
          }
        }
        throw e
      }

      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = `${form.name}-submissions.csv`
      link.click()
      URL.revokeObjectURL(url)
    },

    // Public — no auth, used by the standalone form-fill page.
    async fetchPublicForm(slug) {
      const { data } = await api.get(`/api/whatsapp/forms/${slug}/public`)
      return data
    },
    async submitPublicForm(slug, formData) {
      const { data } = await api.post(`/api/whatsapp/forms/${slug}/submit`, formData)
      return data
    },

    async fetchTemplates() {
      const { data } = await api.get('/api/whatsapp/templates')
      this.templates = data
    },
    async createTemplate(payload) {
      const { data } = await api.post('/api/whatsapp/templates', payload)
      this.templates.unshift(data)
      return data
    },
    async updateTemplate(id, payload) {
      const { data } = await api.patch(`/api/whatsapp/templates/${id}`, payload)
      const index = this.templates.findIndex((t) => t.id === id)
      if (index !== -1) this.templates[index] = data
      return data
    },
    async deleteTemplate(id) {
      await api.delete(`/api/whatsapp/templates/${id}`)
      this.templates = this.templates.filter((t) => t.id !== id)
    },

    async fetchContactGroups() {
      const { data } = await api.get('/api/whatsapp/contact-groups')
      this.contactGroups = data
    },
    async createContactGroup(payload) {
      const { data } = await api.post('/api/whatsapp/contact-groups', payload)
      this.contactGroups.unshift(data)
      return data
    },
    async updateContactGroup(id, payload) {
      const { data } = await api.patch(`/api/whatsapp/contact-groups/${id}`, payload)
      const index = this.contactGroups.findIndex((g) => g.id === id)
      if (index !== -1) this.contactGroups[index] = data
      return data
    },
    async deleteContactGroup(id) {
      await api.delete(`/api/whatsapp/contact-groups/${id}`)
      this.contactGroups = this.contactGroups.filter((g) => g.id !== id)
    },

    async fetchContacts(groupId, { search = '', page = 1 } = {}) {
      this.contactsLoading = true
      try {
        const { data } = await api.get(`/api/whatsapp/contact-groups/${groupId}/contacts`, {
          params: { search: search || undefined, page },
        })
        this.contacts = data.data
        this.contactsMeta = { currentPage: data.current_page, lastPage: data.last_page, total: data.total }
      } finally {
        this.contactsLoading = false
      }
    },
    async createContact(groupId, payload) {
      const { data } = await api.post(`/api/whatsapp/contact-groups/${groupId}/contacts`, payload)
      return data
    },
    async importContactsCsv(groupId, file) {
      const formData = new FormData()
      formData.append('file', file)
      const { data } = await api.post(`/api/whatsapp/contact-groups/${groupId}/contacts/import`, formData)
      return data
    },
    async importContactsFromWhatsapp(groupId, channelId) {
      const { data } = await api.post(`/api/whatsapp/contact-groups/${groupId}/contacts/import-whatsapp`, { channel_id: channelId })
      return data
    },
    async exportContacts(groupId, groupName) {
      const response = await api.get(`/api/whatsapp/contact-groups/${groupId}/contacts/export`, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = `${groupName || 'contacts'}.csv`
      link.click()
      URL.revokeObjectURL(url)
    },
    async validateContacts(groupId, { channelId, ids = null }) {
      const { data } = await api.post(`/api/whatsapp/contact-groups/${groupId}/contacts/validate`, {
        channel_id: channelId,
        ids,
      })
      return data
    },
    async deleteInvalidContacts(groupId) {
      const { data } = await api.delete(`/api/whatsapp/contact-groups/${groupId}/contacts/invalid`)
      return data
    },
    async bulkDeleteContacts(groupId, ids) {
      await api.delete(`/api/whatsapp/contact-groups/${groupId}/contacts/bulk`, { data: { ids } })
    },
    async deleteContact(groupId, contactId) {
      await api.delete(`/api/whatsapp/contact-groups/${groupId}/contacts/${contactId}`)
    },

    async fetchApiSettings() {
      const { data } = await api.get('/api/whatsapp/api-settings')
      return data
    },
    async updateApiSettings(enabledGroups) {
      const { data } = await api.put('/api/whatsapp/api-settings', { enabled_groups: enabledGroups })
      return data
    },
  },
  getters: {
    connectedChannels: (state) => state.channels.filter((c) => c.status === 'connected'),
  },
})
