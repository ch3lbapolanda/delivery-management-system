
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Person {
  id: number
  name: string
  email?: string
}

interface StatusHistory {
  id: number
  old_status: string | null
  new_status: string
  note: string | null
  created_at: string
  changed_by: Person | null
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
  uploaded_by: Person | null
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
  sender_name: string
  sender_phone: string
  pickup_address: string
  recipient_name: string
  recipient_phone: string
  delivery_address: string
  eta?: OrderEta
  scheduled_pickup_at: string | null
  notes: string | null
  price: string | number | null
  pricing_details?: PricingDetails | null
  customer: Person | null
  courier: Person | null
  status_histories: StatusHistory[]
  delivery_proof?: DeliveryProof | null
}

interface OrderResponse {
  message: string
  data: Order
  eta?: OrderEta
}

interface Courier {
  id: number
  name: string
  email: string
}

interface CourierResponse {
  message: string
  data: Courier[]
}

const route = useRoute()
const api = useApi()

const nuxtApp = useNuxtApp() as any
const echo = nuxtApp.$echo
const reverbStatus = nuxtApp.$reverbStatus

interface TrackingLocation {
  latitude: number
  longitude: number
  accuracy: number | null
  speed: number | null
  heading: number | null
  recorded_at: string
}

interface CourierLocationEvent extends TrackingLocation {
  order_id: number
}

const trackingLocation = ref<TrackingLocation | null>(null)
const trackingMessage = ref('Menunggu lokasi kurir...')
let trackingTimer: ReturnType<typeof setInterval> | null = null
let trackingChannelName: string | null = null

const reverbStatusLabel = computed(() => {
  switch (reverbStatus.value) {
    case 'connected':
      return 'LIVE CONNECTION'
    case 'connecting':
    case 'initialized':
      return 'CONNECTING'
    case 'unavailable':
    case 'failed':
      return 'CONNECTION ERROR'
    default:
      return 'RECONNECTING'
  }
})

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')

const couriers = ref<Courier[]>([])
const selectedCourierId = ref<number | null>(null)

const showAssignModal = ref(false)
const loadingCouriers = ref(false)
const assigning = ref(false)
const assignmentError = ref('')

const fetchOrder = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api<OrderResponse>(
      `/dispatcher/orders/${route.params.id}`
    )

    order.value = {
      ...response.data,
      eta: response.eta
    }
    startTracking()
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil detail pesanan.'
  } finally {
    loading.value = false
  }
}

const fetchTracking = async () => {
  try {
    const response = await api<{
      data: TrackingLocation | null
      message: string
    }>(`/dispatcher/orders/${route.params.id}/tracking`)

    trackingLocation.value = response.data
    trackingMessage.value = response.message
  } catch {
    trackingMessage.value = 'Gagal mengambil lokasi kurir.'
  }
}

const startTracking = () => {
  stopTracking()
  trackingLocation.value = null

  if (order.value?.status !== 'in_transit') return

  fetchTracking()

  // Fallback polling setiap 30 detik
  trackingTimer = setInterval(fetchTracking, 30000)

  if (echo) {
    trackingChannelName = `orders.${order.value.id}`

    echo.private(trackingChannelName)
      .listen(
        '.courier.location.updated',
        (event: CourierLocationEvent) => {
          if (event.order_id !== order.value?.id) return

          if (
    trackingLocation.value &&
    Date.parse(event.recorded_at) <=
    Date.parse(trackingLocation.value.recorded_at)
) {
    return
}
          
          trackingLocation.value = {
            latitude: event.latitude,
            longitude: event.longitude,
            accuracy: event.accuracy,
            speed: event.speed,
            heading: event.heading,
            recorded_at: event.recorded_at
          }

          trackingMessage.value = 'Lokasi kurir diperbarui.'
        }
      )
  }
}

const stopTracking = () => {
  if (trackingTimer !== null) {
    clearInterval(trackingTimer)
    trackingTimer = null
  }

  if (trackingChannelName && echo) {
    echo.leave(trackingChannelName)
    trackingChannelName = null
  }
}

const fetchCouriers = async () => {
  loadingCouriers.value = true
  assignmentError.value = ''

  try {
    const response = await api<CourierResponse>(
      '/dispatcher/couriers'
    )

    couriers.value = response.data
  } catch (err: any) {
    assignmentError.value =
      err?.data?.message || 'Gagal mengambil daftar kurir.'
  } finally {
    loadingCouriers.value = false
  }
}

const openAssignModal = async () => {
  selectedCourierId.value = null
  assignmentError.value = ''
  couriers.value = []
  showAssignModal.value = true

  await fetchCouriers()
}

