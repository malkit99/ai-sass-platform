import { defineStore } from 'pinia'
import api from '@core/utils/api'

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    productName: 'CRM Platform',
    primaryColor: '#1976D2',
    logoUrl: null,
    loaded: false,
  }),
  actions: {
    async fetch() {
      try {
        const { data } = await api.get('/api/branding')
        this.productName = data.product_name ?? this.productName
        this.primaryColor = data.primary_color ?? this.primaryColor
        this.logoUrl = data.logo_url ?? null
      } finally {
        this.loaded = true
        document.title = this.productName
      }
    },
  },
})
