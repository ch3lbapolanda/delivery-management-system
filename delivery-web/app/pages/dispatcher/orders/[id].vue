
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
  scheduled_pickup_at: string | null
  notes: string | null
  price: string | number | null
  customer: Person | null
  courier: Person | null
  status_histories: StatusHistory[]
}

interface OrderResponse {
  message: string
  data: Order
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

    order.value = response.data
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil detail pesanan.'
  } finally {
    loading.value = false
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

// Ambil riwayat assignment terbaru.
const assignmentHistory = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'assigned')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0] || null
})

onMounted(fetchOrder)
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

      <section class="detail-card">
        <h3>Delivery Schedule</h3>

        <div class="info-row">
          <span>Pickup Schedule</span>
          <strong>{{ formatDate(order.scheduled_pickup_at) }}</strong>
        </div>

        <div class="info-row">
          <span>Delivery Price</span>
          <strong>
            {{ order.price !== null
              ? `Rp ${Number(order.price).toLocaleString('id-ID')}`
              : 'Belum ditentukan' }}
          </strong>
        </div>

        <div v-if="order.notes" class="notes-box">
          <span>Customer Notes</span>
          <p>{{ order.notes }}</p>
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



