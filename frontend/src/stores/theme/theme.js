import { defineStore } from 'pinia'

const STORAGE_KEY = 'theme-settings'
const media = window.matchMedia('(prefers-color-scheme: dark)')

const defaults = {
  mode: 'system', // 'light' | 'dark' | 'system'
  skin: 'default', // 'default' | 'border' — border makes cards outlined instead of filled/tonal
  themeColor: '#1976D2', // primary color applied to buttons, links, active states
  themeColorCustomized: false, // once true, themeColor wins over the reseller's branding.primaryColor
  contentWidth: 'fluid', // 'fluid' | 'boxed' — boxed caps page content to a centered max-width
}

function loadPersisted() {
  try {
    return { ...defaults, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') }
  } catch {
    return { ...defaults }
  }
}

export const useThemeStore = defineStore('theme', {
  state: () => ({
    ...loadPersisted(),
    systemIsDark: media.matches,
  }),
  getters: {
    // The actual Vuetify theme name to render, resolving 'system' to the OS preference.
    effectiveTheme: (state) => (state.mode === 'system' ? (state.systemIsDark ? 'dark' : 'light') : state.mode),
  },
  actions: {
    persist() {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          mode: this.mode,
          skin: this.skin,
          themeColor: this.themeColor,
          themeColorCustomized: this.themeColorCustomized,
          contentWidth: this.contentWidth,
        }),
      )
    },
    setMode(mode) {
      this.mode = mode
      this.persist()
    },
    setSkin(skin) {
      this.skin = skin
      this.persist()
    },
    setThemeColor(color) {
      this.themeColor = color
      this.themeColorCustomized = true
      this.persist()
    },
    resetThemeColor() {
      this.themeColor = defaults.themeColor
      this.themeColorCustomized = false
      this.persist()
    },
    setContentWidth(width) {
      this.contentWidth = width
      this.persist()
    },
    watchSystemPreference() {
      media.addEventListener('change', (e) => {
        this.systemIsDark = e.matches
      })
    },
  },
})
