import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { useBrandingStore } from './stores/branding'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(vuetify)

const branding = useBrandingStore()
await branding.fetch()
vuetify.theme.themes.value.light.colors.primary = branding.primaryColor

app.mount('#app')
