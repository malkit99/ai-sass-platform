import axios from 'axios'

// Default derives the API host from whatever hostname the SPA itself was loaded
// from (e.g. acme.localhost:5173 -> acme.localhost:8010), so reseller white-label
// domain resolution on the backend actually gets exercised. In production the
// reseller's custom domain would typically serve both the SPA and proxy /api to
// the backend under that same domain, so this stays consistent there too.
const defaultApiUrl = `${window.location.protocol}//${window.location.hostname}:8010`

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? defaultApiUrl,
  withCredentials: true,
  withXSRFToken: true,
})

export async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}

export default api
