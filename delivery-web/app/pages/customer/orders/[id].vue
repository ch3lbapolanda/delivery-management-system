
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface OrderHistory {
  id: number
  old_status: string | null
  new_status: string
  note: string | null
  created_at: string
}

interface OrderEta {
  available: boolean
  distance_km: number | null
  duration_minutes: number | null
  estimated_arrival_at: string | null
  message: string
}

interface DeliveryProof {
  id: number
  order_id: number
  uploaded_by: {
    id: number
    name: string
  } | null
  photo_path: string
  photo_url: string
  recipient_name: string
  notes: string | null
  created_at: string
}

  interface PricingDetails {
  delivery_type: string
  distance_km: number
  base_price: number
  price_per_km: number
  distance_fee: number
  additional_fee: number
  minimum_price: number
  total_price: number
}

interface Order {
  id: number
  order_number: string
  delivery_type: string
  status: string
  price: string | number | null
  pricing_details?: PricingDetails | null
  sender_name: string
  sender_phone: string
  pickup_address: string
  recipient_name: string
  recipient_phone: string
  delivery_address: string
  notes: string | null
  created_at: string
  status_histories: OrderHistory[]
  delivery_proof?: DeliveryProof | null
  eta?: OrderEta
  
}

interface OrderResponse {
  data: Order
  eta?: OrderEta
}

const route = useRoute()
const api = useApi()


interface TrackingLocation {
  latitude: number
  longitude: number
  accuracy: number | null
  speed: number | null
  heading: number | null
  recorded_at: string
}

const trackingLocation = ref<TrackingLocation | null>(null)
const trackingMessage = ref('Waiting for courier location...')
let trackingTimer: ReturnType<typeof setInterval> | null = null

const fetchTracking = async () => {
  try {
    const response = await api<{
      data: TrackingLocation | null
      message: string
    }>(`/orders/${route.params.id}/tracking`)

    trackingLocation.value = response.data
    trackingMessage.value = response.message
  } catch {
    trackingMessage.value = 'Unable to retrieve courier location.'
  }
}

const startTracking = () => {
  stopTracking()
  trackingLocation.value = null

  if (order.value?.status !== 'in_transit') return

  fetchTracking()
  trackingTimer = setInterval(fetchTracking, 8000)
}

const stopTracking = () => {
  if (trackingTimer !== null) {
    clearInterval(trackingTimer)
    trackingTimer = null
  }
}


const order = ref<Order | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const cancelDialog = ref(false)
const cancelReason = ref('')
const cancelLoading = ref(false)
const cancelError = ref('')

const canCancel = computed(() =>
  ['pending', 'assigned'].includes(order.value?.status || '')
)

  const actualDeliveredAt = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'delivered')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0]?.created_at || null
})

const fetchOrder = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<Order | OrderResponse>(
      `/orders/${route.params.id}`
    )

    if ('data' in response) {
      order.value = {
        ...response.data,
        eta: response.eta
      }
    } else {
      order.value = response
    }

    if (!order.value || !order.value.id) {
      throw new Error('Invalid order response')
    }
    startTracking()
  } catch (error: any) {
    order.value = null
    errorMessage.value =
      error.data?.message ||
      error.message ||
      'Unable to load order details.'
  } finally {
    loading.value = false
  }
}

const submitCancellation = async () => {
  if (!cancelReason.value.trim()) {
    cancelError.value = 'Please provide a cancellation reason.'
    return
  }

  cancelLoading.value = true
  cancelError.value = ''

  try {
    await api(`/orders/${route.params.id}/cancel`, {
      method: 'POST',
      body: {
        cancellation_reason: cancelReason.value.trim()
      }
    })

    cancelDialog.value = false
    cancelReason.value = ''

    await fetchOrder()
  } catch (error: any) {
    cancelError.value =
      error.data?.message || 'Unable to cancel this order.'
  } finally {
    cancelLoading.value = false
  }
}

