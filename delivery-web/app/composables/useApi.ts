export const useApi = () => {
  const config = useRuntimeConfig()
  const token = useState<string | null>('auth-token')

  return $fetch.create({
    baseURL: config.public.apiBase,

    headers: {
      Accept: 'application/json',
    },

    onRequest({ options }) {
      if (token.value) {
        const headers = new Headers(options.headers)
        headers.set('Authorization', `Bearer ${token.value}`)
        options.headers = headers
      }
    },
  })
}