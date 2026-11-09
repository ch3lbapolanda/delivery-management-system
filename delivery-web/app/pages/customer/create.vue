
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Coordinates {
  latitude: number | null
  longitude: number | null
}

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
  notes: '',
  pickup_location: {
    latitude: null,
    longitude: null
  } as Coordinates,
  delivery_location: {
    latitude: null,
    longitude: null
  } as Coordinates
})

const submitOrder = async () => {
  errorMessage.value = ''
  successMessage.value = ''

  if (
    form.pickup_location.latitude === null ||
    form.pickup_location.longitude === null
  ) {
    errorMessage.value = 'Please select your pickup location.'
    return
  }

  if (
    form.delivery_location.latitude === null ||
    form.delivery_location.longitude === null
  ) {
    errorMessage.value = 'Please select your delivery location.'
    return
  }

  loading.value = true

  try {
    const payload = {
      delivery_type: form.delivery_type,
      sender_name: form.sender_name,
      sender_phone: form.sender_phone,
      pickup_address: form.pickup_address,
      pickup_latitude: form.pickup_location.latitude,
      pickup_longitude: form.pickup_location.longitude,
      recipient_name: form.recipient_name,
      recipient_phone: form.recipient_phone,
      delivery_address: form.delivery_address,
      delivery_latitude: form.delivery_location.latitude,
      delivery_longitude: form.delivery_location.longitude,
      scheduled_pickup_at:
        form.delivery_type === 'scheduled'
          ? form.scheduled_pickup_at
          : null,
      notes: form.notes || null
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

        <div
          v-if="form.delivery_type === 'scheduled'"
          class="form-field"
        >
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
          <input
            v-model="form.sender_phone"
            type="tel"
            required
          />
        </div>

        <div class="form-field">
          <label>Pickup address</label>
          <textarea
            v-model="form.pickup_address"
            required
          />
        </div>

        <ClientOnly>
          <LocationPicker
            v-model="form.pickup_location"
            title="Select Pickup Location"
            :allow-current-location="true"
          />

          <template #fallback>
            <p>Loading pickup map...</p>
          </template>
        </ClientOnly>
      </div>

      <div class="form-section">
        <h3>Recipient information</h3>

        <div class="form-field">
          <label>Recipient name</label>
          <input
            v-model="form.recipient_name"
            required
          />
        </div>

        <div class="form-field">
          <label>Recipient phone</label>
          <input
            v-model="form.recipient_phone"
            type="tel"
            required
          />
        </div>

        <div class="form-field">
          <label>Delivery address</label>
          <textarea
            v-model="form.delivery_address"
            required
          />
        </div>

        <ClientOnly>
          <LocationPicker
            v-model="form.delivery_location"
            title="Select Delivery Location"
          />

          <template #fallback>
            <p>Loading delivery map...</p>
          </template>
        </ClientOnly>
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

      <p v-if="successMessage" class="success-message">
        {{ successMessage }}
      </p>

      <button
        class="submit-btn"
        type="submit"
        :disabled="loading"
      >
        {{ loading ? 'Creating delivery...' : 'Create delivery →' }}
      </button>
    </form>
  </main>
</template>