const formatCurrency = (value: string | number | null) => {
  if (value === null || value === undefined) {
    return 'Price unavailable'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(Number(value))
}
  
const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

const formatEtaDate = (date: string) => {
  return new Date(date).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

const goBack = () => {
  navigateTo('/customer/orders')
}

onMounted(() => {
  fetchOrder()
})
  onBeforeUnmount(() => {
  stopTracking()
})
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

      <button class="logout-btn" @click="goBack">
        ← Back
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">ORDER DETAILS</span>
      <h2>Delivery<br /><span>information.</span></h2>
      <p>View your order and its delivery progress.</p>
    </section>

    <div v-if="loading" class="empty-state">
      Loading order details...
    </div>

    <div v-else-if="errorMessage" class="empty-state">
      <h4>Unable to load order</h4>
      <p>{{ errorMessage }}</p>
      <button @click="fetchOrder">
        Try again
      </button>
    </div>

    <template v-else-if="order">

      <section class="detail-card order-price-card">
  <span class="eyebrow">PAYMENT INFORMATION</span>
  <h3>Delivery price</h3>

  <div class="order-price-row">
    <span>Service type</span>
    <strong>
      {{
        order.pricing_details?.delivery_type === 'instant'
          ? 'Instant'
          : order.pricing_details?.delivery_type === 'scheduled'
            ? 'Scheduled'
            : order.delivery_type === 'instant'
              ? 'Instant'
              : 'Scheduled'
      }}
    </strong>
  </div>

  <template v-if="order.pricing_details">
    <div class="order-price-row">
      <span>Delivery distance</span>
      <strong>{{ order.pricing_details.distance_km }} km</strong>
    </div>

    <div class="order-price-row">
      <span>Base price</span>
      <strong>{{ formatCurrency(order.pricing_details.base_price) }}</strong>
    </div>

    <div class="order-price-row">
      <span>Price per km</span>
      <strong>{{ formatCurrency(order.pricing_details.price_per_km) }}</strong>
    </div>

    <div class="order-price-row">
      <span>Distance fee</span>
      <strong>{{ formatCurrency(order.pricing_details.distance_fee) }}</strong>
    </div>

    <div class="order-price-row">
      <span>Additional fee</span>
      <strong>{{ formatCurrency(order.pricing_details.additional_fee) }}</strong>
    </div>

    <div class="order-price-row">
      <span>Minimum price</span>
      <strong>{{ formatCurrency(order.pricing_details.minimum_price) }}</strong>
    </div>
  </template>

  <div class="order-price-total">
    <span>Total delivery price</span>
    <strong>{{ formatCurrency(order.pricing_details?.total_price ?? order.price) }}</strong>
  </div>

  <p class="order-price-note">
    {{
      order.pricing_details
        ? 'Historical pricing snapshot recorded when this order was created.'
        : 'Historical pricing details are unavailable for this order.'
    }}
  </p>
</section>
      
      <section class="detail-card">
        <span class="eyebrow">ORDER NUMBER</span>
        <h2>{{ order.order_number }}</h2>

        <span class="status" :class="`status-${order.status}`">
          {{ order.status.replace('_', ' ') }}
        </span>

        <p class="detail-date">
          Created {{ formatDate(order.created_at) }}
        </p>

        <button
          v-if="canCancel"
          class="cancel-order-btn"
          @click="cancelDialog = true"
        >
          Cancel this order
        </button>
      </section>

      <section class="detail-card eta-card">

  <template v-if="order.status === 'delivered'">
    <span class="eyebrow">DELIVERY COMPLETED</span>
    <h3>Actual Delivery Time</h3>

    <template v-if="actualDeliveredAt">
      <div class="eta-highlight">
        ✓ Delivered
      </div>

      <div class="eta-info">
        <div class="eta-info-row">
          <span>Completion Date</span>
          <strong>{{ formatEtaDate(actualDeliveredAt) }}</strong>
        </div>

        <div class="eta-info-row">
          <span>Delivery Status</span>
          <strong>Successfully Delivered</strong>
        </div>
      </div>

      <p class="eta-disclaimer">
        This is the actual delivery completion time recorded by the courier.
      </p>
    </template>

    <template v-else>
      <p class="eta-unavailable">
        Actual delivery time is not available.
      </p>
    </template>
  </template>

  <template v-else>
    <span class="eyebrow">DELIVERY ESTIMATE</span>
    <h3>Estimated arrival</h3>

    <template v-if="order.eta?.available">
      <div class="eta-highlight">
        {{ order.eta.duration_minutes }} minutes
      </div>

      <div class="eta-info">
        <div class="eta-info-row">
          <span>Estimated distance</span>
          <strong>{{ order.eta.distance_km }} km</strong>
        </div>

        <div class="eta-info-row">
          <span>Travel duration</span>
          <strong>{{ order.eta.duration_minutes }} minutes</strong>
        </div>

        <div class="eta-info-row">
          <span>Estimated arrival</span>
          <strong>
            {{ formatEtaDate(order.eta.estimated_arrival_at!) }}
          </strong>
        </div>
      </div>

      <p class="eta-disclaimer">
        {{ order.eta.message }}
      </p>
    </template>

    <template v-else>
      <p class="eta-unavailable">
        {{ order.eta?.message || 'ETA belum tersedia.' }}
      </p>

      <p class="eta-disclaimer">
        Estimasi akan tersedia setelah kurir memulai perjalanan
        dan koordinat pickup serta tujuan tersedia.
      </p>
    </template>
  </template>

</section>

      <section class="detail-card">
        <h3>Pickup information</h3>
        <p><strong>{{ order.sender_name }}</strong></p>
        <p>{{ order.sender_phone }}</p>
        <p>{{ order.pickup_address }}</p>
      </section>
      <!-- LIVE COURIER TRACKING -->
<section
  v-if="order.status === 'in_transit'"
  class="detail-card tracking-card"
>
  <span class="eyebrow">LIVE TRACKING</span>
  <h3>Courier location</h3>

  <template v-if="trackingLocation">
    <ClientOnly>
      <CourierTrackingMap
        :latitude="trackingLocation.latitude"
        :longitude="trackingLocation.longitude"
      />
    </ClientOnly>

    <p class="tracking-status">
      ● Courier location updated
    </p>

    <p class="tracking-time">
      Last update: {{ formatDate(trackingLocation.recorded_at) }}
    </p>
  </template>

  <p v-else class="eta-unavailable">
    {{ trackingMessage }}
  </p>
</section>

      <section class="detail-card">
        <h3>Recipient information</h3>
        <p><strong>{{ order.recipient_name }}</strong></p>
        <p>{{ order.recipient_phone }}</p>
        <p>{{ order.delivery_address }}</p>
      </section>

      <section class="detail-card">
        <h3>Delivery information</h3>
        <p>Type: {{ order.delivery_type }}</p>
        <p>Notes: {{ order.notes || 'No additional notes' }}</p>
      </section>

      <!-- PROOF OF DELIVERY -->
      <section
        v-if="order.delivery_proof"
        class="detail-card pod-card"
      >
        <span class="eyebrow">DELIVERY CONFIRMATION</span>
        <h3>Proof of delivery</h3>

        <div class="pod-status">
          <span class="pod-check">✓</span>
          <strong>Delivery successfully completed</strong>
        </div>

        <div class="pod-image-wrapper">
          <a
            :href="order.delivery_proof.photo_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <img
              :src="order.delivery_proof.photo_url"
              alt="Delivery proof photo"
              class="pod-image"
            />
          </a>
          <p class="pod-image-caption">
            Tap image to view full size
          </p>
        </div>

        <div class="pod-info">
          <div class="pod-info-row">
            <span>Received by</span>
            <strong>
              {{ order.delivery_proof.recipient_name }}
            </strong>
          </div>

          <div class="pod-info-row">
            <span>Uploaded by</span>
            <strong>
              {{ order.delivery_proof.uploaded_by?.name || 'Courier' }}
            </strong>
          </div>

          <div class="pod-info-row">
            <span>Upload time</span>
            <strong>
              {{ formatDate(order.delivery_proof.created_at) }}
            </strong>
          </div>
        </div>

        <div
          v-if="order.delivery_proof.notes"
          class="pod-notes"
        >
          <span class="eyebrow">COURIER NOTES</span>
          <p>{{ order.delivery_proof.notes }}</p>
        </div>
      </section>

      <section class="detail-card">
        <h3>Order history</h3>

        <div
          v-for="history in order.status_histories || []"
          :key="history.id"
          class="history-item"
        >
          <span class="history-dot"></span>

          <div>
            <strong>
              {{ history.new_status.replace('_', ' ') }}
            </strong>
            <p>{{ history.note || 'Status updated' }}</p>
            <small>{{ formatDate(history.created_at) }}</small>
          </div>
        </div>

        <p v-if="!order.status_histories?.length">
          No order history available.
        </p>
      </section>
    </template>

    <div v-if="cancelDialog" class="modal-overlay">
      <div class="cancel-modal">
        <span class="eyebrow">ORDER CANCELLATION</span>

        <h3>Cancel this delivery?</h3>

        <p>
          Are you sure you want to cancel
          <strong>{{ order?.order_number }}</strong>?
          This action cannot be undone.
        </p>

        <div class="form-field">
          <label>Reason for cancellation</label>

          <textarea
            v-model="cancelReason"
            maxlength="1000"
            placeholder="Tell us why you want to cancel..."
            rows="4"
          />

          <small>{{ cancelReason.length }}/1000 characters</small>
        </div>

        <p v-if="cancelError" class="form-error">
          {{ cancelError }}
        </p>

        <div class="modal-actions">
          <button
            class="modal-back-btn"
            :disabled="cancelLoading"
            @click="cancelDialog = false"
          >
            Keep order
          </button>

          <button
            class="modal-confirm-btn"
            :disabled="cancelLoading"
            @click="submitCancellation"
          >
            {{ cancelLoading ? 'Cancelling...' : 'Confirm cancellation' }}
          </button>
        </div>
      </div>
    </div>

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>
  </main>
</template>

