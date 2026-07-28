import { createI18n } from 'vue-i18n'
import en from '@core/locales/en'
import hi from '@core/locales/hi'
import es from '@core/locales/es'
import fr from '@core/locales/fr'

const STORAGE_KEY = 'locale'

// Windows renders flag emoji as literal ISO country-code text (e.g. "GB")
// rather than a flag glyph, so the switcher intentionally has no flags.
export const availableLocales = [
  { value: 'en', label: 'English' },
  { value: 'hi', label: 'हिन्दी' },
  { value: 'es', label: 'Español' },
  { value: 'fr', label: 'Français' },
]

export default createI18n({
  legacy: false,
  locale: localStorage.getItem(STORAGE_KEY) ?? 'en',
  fallbackLocale: 'en',
  messages: { en, hi, es, fr },
})
