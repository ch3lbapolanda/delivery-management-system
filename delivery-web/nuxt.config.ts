export default defineNuxtConfig({
  compatibilityDate: '2026-01-01',
  devtools: { enabled: true },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
  public: {
    apiBase: '/api',
    reverbKey: 'smoheiozbuwcsqhl1s6c',
    reverbHost: 'localhost',
    reverbPort: 8080
  }
  },

  routeRules: {
  '/api/**': {
    proxy: 'http://127.0.0.1:8000/api/**'
  },
  '/api/broadcasting/**': {
    proxy: 'http://127.0.0.1:8000/api/broadcasting/**'
  }
  }
})