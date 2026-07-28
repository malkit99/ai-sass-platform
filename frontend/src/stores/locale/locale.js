import { defineStore } from 'pinia'

const STORAGE_KEY = 'locale'

export const useLocaleStore = defineStore('locale', {
  state: () => ({
    current: localStorage.getItem(STORAGE_KEY) ?? 'en',
  }),
  actions: {
    setLocale(locale) {
      this.current = locale
      localStorage.setItem(STORAGE_KEY, locale)
    },
  },
})
