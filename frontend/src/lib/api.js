import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8010',
  withCredentials: true,
  withXSRFToken: true,
})

export async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}

export default api
