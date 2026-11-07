
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
  sender_phone: string
  pickup_address: string
  recipient_name: string
  recipient_phone: string
  delivery_address: string
  created_at: string
  customer: {
    id: number
    name: string
    email: string
  }
  courier: {
    id: number
    name: string
  } | null
}

interface OrderResponse {
  message: string
  data: {
    data: Order[]
    current_page: number
    last_page: number
    total: number
  }
}

const { user, logout } = useAuth()
const api = useApi()

const route = useRoute()

const currentView = computed(() => {
  const view = route.query.view

  if (view === 'queue') return 'queue'
  if (view === 'all') return 'all'

  return 'home'
})


const orders = ref<Order[]>([])
const loading = ref(false)
const errorMessage = ref('')
const selectedStatus = ref('all')
const totalOrders = ref(0)

const filters = [
  { label: 'All orders', value: 'all' },
  { label: 'Pending', value: 'pending' },
  { label: 'Assigned', value: 'assigned' },
  { label: 'Picked up', value: 'picked_up' },
  { label: 'In transit', value: 'in_transit' },
  { label: 'Delivered', value: 'delivered' },
  { label: 'Cancelled', value: 'cancelled' }
]


const fetchOrders = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const status = currentView.value === 'queue'
      ? 'pending'
      : selectedStatus.value

    const query = status === 'all'
      ? ''
      : `?status=${status}`

    const response = await api<OrderResponse>(
      `/dispatcher/orders${query}`
    )

    orders.value = response.data.data
    totalOrders.value = response.data.total
  } catch (error: any) {
    errorMessage.value =
      error.data?.message || 'Unable to load orders.'
  } finally {
    loading.value = false
  }
}


const handleLogout = async () => {
  await logout()
  await navigateTo('/')
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

const statusLabel = (status: string) =>
  status.replace('_', ' ')


watch(selectedStatus, () => {
  if (currentView.value !== 'queue') {
    fetchOrders()
  }
})

watch(
  () => route.query.view,
  (view) => {
    selectedStatus.value = 'all'
    fetchOrders()
  }
)

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

      <button class="logout-btn" @click="handleLogout">
        Sign out ↗
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">DISPATCHER PORTAL</span>
      <h2>Welcome back,<br /><span>{{ user?.name || 'Dispatcher' }}.</span></h2>
      <p>Monitor and manage incoming deliveries.</p>
    </section>

    <section class="stats">
      <article class="stat-card">
        <span class="stat-icon">▣</span>
        <p>TOTAL ORDERS</p>
        <h3>{{ totalOrders }}</h3>
        <small>Orders in current filter</small>
      </article>

      <article class="stat-card">
        <span class="stat-icon">◷</span>
        <p>IN QUEUE</p>
        <h3>
          {{ orders.filter(o => o.status === 'pending').length }}
        </h3>
        <small>Waiting for assignment</small>
      </article>
    </section>

    <section class="recent">
      <div class="section-heading">
        <h3>Order management</h3>
        <span>OPERATIONS</span>
      </div>

      <div v-if="currentView !== 'queue'"class="dispatcher-filters">
        <button
          v-for="filter in filters"
          :key="filter.value"
          :class="{ active: selectedStatus === filter.value }"
          @click="selectedStatus = filter.value"
        >
          {{ filter.label }}
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        Loading orders...
      </div>

      <div v-else-if="errorMessage" class="empty-state">
        <h4>Unable to load orders</h4>
        <p>{{ errorMessage }}</p>
        <button @click="fetchOrders">Try again</button>
      </div>

      <div v-else-if="orders.length === 0" class="empty-state">
        <div class="empty-icon">☾</div>
        <h4>No orders found</h4>
        <p>There are no orders in this category.</p>
      </div>

      <div v-else class="order-list">
        <article
          v-for="order in orders"
          :key="order.id"
          class="order-card"
          @click="navigateTo(`/dispatcher/orders/${order.id}`)"
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

          <div class="dispatcher-order-info">
            <p>Customer: <strong>{{ order.customer.name }}</strong></p>
            <p>Recipient: {{ order.recipient_name }}</p>
            <p>Pickup: {{ order.pickup_address }}</p>
            <p>Destination: {{ order.delivery_address }}</p>
          </div>

          <div class="order-footer">
            <span>{{ order.delivery_type }}</span>
            <span>{{ formatDate(order.created_at) }}</span>
          </div>
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
