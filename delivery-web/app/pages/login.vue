
<script setup lang="ts">
definePageMeta({
  middleware: [],
})

const { login, user } = useAuth()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

async function handleLogin() {
  errorMessage.value = ''

  if (!email.value || !password.value) {
    errorMessage.value = 'Email and password are required.'
    return
  }

  loading.value = true

  try {
    await login(email.value, password.value)

    const role = user.value?.role?.slug

    const destinations: Record<string, string> = {
      admin: '/admin',
      dispatcher: '/dispatcher',
      courier: '/courier',
      customer: '/customer',
    }

    await navigateTo(destinations[role || ''] || '/')
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Invalid email or password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page">
    <div class="login-container">
      <div class="login-brand">
        <div class="login-brand-icon">☾</div>
        <div>
          <h1>KURIR<span>MALAM</span></h1>
          <p>DELIVERY MANAGEMENT SYSTEM</p>
        </div>
      </div>

      <section class="login-card">
        <span class="login-eyebrow">WELCOME BACK</span>

        <h2>Sign in to your account</h2>
        <p class="login-description">
          Access your delivery management portal.
        </p>

        <form @submit.prevent="handleLogin">
          <div class="login-field">
            <label for="login-email">Email address</label>
            <input
              id="login-email"
              v-model="email"
              type="email"
              placeholder="name@example.com"
              autocomplete="username"
              required
            />
          </div>

          <div class="login-field">
            <label for="login-password">Password</label>

            <div class="login-password-wrap">
              <input
                id="login-password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              />

              <button
                type="button"
                class="login-show-password"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </div>

          <div v-if="errorMessage" class="login-error">
            {{ errorMessage }}
          </div>

          <button
            type="submit"
            class="login-submit"
            :disabled="loading"
          >
            {{ loading ? 'Signing in...' : 'SIGN IN TO YOUR ACCOUNT →' }}
          </button>
        </form>

        <div class="login-footer">
          <span class="login-status-dot"></span>
          Secure delivery management access
        </div>
      </section>

      <p class="login-copyright">
        © 2026 KURIR MALAM. ALL RIGHTS RESERVED.
      </p>
    </div>
  </main>
</template>
