import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

// Themed instance — rounded popup/buttons to match the app's global button
// rounding convention, flat (non-shadowed) confirm button matching the
// modal-button convention used across the app's own dialogs.
const swal = Swal.mixin({
  buttonsStyling: false,
  width: 380,
  customClass: {
    popup: 'app-swal-popup',
    title: 'app-swal-title',
    htmlContainer: 'app-swal-text',
    actions: 'app-swal-actions',
    confirmButton: 'v-btn v-btn--variant-flat app-swal-btn app-swal-btn--confirm',
    cancelButton: 'v-btn v-btn--variant-outlined app-swal-btn app-swal-btn--cancel',
  },
})

export function fireSuccess(title, text) {
  return swal.fire({ icon: 'success', title, text, timer: 2500, showConfirmButton: false })
}

export function fireError(title, text) {
  return swal.fire({ icon: 'error', title, text })
}

export function fireWarning(title, text) {
  return swal.fire({ icon: 'warning', title, text })
}

export function fireConfirm(title, text) {
  return swal
    .fire({
      icon: 'warning',
      title,
      text,
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
    })
    .then((result) => result.isConfirmed)
}

export default swal
