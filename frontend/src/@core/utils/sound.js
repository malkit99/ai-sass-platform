// Plays the "WhatsApp connected" confirmation sound. The file lives in
// public/images/audio/ (Vite copies public/ as-is into every build, dev and
// prod alike, served from the same root-relative path) so this needs no
// bundler import.
const CONNECTED_SOUND_URL = '/images/audio/whats-app-connected.wav'

/**
 * Plays twice, ~600ms apart, to confirm a WhatsApp account just connected.
 * Playback failures (e.g. a browser autoplay block) are swallowed — a missed
 * confirmation sound shouldn't surface as an error to the user.
 */
export function playConnectedChime() {
  const play = () => new Audio(CONNECTED_SOUND_URL).play().catch(() => {})

  play()
  setTimeout(play, 600)
}
