import { defineStore } from 'pinia'

// Reusable app-wide notification store. Two channels:
//  - snackbar (this store): lightweight, non-blocking toasts for success/
//    warning/error/info — rendered once by the global <AppSnackbar /> in
//    App.vue, triggered from anywhere via useAlertStore().success(...) etc.
//  - SweetAlert2 (@core/plugins/sweetalert.js): blocking modal-style
//    confirmations, used where a more prominent "this succeeded" moment
//    matters (e.g. right after submitting a form), not routed through
//    Pinia state since it's an imperative call, not something to render.
export const useAlertStore = defineStore('alert', {
  state: () => ({
    snackbar: {
      show: false,
      message: '',
      color: 'success',
      timeout: 4000,
    },
  }),
  actions: {
    show(message, color = 'success', timeout = 4000) {
      this.snackbar = { show: true, message, color, timeout }
    },
    success(message) {
      this.show(message, 'success')
    },
    error(message) {
      this.show(message, 'error', 6000)
    },
    warning(message) {
      this.show(message, 'warning', 5000)
    },
    info(message) {
      this.show(message, 'info')
    },
    close() {
      this.snackbar.show = false
    },
  },
})
