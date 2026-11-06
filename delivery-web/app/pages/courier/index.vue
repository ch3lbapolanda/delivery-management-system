
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface CourierOrder {
  id: number
  order_number: string
  status: string
  delivery_type: string
  sender_name: string
  pickup_address: string
  recipient_name: string
  delivery_address: string
  scheduled_pickup_at: string | null
  customer: {
    id: number
    name: string
    email: string
  } | null
}

const openOrder = (orderId: number) => {
  navigateTo(`/courier/orders/${orderId}`)
}

interface OrdersResponse {
  message: string
  data: {
    data: CourierOrder[]
    total: number
  }
}

const api = useApi()
const { user, logout } = useAuth()

const orders = ref<CourierOrder[]>([])
const loading = ref(true)
const error = ref('')

const fetchOrders = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api<OrdersResponse>(
      '/courier/orders'
    )

    orders.value = response.data.data
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil daftar pengiriman.'
  } finally {
    loading.value = false
  }
}

const statusLabel = (status: string) => {
  const labels: Record<string, string> = {
    assigned: 'Tugas Baru',
    picked_up: 'Sudah Dijemput',
    in_transit: 'Dalam Perjalanan',
    delivered: 'Selesai',
    cancelled: 'Dibatalkan'
  }

  return labels[status] || status
}

const statusClass = (status: string) => {
  return `status-${status}`
}

const activeOrders = computed(() =>
  orders.value.filter(order =>
    ['assigned', 'picked_up', 'in_transit'].includes(order.status)
  ).length
)

const completedOrders = computed(() =>
  orders.value.filter(order =>
    order.status === 'delivered'
  ).length
)

const formatDate = (date: string | null) => {
  if (!date) return 'Pengiriman instan'

  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const handleLogout = async () => {
  await logout()
  await navigateTo('/')
}

onMounted(fetchOrders)
</script>

<template>
  <main class="courier-dashboard">

    <header class="courier-header">
      <div>
        <span class="courier-eyebrow">KURIR MALAM</span>
        <h1>Courier Portal</h1>
        <p>Selamat bekerja, {{ user?.name || 'Courier' }}</p>
      </div>

      <button
        class="courier-logout"
        @click="handleLogout"
      >
        Logout
      </button>
    </header>

    <section class="courier-welcome">
      <span>DELIVERY OPERATIONS</span>
      <h2>Ready for your next delivery?</h2>
      <p>
        Pantau tugas pengiriman dan selesaikan setiap
        perjalanan dengan aman.
      </p>
    </section>

    <section class="courier-stats">
      <div class="courier-stat-card">
        <span>Active Orders</span>
        <strong>{{ activeOrders }}</strong>
      </div>

      <div class="courier-stat-card">
        <span>Completed</span>
        <strong>{{ completedOrders }}</strong>
      </div>

      <div class="courier-stat-card">
        <span>Total Orders</span>
        <strong>{{ orders.length }}</strong>
      </div>
    </section>

    <section class="courier-order-section">
      <div class="courier-section-heading">
        <div>
          <span class="courier-eyebrow">YOUR WORKSPACE</span>
          <h2>My Deliveries</h2>
        </div>

        <button
          class="courier-refresh"
          @click="fetchOrders"
          :disabled="loading"
        >
          ↻ Refresh
        </button>
      </div>

      <div v-if="loading" class="courier-state">
        Memuat daftar pengiriman...
      </div>

      <div v-else-if="error" class="courier-state courier-error">
        {{ error }}

        <button @click="fetchOrders">
          Coba Lagi
        </button>
      </div>

      <div
        v-else-if="orders.length === 0"
        class="courier-state"
      >
        Belum ada tugas pengiriman.
      </div>

      <div v-else class="courier-order-list">

      <article
  v-for="order in orders"
  :key="order.id"
  class="courier-order-card"
  @click="openOrder(order.id)"
>
          <div class="courier-order-top">
            <div>
              <span class="courier-order-label">
                ORDER NUMBER
              </span>

              <h3>{{ order.order_number }}</h3>
            </div>

            <span
              class="courier-status"
              :class="statusClass(order.status)"
            >
              {{ statusLabel(order.status) }}
            </span>
          </div>

          <div class="courier-route">

            <div class="courier-route-item">
              <span class="route-marker pickup-marker"></span>

              <div>
                <span>Pickup</span>
                <strong>{{ order.sender_name }}</strong>
                <p>{{ order.pickup_address }}</p>
              </div>
            </div>

            <div class="courier-route-item">
              <span class="route-marker delivery-marker"></span>

              <div>
                <span>Destination</span>
                <strong>{{ order.recipient_name }}</strong>
                <p>{{ order.delivery_address }}</p>
              </div>
            </div>

          </div>

          <div class="courier-order-footer">
            <span>{{ formatDate(order.scheduled_pickup_at) }}</span>
            <span>
              {{ order.delivery_type === 'instant'
                ? 'Instant'
                : 'Scheduled' }}
            </span>
          </div>

        </article>

      </div>
    </section>

  </main>
</template>