const assignCourier = async () => {
  if (!selectedCourierId.value || !order.value) return

  assigning.value = true
  assignmentError.value = ''

  try {
    await api(
      `/dispatcher/orders/${order.value.id}/assign`,
      {
        method: 'POST',
        body: {
          courier_id: selectedCourierId.value
        }
      }
    )

    showAssignModal.value = false

    await fetchOrder()
  } catch (err: any) {
    assignmentError.value =
      err?.data?.message || 'Gagal menugaskan kurir.'
  } finally {
    assigning.value = false
  }
}

const formatCurrency = (value: string | number | null) => {
  if (value === null || value === undefined) {
    return 'Belum ditentukan'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(Number(value))
}
  
const formatDate = (date: string | null) => {
  if (!date) return 'Tidak dijadwalkan'

  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const statusLabel = (status: string) => {
  const labels: Record<string, string> = {
    pending: 'Menunggu',
    assigned: 'Kurir Ditugaskan',
    picked_up: 'Sudah Dijemput',
    in_transit: 'Dalam Perjalanan',
    delivered: 'Terkirim',
    cancelled: 'Dibatalkan'
  }

  return labels[status] || status
}

const statusClass = (status: string) => {
  return `status-${status}`
}

const assignmentHistory = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'assigned')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0] || null
})

const actualDeliveredAt = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'delivered')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0]?.created_at || null
})

onMounted(() => {
  fetchOrder()
})

onBeforeUnmount(() => {
  stopTracking()
})
</script>

<template>
  <main class="dispatcher-detail">

    <button
      class="back-button"
      @click="navigateTo('/dispatcher')"
    >
      ← Kembali ke Dashboard
    </button>

    <div v-if="loading" class="detail-state">
      Memuat detail pesanan...
    </div>

    <div v-else-if="error" class="detail-state error">
      {{ error }}

      <button @click="fetchOrder">
        Coba Lagi
      </button>
    </div>

    <template v-else-if="order">

      <header class="detail-header">
        <div>
          <span class="eyebrow">DISPATCHER PORTAL</span>
          <h1>Order Detail</h1>
          <p>Informasi lengkap pengiriman</p>
        </div>

        <span
          class="status-badge"
          :class="statusClass(order.status)"
        >
          {{ statusLabel(order.status) }}
        </span>
      </header>

      <section class="detail-card order-summary">
        <span class="card-label">ORDER NUMBER</span>
        <h2>{{ order.order_number }}</h2>

        <div class="summary-meta">
          <span>
            {{ order.delivery_type === 'instant'
              ? 'Instant Delivery'
              : 'Scheduled Delivery' }}
          </span>
          <span>Order ID: {{ order.id }}</span>
        </div>
      </section>

      
      <!-- DELIVERY PRICE -->
<section class="detail-card dispatcher-price-card">
  <span class="eyebrow">PAYMENT INFORMATION</span>

  <h3>Delivery Price</h3>

  <div class="info-row">
    <span>Service Type</span>
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
    <div class="info-row">
      <span>Delivery Distance</span>
      <strong>{{ order.pricing_details.distance_km }} km</strong>
    </div>

    <div class="info-row">
      <span>Base Price</span>
      <strong>{{ formatCurrency(order.pricing_details.base_price) }}</strong>
    </div>

    <div class="info-row">
      <span>Price per KM</span>
      <strong>{{ formatCurrency(order.pricing_details.price_per_km) }}</strong>
    </div>

    <div class="info-row">
      <span>Distance Fee</span>
      <strong>{{ formatCurrency(order.pricing_details.distance_fee) }}</strong>
    </div>

    <div class="info-row">
      <span>Additional Fee</span>
      <strong>{{ formatCurrency(order.pricing_details.additional_fee) }}</strong>
    </div>

    <div class="info-row">
      <span>Minimum Price</span>
      <strong>{{ formatCurrency(order.pricing_details.minimum_price) }}</strong>
    </div>
  </template>

  <div class="dispatcher-price-total">
    <span>Total Delivery Price</span>
    <strong>
      {{ formatCurrency(order.pricing_details?.total_price ?? order.price) }}
    </strong>
  </div>

  <p class="dispatcher-price-note">
    {{
      order.pricing_details
        ? 'Historical pricing snapshot recorded when this order was created.'
        : 'Historical pricing details are unavailable for this order.'
    }}
  </p>
