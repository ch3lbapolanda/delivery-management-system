
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Order {
  id: number
  order_number: string
  delivery_type: string
  status: string
  sender_name: string
  recipient_name: string
  pickup_address: string
  delivery_address: string
  created_at: string
}

interface OrderResponse {
  message: string
  data: {
    data: Order[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

const { user, logout } = useAuth()
const api = useApi()

const orders = ref<Order[]>([])
const loading = ref(false)
const errorMessage = ref('')

const totalOrders = computed(() => orders.value.length)

const inTransitOrders = computed(() =>
  orders.value.filter(order =>
    ['picked_up', 'in_transit'].includes(order.status)
  ).length
)

const cancelledOrders = computed(() =>
  orders.value.filter(order =>
    order.status === 'cancelled'
  ).length
)

const statusLabel = (status: string) => {
  const labels: Record<string, string> = {
    pending: 'Pending',
    assigned: 'Assigned',
    picked_up: 'Picked up',
    in_transit: 'In transit',
    delivered: 'Delivered',
    cancelled: 'Cancelled'
  }

  return labels[status] || status
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const fetchOrders = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<OrderResponse>('/orders')

    orders.value = response.data.data || []
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to load orders.'
  } finally {
    loading.value = false
  }
}

const goToCreate = () => {
  navigateTo('/customer/create')
}

const goToOrders = () => {
  navigateTo('/customer/orders')
}

const openOrder = async (orderId: number) => {
  await navigateTo(`/customer/orders/${orderId}`)
}

const handleLogout = async () => {
  await logout()
  await navigateTo('/login')
}

onMounted(fetchOrders)
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
        @click="handleLogout"
      >
        Sign out ↗
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">CUSTOMER PORTAL</span>

      <h2>
        Good evening,<br />
        <span>{{ user?.name || 'Customer' }}.</span>
      </h2>

      <p>Manage and track your deliveries in one place.</p>
    </section>

    <section class="stats">

      <article class="stat-card">
        <span class="stat-icon">▣</span>
        <p>TOTAL ORDERS</p>
        <h3>{{ totalOrders }}</h3>
        <small>Your delivery history</small>
      </article>

      <article class="stat-card">
        <span class="stat-icon">↗</span>
        <p>IN TRANSIT</p>
        <h3>{{ inTransitOrders }}</h3>
        <small>Currently on the way</small>
      </article>

    </section>

    <section class="actions">

      <div class="section-heading">
        <h3>Quick actions</h3>
        <span>GET STARTED</span>
      </div>

      <button
        class="action-card"
        @click="goToCreate"
      >
        <span class="action-icon">＋</span>

        <span>
          <strong>Create delivery</strong>
          <small>Send a package to its destination</small>
        </span>

        <span class="arrow">→</span>
      </button>

      <button
        class="action-card"
        @click="goToOrders"
      >
        <span class="action-icon">⌖</span>

        <span>
          <strong>Track delivery</strong>
          <small>Follow your package journey</small>
        </span>

        <span class="arrow">→</span>
      </button>

    </section>

    <section class="recent">

      <div class="section-heading">
        <h3>Recent deliveries</h3>

        <button
          class="admin-text-button"
          @click="goToOrders"
        >
          View all →
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        <h4>Loading deliveries...</h4>
      </div>

      <div v-else-if="errorMessage" class="empty-state">
        <h4>Unable to load deliveries</h4>
        <p>{{ errorMessage }}</p>

        <button @click="fetchOrders">
          Try again
        </button>
      </div>

      <div
        v-else-if="orders.length === 0"
        class="empty-state"
      >
        <div class="empty-icon">☾</div>

        <h4>No deliveries yet</h4>

        <p>Your delivery activity will appear here.</p>

        <button
          class="submit-btn"
          @click="goToCreate"
        >
          Create your first delivery →
        </button>
      </div>

      <div v-else class="order-list">

    <article
        v-for="order in orders.slice(0, 5)"
        :key="order.id"
        class="order-card"
        @click="openOrder(order.id)"
        role="button"
        tabindex="0"
        @keydown.enter="openOrder(order.id)"
      >

          <div class="order-header">
            <h4>{{ order.order_number }}</h4>

            <span
              class="status"
              :class="`status-${order.status}`"
            >
              {{ statusLabel(order.status) }}
            </span>
          </div>

          <div class="order-route">

            <div class="route-item">
              <span class="route-dot pickup"></span>

              <div>
                <small>Pickup</small>
                <p>{{ order.pickup_address }}</p>
              </div>
            </div>

            <div class="route-line"></div>

            <div class="route-item">
              <span class="route-dot delivery"></span>

              <div>
                <small>Delivery</small>
                <p>{{ order.delivery_address }}</p>
              </div>
            </div>

          </div>

          <div class="order-footer">
            <span>{{ order.delivery_type }}</span>
            <strong>{{ statusLabel(order.status).toUpperCase() }}</strong>
          </div>

          <small>{{ formatDate(order.created_at) }}</small>

        </article>

      </div>

    </section>

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>

  </main>
</template>
