<script setup lang="ts">
const email = ref('budi@example.com')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const { login } = useAuth()

const handleLogin = async () => {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await login(
      email.value,
      password.value
    )

    const roleRoutes: Record<string, string> = {
      admin: '/admin',
      dispatcher: '/dispatcher',
      courier: '/courier',
      customer: '/customer'
    }

    const destination = roleRoutes[response.user.role.slug]

    if (!destination) {
      throw new Error('Role pengguna tidak dikenali.')
    }

    console.log('Login berhasil:', response.user)
    console.log('Redirect ke:', destination)

    await navigateTo(destination)

  } catch (error: any) {
    errorMessage.value =
      error.data?.message ||
      error.message ||
      'Login gagal. Periksa email dan password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page">

    <div class="ambient ambient-one"></div>
    <div class="ambient ambient-two"></div>

    <section class="login-container">

      <div class="brand">
        <div class="brand-icon">
          <span>☾</span>
        </div>

        <div>
          <h1>KURIR<span>MALAM</span></h1>
          <p>DELIVERY MANAGEMENT SYSTEM</p>
        </div>
      </div>

      <div class="night-scene">
        <div class="moon"></div>

        <div class="route">
          <div class="route-line"></div>
          <div class="route-point point-one"></div>
          <div class="route-point point-two"></div>
          <div class="route-point point-three"></div>
        </div>

        <div class="scene-text">
          <span>YOUR JOURNEY STARTS HERE</span>
          <h2>Every delivery<br />has a destination.</h2>
          <p>Reliable routes. Smarter deliveries.</p>
        </div>
      </div>

      <div class="login-card">
        <div class="card-heading">
          <span class="eyebrow">WELCOME BACK</span>
          <h2>Sign in<span>.</span></h2>
          <p>Enter your credentials to continue.</p>
        </div>

        <form @submit.prevent="handleLogin">

          <div class="form-group">
            <label>Email address</label>
            <input
              v-model="email"
              type="email"
              placeholder="you@example.com"
              required
            />
          </div>

          <div class="form-group">
            <label>Password</label>
            <input
              v-model="password"
              type="password"
              placeholder="Enter your password"
              required
            />
          </div>

          <p v-if="errorMessage" class="error">
            {{ errorMessage }}
          </p>
          <p v-if="successMessage" class="success">
            {{ successMessage }}
          </p>

          <button type="submit" :disabled="loading">
            <span>
              {{ loading ? 'AUTHENTICATING...' : 'SIGN IN TO YOUR ACCOUNT' }}
            </span>
            <span class="arrow">→</span>
          </button>

        </form>
      </div>

      <footer>
        <span class="status-dot"></span>
        SYSTEM OPERATIONAL
        <span class="copyright">© 2026 KURIR MALAM</span>
      </footer>

    </section>
  </main>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  background: #080e19;
  color: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 35px 20px;
  font-family: Arial, sans-serif;
  position: relative;
  overflow: hidden;
}

.ambient {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  pointer-events: none;
}

.ambient-one {
  width: 300px;
  height: 300px;
  background: #142d50;
  top: -100px;
  left: -100px;
  opacity: .6;
}

.ambient-two {
  width: 250px;
  height: 250px;
  background: #a36b17;
  bottom: -150px;
  right: -100px;
  opacity: .18;
}

.login-container {
  width: 100%;
  max-width: 420px;
  position: relative;
  z-index: 1;
}

.brand {
  display: flex;
  align-items: center;
  gap: 13px;
  margin-bottom: 30px;
}

.brand-icon {
  width: 48px;
  height: 48px;
  border: 1px solid #eab44b;
  border-radius: 14px;
  display: grid;
  place-items: center;
  color: #f5bd50;
  font-size: 32px;
  background: #182235;
}

.brand h1 {
  font-size: 19px;
  letter-spacing: 2px;
  margin: 0;
}

.brand h1 span {
  color: #f5bd50;
}

.brand p {
  font-size: 9px;
  letter-spacing: 2px;
  color: #8290a5;
  margin: 5px 0 0;
}

.success {
  color: #4ade80;
  font-size: 12px;
}

.night-scene {
  height: 190px;
  border-radius: 18px;
  background:
    linear-gradient(110deg, #172943, #101b2d 65%, #1d2635);
  position: relative;
  overflow: hidden;
  padding: 24px;
  margin-bottom: 18px;
  border: 1px solid #27364b;
}

.moon {
  position: absolute;
  width: 65px;
  height: 65px;
  border-radius: 50%;
  background: #f3c76d;
  right: 45px;
  top: 25px;
  box-shadow: 0 0 45px #eebd5b55;
}

.route {
  position: absolute;
  width: 170px;
  height: 120px;
  right: -5px;
  bottom: -10px;
  opacity: .65;
}

.route-line {
  position: absolute;
  width: 100px;
  height: 85px;
  right: 25px;
  top: 15px;
  border: 2px dashed #eebd5b;
  border-left: 0;
  border-bottom: 0;
  border-radius: 0 35px 0 0;
  transform: rotate(-12deg);
}

.route-point {
  position: absolute;
  width: 9px;
  height: 9px;
  background: #f5bd50;
  border: 2px solid #172943;
  border-radius: 50%;
}

.point-one { right: 25px; top: 15px; }
.point-two { right: 115px; top: 48px; }
.point-three { right: 55px; bottom: 5px; }

.scene-text {
  position: relative;
  z-index: 2;
}

.scene-text span,
.eyebrow {
  font-size: 9px;
  letter-spacing: 2px;
  color: #f5bd50;
  font-weight: bold;
}

.scene-text h2 {
  font-size: 23px;
  line-height: 1.3;
  margin: 12px 0 7px;
}

.scene-text p {
  font-size: 11px;
  color: #a9b5c7;
}

.login-card {
  background: #111c2d;
  border: 1px solid #28364a;
  border-radius: 18px;
  padding: 28px 24px;
  box-shadow: 0 20px 60px #00000035;
}

.card-heading h2 {
  font-size: 29px;
  margin: 10px 0 5px;
}

.card-heading h2 span {
  color: #f5bd50;
}

.card-heading p {
  font-size: 12px;
  color: #8795a9;
  margin-bottom: 26px;
}

.form-group {
  margin-bottom: 19px;
}

label {
  display: block;
  font-size: 11px;
  font-weight: bold;
  color: #c5cedb;
  margin-bottom: 9px;
}

input {
  width: 100%;
  padding: 14px;
  background: #0b1422;
  border: 1px solid #303e52;
  border-radius: 9px;
  color: white;
  box-sizing: border-box;
  outline: none;
  font-size: 13px;
}

input:focus {
  border-color: #eab44b;
}

input::placeholder {
  color: #65748a;
}

button {
  width: 100%;
  padding: 15px;
  border: none;
  border-radius: 9px;
  background: #eab44b;
  color: #111827;
  font-size: 10px;
  font-weight: bold;
  letter-spacing: 1px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  margin-top: 25px;
}

button:disabled {
  opacity: .6;
}

.arrow {
  font-size: 18px;
}

.error {
  color: #ff7777;
  font-size: 12px;
}

footer {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 23px;
  font-size: 9px;
  letter-spacing: 1px;
  color: #8290a5;
}

.status-dot {
  width: 7px;
  height: 7px;
  background: #4ade80;
  border-radius: 50%;
}

.copyright {
  margin-left: auto;
  font-size: 8px;
}

@media (max-width: 380px) {
  .login-card {
    padding: 23px 18px;
  }

  .night-scene {
    height: 175px;
  }
}
</style>