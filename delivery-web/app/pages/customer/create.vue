<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

const api = useApi()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = reactive({
  delivery_type: 'instant',
  sender_name: '',
  sender_phone: '',
  pickup_address: '',
  recipient_name: '',
  recipient_phone: '',
  delivery_address: '',
  scheduled_pickup_at: '',
  notes: ''
})

const submitOrder = async () => {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = {
      ...form,
      scheduled_pickup_at:
        form.delivery_type === 'scheduled'
          ? form.scheduled_pickup_at
          : null
    }

    await api('/orders', {
      method: 'POST',
      body: payload
    })

    successMessage.value = 'Delivery created successfully.'

    await navigateTo('/customer')
  } catch (error: any) {
    errorMessage.value =
      error.data?.message || 'Failed to create delivery.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="dashboard">
    <header class="topbar">
      <div class="brand">
        <div class="brand-icon">☾</div>
        <div>
          <h1>KURIR<span>MALAM</span></h1>
          <p>DELIVERY MANAGEMENT</p>
        </div>
      </div>

      <button class="logout-btn" @click="navigateTo('/customer')">
        ← Back
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">CUSTOMER PORTAL</span>
      <h2>Create a<br /><span>new delivery.</span></h2>
      <p>Fill in the details below to arrange your delivery.</p>
    </section>

    <form class="delivery-form" @submit.prevent="submitOrder">
      <div class="form-section">
        <h3>Delivery type</h3>

        <div class="type-options">
          <label>
            <input
              v-model="form.delivery_type"
              type="radio"
              value="instant"
            />
            <span>Instant delivery</span>
          </label>

          <label>
            <input
              v-model="form.delivery_type"
              type="radio"
              value="scheduled"
            />
            <span>Scheduled delivery</span>
          </label>
        </div>

        <div v-if="form.delivery_type === 'scheduled'" class="form-field">
          <label>Scheduled pickup</label>
          <input
            v-model="form.scheduled_pickup_at"
            type="datetime-local"
            required
          />
        </div>
      </div>

      <div class="form-section">
        <h3>Pickup information</h3>

        <div class="form-field">
          <label>Sender name</label>
          <input v-model="form.sender_name" required />
        </div>

        <div class="form-field">
          <label>Sender phone</label>
          <input v-model="form.sender_phone" type="tel" required />
        </div>

        <div class="form-field">
          <label>Pickup address</label>
          <textarea v-model="form.pickup_address" required />
        </div>
      </div>

      <div class="form-section">
        <h3>Recipient information</h3>

        <div class="form-field">
          <label>Recipient name</label>
          <input v-model="form.recipient_name" required />
        </div>

        <div class="form-field">
          <label>Recipient phone</label>
          <input v-model="form.recipient_phone" type="tel" required />
        </div>

        <div class="form-field">
          <label>Delivery address</label>
          <textarea v-model="form.delivery_address" required />
        </div>
      </div>

      <div class="form-section">
        <h3>Additional notes</h3>

        <div class="form-field">
          <label>Notes (optional)</label>
          <textarea
            v-model="form.notes"
            placeholder="Any special instructions?"
          />
        </div>
      </div>

      <p v-if="errorMessage" class="form-error">
        {{ errorMessage }}
      </p>

      <button class="submit-btn" type="submit" :disabled="loading">
        {{ loading ? 'Creating delivery...' : 'Create delivery →' }}
      </button>
    </form>
  </main>
</template>