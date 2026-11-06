
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
  status_histories: StatusHistory[]
}

interface OrderResponse {
  message: string
  data: Order
}

const route = useRoute()
const api = useApi()

const order = ref<Order | null>(null)
const loading = ref(true)
const updating = ref(false)
const error = ref('')
const actionMessage = ref('')

const fetchOrder = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api<OrderResponse>(
      `/courier/orders/${route.params.id}`
    )

    order.value = response.data
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil detail pengiriman.'
  } finally {
    loading.value = false
  }
}

const nextStatus: Record<string, string> = {
  assigned: 'picked_up',
  picked_up: 'in_transit',
  in_transit: 'delivered'
}

const actionLabels: Record<string, string> = {
  assigned: 'Mark as Picked Up',
  picked_up: 'Start Delivery',
  in_transit: 'Mark as Delivered'
}

const statusLabels: Record<string, string> = {
  assigned: 'Kurir Ditugaskan',
  picked_up: 'Sudah Dijemput',
  in_transit: 'Dalam Perjalanan',
  delivered: 'Selesai',
  cancelled: 'Dibatalkan',
  pending: 'Menunggu'
}

const statusLabel = (status: string) => {
  return statusLabels[status] || status
}

const statusClass = (status: string) => {
  return `status-${status}`
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

const updateStatus = async () => {
  if (!order.value || updating.value) return

  const targetStatus = nextStatus[order.value.status]

  if (!targetStatus) return

  updating.value = true
  error.value = ''
  actionMessage.value = ''

  const notes: Record<string, string> = {
    picked_up: 'Barang sudah dijemput kurir.',
    in_transit: 'Barang sedang dalam perjalanan.',
    delivered: 'Barang sudah diterima penerima.'
  }

  try {
    await api(
      `/courier/orders/${order.value.id}/status`,
      {
        method: 'PATCH',
        body: {
          status: targetStatus,
          note: notes[targetStatus]
        }
      }
    )

    actionMessage.value =
      `Status berhasil diperbarui menjadi ${statusLabel(targetStatus)}.`

    await fetchOrder()
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal memperbarui status pengiriman.'
  } finally {
    updating.value = false
  }
}

const actionButtonLabel = computed(() => {
  if (!order.value) return ''

  return actionLabels[order.value.status] || ''
})

onMounted(fetchOrder)
</script>

<template>
  <main class="courier-detail">

    <button
      class="courier-back-button"
      @click="navigateTo('/courier')"
    >
      ← Back to Dashboard
    </button>

    <div v-if="loading" class="courier-detail-state">
      Memuat detail pengiriman...
    </div>

    <div v-else-if="error && !order" class="courier-detail-state error">
      {{ error }}

      <button @click="fetchOrder">
        Coba Lagi
      </button>
    </div>

    <template v-else-if="order">

      <header class="courier-detail-header">
        <div>
          <span class="courier-detail-eyebrow">
            COURIER WORKSPACE
          </span>

          <h1>Delivery Detail</h1>
          <p>Informasi tugas pengiriman</p>
        </div>

        <span
          class="courier-detail-status"
          :class="statusClass(order.status)"
        >
          {{ statusLabel(order.status) }}
        </span>
      </header>

      <section class="courier-detail-card order-highlight">
        <span>ORDER NUMBER</span>
        <h2>{{ order.order_number }}</h2>

        <div class="courier-order-meta">
          <span>Order ID: {{ order.id }}</span>
          <span>
            {{ order.delivery_type === 'instant'
              ? 'Instant Delivery'
              : 'Scheduled Delivery' }}
          </span>
        </div>
      </section>

      <section class="courier-detail-card">
        <h3>Pickup Information</h3>

        <div class="courier-person">
          <span>Sender</span>
          <strong>{{ order.sender_name }}</strong>
          <p>{{ order.sender_phone }}</p>
        </div>

        <div class="courier-address">
          {{ order.pickup_address }}
        </div>
      </section>

      <section class="courier-detail-card">
        <h3>Delivery Information</h3>

        <div class="courier-person">
          <span>Recipient</span>
          <strong>{{ order.recipient_name }}</strong>
          <p>{{ order.recipient_phone }}</p>
        </div>

        <div class="courier-address">
          {{ order.delivery_address }}
        </div>
      </section>

      <section class="courier-detail-card">
        <h3>Delivery Schedule</h3>

        <div class="courier-info-row">
          <span>Pickup Schedule</span>
          <strong>{{ formatDate(order.scheduled_pickup_at) }}</strong>
        </div>

        <div class="courier-info-row">
          <span>Customer</span>
          <strong>{{ order.customer?.name || '-' }}</strong>
        </div>

        <div v-if="order.notes" class="courier-notes">
          <span>Customer Notes</span>
          <p>{{ order.notes }}</p>
        </div>
      </section>

      <section class="courier-action-card">
        <span class="courier-detail-eyebrow">
          DELIVERY PROGRESS
        </span>

        <h3>Update Delivery Status</h3>

        <p v-if="order.status === 'delivered'">
          Pengiriman telah selesai. Terima kasih atas pekerjaanmu!
        </p>

        <p v-else-if="order.status === 'cancelled'">
          Pesanan ini telah dibatalkan.
        </p>

        <p v-else>
          Perbarui status setelah setiap tahap pengiriman benar-benar selesai.
        </p>

        <button
          v-if="actionButtonLabel"
          class="courier-action-button"
          :disabled="updating"
          @click="updateStatus"
        >
          {{ updating ? 'Updating...' : actionButtonLabel }}
        </button>

        <div
          v-if="actionMessage"
          class="courier-success-message"
        >
          {{ actionMessage }}
        </div>

        <div
          v-if="error && order"
          class="courier-action-error"
        >
          {{ error }}
        </div>
      </section>

      <section class="courier-detail-card">
        <h3>Delivery History</h3>

        <div
          v-if="order.status_histories?.length"
          class="courier-history-list"
        >
          <div
            v-for="history in order.status_histories"
            :key="history.id"
            class="courier-history-item"
          >
            <div class="courier-history-dot"></div>

            <div class="courier-history-content">
              <strong>
                {{ statusLabel(history.new_status) }}
              </strong>

              <p>
                {{ history.note || 'Status diperbarui.' }}
              </p>

              <span>
                {{ history.changed_by?.name || 'System' }}
                · {{ formatDate(history.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <p v-else class="courier-empty-history">
          Belum ada riwayat pengiriman.
        </p>
      </section>

    </template>

  </main>
</template>