</section>

    <section class="detail-card eta-card">
  <span class="eyebrow">
    DELIVERY ESTIMATE
  </span>

  <template v-if="order.status === 'delivered'">
    <h3>Delivery Completed</h3>

    <div class="eta-highlight">
      DELIVERED
    </div>

    <div class="eta-info">
      <div class="eta-info-row">
        <span>Actual Delivery Time</span>
        <strong v-if="actualDeliveredAt">
          {{ formatDate(actualDeliveredAt) }}
        </strong>
        <strong v-else>
          Waktu pengiriman tidak tersedia
        </strong>
      </div>
    </div>

    <p class="eta-disclaimer">
      Pesanan telah berhasil diterima dan diselesaikan.
    </p>
  </template>

  <template v-else-if="order.eta?.available">
    <h3>Estimated Arrival</h3>

    <div class="eta-highlight">
      {{ order.eta.duration_minutes }} minutes
    </div>

    <div class="eta-info">
      <div class="eta-info-row">
        <span>Estimated Distance</span>
        <strong>{{ order.eta.distance_km }} km</strong>
      </div>

      <div class="eta-info-row">
        <span>Travel Duration</span>
        <strong>{{ order.eta.duration_minutes }} minutes</strong>
      </div>

      <div class="eta-info-row">
        <span>Estimated Arrival</span>
        <strong>
          {{ formatDate(order.eta.estimated_arrival_at) }}
        </strong>
      </div>
    </div>

    <p class="eta-disclaimer">
      {{ order.eta.message }}
    </p>
  </template>

  <template v-else>
    <h3>Estimated Arrival</h3>

    <p class="eta-unavailable">
      {{ order.eta?.message || 'ETA belum tersedia.' }}
    </p>

    <p class="eta-disclaimer">
      Estimasi tersedia setelah kurir memulai perjalanan
      dan koordinat pickup serta tujuan tersedia.
    </p>
  </template>
</section>

      <section class="detail-card">
        <h3>Customer Information</h3>

        <div class="info-row">
          <span>Nama Customer</span>
          <strong>{{ order.customer?.name || '-' }}</strong>
        </div>

        <div class="info-row">
          <span>Email</span>
          <strong>{{ order.customer?.email || '-' }}</strong>
        </div>
      </section>

      <section class="detail-card">
        <h3>Pickup Information</h3>

        <div class="info-block">
          <span>Sender</span>
          <strong>{{ order.sender_name }}</strong>
          <p>{{ order.sender_phone }}</p>
        </div>

        <div class="address-box">
          {{ order.pickup_address }}
        </div>
      </section>

      <section class="detail-card">
        <h3>Delivery Information</h3>

        <div class="info-block">
          <span>Recipient</span>
          <strong>{{ order.recipient_name }}</strong>
          <p>{{ order.recipient_phone }}</p>
        </div>

        <div class="address-box">
          {{ order.delivery_address }}
        </div>
      </section>

      <section class="detail-card">
        <div class="section-heading">
          <h3>Courier Assignment</h3>

          <button
            v-if="order.status === 'pending'"
            class="assign-button"
            @click="openAssignModal"
          >
            + Assign Courier
          </button>
        </div>

        <div v-if="order.courier" class="courier-box">
          <div class="courier-avatar">
            {{ order.courier.name.charAt(0).toUpperCase() }}
          </div>

          <div>
            <strong>{{ order.courier.name }}</strong>
            <p>Assigned Courier</p>
            <p v-if="order.courier.email">
              {{ order.courier.email }}
            </p>
          </div>
        </div>

        <div v-else class="unassigned-box">
          Belum ada kurir yang ditugaskan.
        </div>

        <div
          v-if="order.courier && assignmentHistory"
          class="assignment-details"
        >
          <div class="assignment-details-heading">
            Assignment Details
          </div>

          <div class="info-row">
            <span>Assigned By</span>
            <strong>
              {{ assignmentHistory.changed_by?.name || 'System' }}
            </strong>
          </div>

          <div class="info-row">
            <span>Assignment Time</span>
            <strong>
              {{ formatDate(assignmentHistory.created_at) }}
            </strong>
          </div>

          <div class="notes-box">
            <span>Assignment Note</span>
            <p>
              {{ assignmentHistory.note || 'Tidak ada catatan.' }}
            </p>
          </div>
        </div>
      </section>

      <!-- DISPATCHER LIVE TRACKING -->
<section
  v-if="order.status === 'in_transit'"
  class="detail-card tracking-card"
>
  <span class="eyebrow">LIVE TRACKING</span>
  <h3>Courier Location</h3>

  <div
    class="tracking-connection"
    :class="`connection-${reverbStatus}`"
  >
    <span class="connection-dot"></span>
    {{ reverbStatusLabel }}
  </div>

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
      Last update:
      {{ formatDate(trackingLocation.recorded_at) }}
    </p>
  </template>

  <p v-else class="eta-unavailable">
    {{ trackingMessage }}
  </p>
