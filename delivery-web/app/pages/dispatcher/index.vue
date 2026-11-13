
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


interface CourierAvailabilityResponse {
  message: string
  data: {
    summary: {
      available: number
      busy: number
      offline: number
      total: number
    }
    couriers: {
      id: number
      name: string
      email: string
      availability_status: 'available' | 'offline'
      has_active_order: boolean
      work_status: 'available' | 'busy' | 'offline'
    }[]
  }
}

interface FleetCourier {
  order_id: number
  order_number: string
  courier_id: number
  courier_name: string
  latitude: number | string
  longitude: number | string
  accuracy: number | string | null
  heading: number | string | null
  speed: number | string | null
  recorded_at: string
}

interface LiveCouriersResponse {
  message: string
  data: FleetCourier[]
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
const courierAvailability = ref<CourierAvailabilityResponse['data'] | null>(null)
const availabilityLoading = ref(false)
const availabilityError = ref('')
const liveCouriers = ref<FleetCourier[]>([])
const fleetLoading = ref(false)
const fleetError = ref('')


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


const fetchCourierAvailability = async () => {
  availabilityLoading.value = true
  availabilityError.value = ''

  try {
    const response = await api<CourierAvailabilityResponse>(
      '/dispatcher/courier-availability'
    )

    courierAvailability.value = response.data
  } catch (error: any) {
    availabilityError.value =
      error.data?.message || 'Unable to load courier availability.'
  } finally {
    availabilityLoading.value = false
  }
}

const fetchLiveCouriers = async () => {
  fleetLoading.value = true
  fleetError.value = ''

  try {
    const response = await api<LiveCouriersResponse>(
      '/dispatcher/live-couriers'
    )

    liveCouriers.value = response.data
  } catch (error: any) {
    fleetError.value =
      error.data?.message || 'Unable to load live courier locations.'
  } finally {
    fleetLoading.value = false
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

onMounted(() => {
  fetchOrders()
  fetchCourierAvailability()
  fetchLiveCouriers()
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

    
<section class="courier-overview">
  <div class="section-heading">
    <h3>Courier availability</h3>
    <span>WORKFORCE</span>
  </div>

  <div v-if="availabilityLoading" class="empty-state">
    Loading courier availability...
  </div>

  <div v-else-if="availabilityError" class="empty-state">
    <p>{{ availabilityError }}</p>
    <button @click="fetchCourierAvailability">
      Try again
    </button>
  </div>

  <template v-else-if="courierAvailability">
    <div class="courier-stats">
      <article class="courier-stat available">
        <span>AVAILABLE</span>
        <h3>{{ courierAvailability.summary.available }}</h3>
        <small>Ready for assignment</small>
      </article>

      <article class="courier-stat busy">
        <span>BUSY</span>
        <h3>{{ courierAvailability.summary.busy }}</h3>
        <small>Handling active orders</small>
      </article>

      <article class="courier-stat offline">
        <span>OFFLINE</span>
        <h3>{{ courierAvailability.summary.offline }}</h3>
        <small>Not accepting orders</small>
      </article>
    </div>

    <div class="courier-overview-list">
      <div
        v-for="courier in courierAvailability.couriers"
        :key="courier.id"
        class="courier-overview-item"
      >
        <div>
          <strong>{{ courier.name }}</strong>
          <small>{{ courier.email }}</small>
        </div>

        <span :class="`courier-work-status status-${courier.work_status}`">
          {{ courier.work_status }}
        </span>
      </div>
    </div>

    <p class="courier-total">
      Total registered couriers:
      <strong>{{ courierAvailability.summary.total }}</strong>
    </p>
  </template>
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
