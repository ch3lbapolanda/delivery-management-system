
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
  customer?: {
    id: number
    name: string
    email: string
  }
}

interface OrdersResponse {
  message: string
  data: {
    data: CourierOrder[]
    current_page: number
    last_page: number
    total: number
  }
}

const route = useRoute()
const api = useApi()
const { user, logout } = useAuth()

const orders = ref<CourierOrder[]>([])
const loading = ref(true)
const error = ref('')

const currentPage = ref(1)
const pageSize = 10

const currentView = computed(() => {
  const view = route.query.view

  if (view === 'active') return 'active'
  if (view === 'completed') return 'completed'

  return 'home'
})

const pageTitle = computed(() => {
  if (currentView.value === 'active') return 'Active Deliveries'
  if (currentView.value === 'completed') return 'Completed Deliveries'
  return 'My Deliveries'
})

// Ambil semua order dari seluruh halaman API.
const fetchOrders = async () => {
  loading.value = true
  error.value = ''

  try {
    const firstResponse = await api<OrdersResponse>(
      '/courier/orders?page=1'
    )

    const allOrders = [...firstResponse.data.data]
    const lastPage = firstResponse.data.last_page

    for (let page = 2; page <= lastPage; page++) {
      const response = await api<OrdersResponse>(
        `/courier/orders?page=${page}`
      )

      allOrders.push(...response.data.data)
    }

    orders.value = allOrders
    currentPage.value = 1
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil daftar pengiriman.'
  } finally {
    loading.value = false
  }
}

// Filter berdasarkan halaman yang sedang dibuka.
const filteredOrders = computed(() => {
  if (currentView.value === 'active') {
    return orders.value.filter(order =>
      ['assigned', 'picked_up', 'in_transit'].includes(order.status)
    )
  }

  if (currentView.value === 'completed') {
    return orders.value.filter(order =>
      order.status === 'delivered'
    )
  }

  return orders.value
})

// Pagination frontend.
const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredOrders.value.length / pageSize))
)

const paginatedOrders = computed(() => {
  const start = (currentPage.value - 1) * pageSize

  return filteredOrders.value.slice(
    start,
    start + pageSize
  )
})

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

// Reset halaman ketika berpindah menu.
watch(currentView, () => {
  currentPage.value = 1
})

const openOrder = (orderId: number) => {
  navigateTo(`/courier/orders/${orderId}`)
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

// Statistik menghitung seluruh data.
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
  await navigateTo('/login')
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
          <h2>{{ pageTitle }}</h2>
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
        v-else-if="filteredOrders.length === 0"
        class="courier-state"
      >
        <template v-if="currentView === 'active'">
          Tidak ada pengiriman aktif saat ini.
        </template>

        <template v-else-if="currentView === 'completed'">
          Belum ada pengiriman yang selesai.
        </template>

        <template v-else>
          Belum ada tugas pengiriman.
        </template>
      </div>

      <div v-else class="courier-order-list">

        <article
          v-for="order in paginatedOrders"
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

      <!-- Pagination -->
      <div
        v-if="!loading && !error && totalPages > 1"
        class="courier-pagination"
      >
        <button
          :disabled="currentPage <= 1"
          @click="previousPage"
        >
          Previous
        </button>

        <span>
          {{ currentPage }} / {{ totalPages }}
        </span>

        <button
          :disabled="currentPage >= totalPages"
          @click="nextPage"
        >
          Next
        </button>
      </div>

    </section>

  </main>
</template>