</section>

      <section class="detail-card">
        <h3>Delivery Schedule</h3>

        <div class="info-row">
          <span>Pickup Schedule</span>
          <strong>{{ formatDate(order.scheduled_pickup_at) }}</strong>
        </div>

        

        <div v-if="order.notes" class="notes-box">
          <span>Customer Notes</span>
          <p>{{ order.notes }}</p>
        </div>
      </section>

      <!-- PROOF OF DELIVERY -->
      <section
        v-if="order.delivery_proof"
        class="detail-card dispatcher-pod-card"
      >
        <span class="eyebrow">DELIVERY CONFIRMATION</span>
        <h3>Proof of Delivery</h3>

        <div class="dispatcher-pod-status">
          <span class="dispatcher-pod-check">✓</span>
          <strong>Delivery successfully completed</strong>
        </div>

        <div class="dispatcher-pod-image-wrapper">
          <a
            :href="order.delivery_proof.photo_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <img
              :src="order.delivery_proof.photo_url"
              alt="Delivery proof photo"
              class="dispatcher-pod-image"
            />
          </a>

          <p class="dispatcher-pod-caption">
            Tap image to view full size
          </p>
        </div>

        <div class="dispatcher-pod-info">
          <div class="dispatcher-pod-info-row">
            <span>Received By</span>
            <strong>
              {{ order.delivery_proof.recipient_name }}
            </strong>
          </div>

          <div class="dispatcher-pod-info-row">
            <span>Uploaded By</span>
            <strong>
              {{ order.delivery_proof.uploaded_by?.name || 'Courier' }}
            </strong>
          </div>

          <div class="dispatcher-pod-info-row">
            <span>Upload Time</span>
            <strong>
              {{ formatDate(order.delivery_proof.created_at) }}
            </strong>
          </div>
        </div>

        <div
          v-if="order.delivery_proof.notes"
          class="dispatcher-pod-notes"
        >
          <span class="eyebrow">COURIER NOTES</span>
          <p>{{ order.delivery_proof.notes }}</p>
        </div>
      </section>

      <section class="detail-card">
        <h3>Order Status History</h3>

        <div
          v-if="order.status_histories?.length"
          class="history-list"
        >
          <div
            v-for="history in order.status_histories"
            :key="history.id"
            class="history-item"
          >
            <div class="history-dot"></div>

            <div class="history-content">
              <strong>{{ statusLabel(history.new_status) }}</strong>

              <p>
                {{ history.note || 'Status pesanan diperbarui.' }}
              </p>

              <span>
                {{ history.changed_by?.name || 'System' }}
                · {{ formatDate(history.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <p v-else class="empty-history">
          Belum ada riwayat status.
        </p>
      </section>

    </template>

    <!-- COURIER ASSIGNMENT MODAL -->
    <div
      v-if="showAssignModal"
      class="modal-overlay"
      @click.self="showAssignModal = false"
    >
      <div class="assign-modal">

        <div class="modal-header">
          <div>
            <span class="eyebrow">ORDER ASSIGNMENT</span>
            <h2>Select Courier</h2>
          </div>

          <button
            class="modal-close"
            :disabled="assigning"
            @click="showAssignModal = false"
          >
            ×
          </button>
        </div>

        <p class="modal-description">
          Pilih kurir untuk menangani pengiriman ini.
        </p>

        <div v-if="loadingCouriers" class="modal-state">
          Memuat daftar kurir...
        </div>

        <div
          v-else-if="couriers.length === 0 && !assignmentError"
          class="modal-state"
        >
          Belum ada akun kurir.
        </div>

        <div v-else class="courier-options">
          <button
            v-for="courier in couriers"
            :key="courier.id"
            class="courier-option"
            :class="{
              selected: selectedCourierId === courier.id
            }"
            @click="selectedCourierId = courier.id"
          >
            <div class="courier-avatar">
              {{ courier.name.charAt(0).toUpperCase() }}
            </div>

            <div class="courier-option-info">
              <strong>{{ courier.name }}</strong>
              <span>{{ courier.email }}</span>
            </div>

            <span class="selection-indicator">
              {{ selectedCourierId === courier.id ? '✓' : '' }}
            </span>
          </button>
        </div>

        <p v-if="assignmentError" class="assignment-error">
          {{ assignmentError }}
        </p>

        <div class="modal-actions">
          <button
            class="cancel-button"
            :disabled="assigning"
            @click="showAssignModal = false"
          >
            Cancel
          </button>

          <button
            class="confirm-button"
            :disabled="
              !selectedCourierId ||
              assigning ||
              loadingCouriers
            "
            @click="assignCourier"
          >
            {{ assigning ? 'Assigning...' : 'Confirm Assignment' }}
          </button>
        </div>

      </div>
    </div>

  </main>
</template>

