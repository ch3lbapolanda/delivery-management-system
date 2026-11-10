
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Coordinates {
  latitude: number | null
  longitude: number | null
}

interface Pricing {
  delivery_type: string
  distance_km: number
  base_price: number
  price_per_km: number
  distance_fee: number
  additional_fee: number
  minimum_price: number
  total_price: number
}

const api = useApi()

const loading = ref(false)
const pricingLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const pricingError = ref('')
const pricing = ref<Pricing | null>(null)

let pricingTimer: ReturnType<typeof setTimeout> | null = null
let pricingRequestId = 0

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

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(value)
}

const calculatePricing = async () => {
  const pickup = form.pickup_location
  const delivery = form.delivery_location

  if (
    pickup.latitude === null ||
    pickup.longitude === null ||
    delivery.latitude === null ||
    delivery.longitude === null
  ) {
    pricing.value = null
    return false
  }

  const requestId = ++pricingRequestId

  pricingLoading.value = true
  pricingError.value = ''
  pricing.value = null

  try {
    const response: any = await api('/pricing/estimate', {
      method: 'POST',
      body: {
        delivery_type: form.delivery_type,
        pickup_latitude: pickup.latitude,
        pickup_longitude: pickup.longitude,
        delivery_latitude: delivery.latitude,
        delivery_longitude: delivery.longitude
      }
    })

    if (requestId !== pricingRequestId) {
      return false
    }

    pricing.value = response.data

    return true
  } catch (error: any) {
    if (requestId === pricingRequestId) {
      pricingError.value =
        error.data?.message || 'Failed to calculate delivery price.'
    }

    return false
  } finally {
    if (requestId === pricingRequestId) {
      pricingLoading.value = false
    }
  }
}

watch(
  () => [
    form.delivery_type,
    form.pickup_location.latitude,
    form.pickup_location.longitude,
    form.delivery_location.latitude,
    form.delivery_location.longitude
  ],
  () => {
    if (pricingTimer) {
      clearTimeout(pricingTimer)
    }

    pricingRequestId++
    pricing.value = null
    pricingError.value = ''
    pricingLoading.value = false

    const pickup = form.pickup_location
    const delivery = form.delivery_location

    if (
      pickup.latitude === null ||
      pickup.longitude === null ||
      delivery.latitude === null ||
      delivery.longitude === null
    ) {
      return
    }

    pricingTimer = setTimeout(() => {
      calculatePricing()
    }, 500)
  }
)

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

  if (form.delivery_type === 'scheduled' && !form.scheduled_pickup_at) {
    errorMessage.value = 'Please select scheduled pickup time.'
    return
  }

  if (!pricing.value) {
    const calculated = await calculatePricing()

    if (!calculated || !pricing.value) {
      errorMessage.value =
        pricingError.value || 'Please wait for price estimation.'
      return
    }
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

      <button
        class="logout-btn"
        type="button"
        @click="navigateTo('/customer')"
      >
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

      <div class="form-section pricing-section">
        <h3>Delivery price estimation</h3>

        <div v-if="pricingLoading" class="pricing-loading">
          Calculating delivery price...
        </div>

        <div v-else-if="pricing" class="pricing-card">
          <div class="pricing-row">
            <span>Service type</span>
            <strong>
              {{ pricing.delivery_type === 'instant' ? 'Instant' : 'Scheduled' }}
            </strong>
          </div>

          <div class="pricing-row">
            <span>Estimated distance</span>
            <strong>{{ pricing.distance_km }} km</strong>
          </div>

          <div class="pricing-row">
            <span>Base price</span>
            <strong>{{ formatCurrency(pricing.base_price) }}</strong>
          </div>

          <div class="pricing-row">
            <span>Distance fee</span>
            <strong>{{ formatCurrency(pricing.distance_fee) }}</strong>
          </div>

          <div
            v-if="pricing.additional_fee > 0"
            class="pricing-row"
          >
            <span>Service fee</span>
            <strong>{{ formatCurrency(pricing.additional_fee) }}</strong>
          </div>

          <div class="pricing-row">
            <span>Minimum price</span>
            <strong>{{ formatCurrency(pricing.minimum_price) }}</strong>
          </div>

          <div class="pricing-total">
            <span>Total estimated price</span>
            <strong>{{ formatCurrency(pricing.total_price) }}</strong>
          </div>

          <p class="pricing-disclaimer">
            Final price will be recalculated by the server when the order is created.
          </p>
        </div>

        <p v-else-if="pricingError" class="form-error">
          {{ pricingError }}
        </p>

        <p v-else class="pricing-placeholder">
          Select pickup and delivery locations to calculate the price.
        </p>
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
        :disabled="loading || pricingLoading"
      >
        {{
          loading
            ? 'Creating delivery...'
            : pricingLoading
              ? 'Calculating price...'
              : 'Create delivery →'
        }}
      </button>
    </form>
  </main>
</template>

