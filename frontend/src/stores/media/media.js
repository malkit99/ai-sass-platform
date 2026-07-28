import { defineStore } from 'pinia'
import api from '@core/utils/api'

export const useMediaStore = defineStore('media', {
  state: () => ({
    files: [],
    loading: false,
    uploading: false,
    usage: null,
  }),
  actions: {
    async fetchFiles({ type = null, purpose = null } = {}) {
      this.loading = true
      try {
        const params = {}
        if (type) params.type = type
        if (purpose) params.purpose = purpose
        const { data } = await api.get('/api/media', { params })
        this.files = data
      } finally {
        this.loading = false
      }
    },
    async uploadFile(file, purpose = 'whatsapp_media') {
      this.uploading = true
      try {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('purpose', purpose)
        const { data } = await api.post('/api/media', formData)
        this.files.unshift(data)
        return data
      } finally {
        this.uploading = false
      }
    },
    async deleteFile(id) {
      await api.delete(`/api/media/${id}`)
      this.files = this.files.filter((f) => f.id !== id)
    },
    async fetchUsage() {
      const { data } = await api.get('/api/media/usage')
      this.usage = data
    },
  },
})
