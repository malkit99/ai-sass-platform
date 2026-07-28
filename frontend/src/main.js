import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import vuetify from '@core/plugins/vuetify'
import i18n from '@core/plugins/i18n'
import { useBrandingStore } from '@/stores/branding/branding'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(vuetify)
app.use(i18n)

;(async () => {
  const branding = useBrandingStore()
  await branding.fetch()
  // Primary color (branding default or user override) and dark/light theme
  // name are synced reactively in App.vue via the theme store.

  app.mount('#app')
})()
