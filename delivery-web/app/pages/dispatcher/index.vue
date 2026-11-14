
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

type GpsHealth = 'live' | 'stale' | 'offline'

const getGpsHealth = (recordedAt: string): GpsHealth => {
  const timestamp = new Date(recordedAt).getTime()

  // Timestamp tidak valid atau terlalu jauh di masa depan.
  if (
    !Number.isFinite(timestamp) ||
    timestamp > Date.now() + 120_000
  ) {
    return 'offline'
  }

  const age = Math.max(
    0,
    (Date.now() - timestamp) / 1000
  )

  if (age <= 30) return 'live'
  if (age <= 90) return 'stale'

  return 'offline'
}

const getGpsAge = (recordedAt: string) => {
  const timestamp = new Date(recordedAt).getTime()

  if (
    !Number.isFinite(timestamp) ||
    timestamp > Date.now() + 120_000
  ) {
    return 'Invalid timestamp'
  }

  const seconds = Math.floor(
    Math.max(0, Date.now() - timestamp) / 1000
  )

  if (seconds < 60) return `${seconds}s ago`

  return `${Math.floor(seconds / 60)}m ago`
}

const fleetHealthSummary = computed(() => {
  // Trigger recalculation every time the clock changes
  gpsHealthTick.value

  return {
    live: liveCouriers.value.filter(
      courier => getGpsHealth(courier.recorded_at) === 'live'
    ).length,

    stale: liveCouriers.value.filter(
      courier => getGpsHealth(courier.recorded_at) === 'stale'
    ).length,

    offline: liveCouriers.value.filter(
      courier => getGpsHealth(courier.recorded_at) === 'offline'
    ).length
  }
})


const { user, logout } = useAuth()
const api = useApi()
const route = useRoute()

const nuxtApp = useNuxtApp() as any
const echo = nuxtApp.$echo

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
interface FleetLocationEvent {
  order_id: number
  latitude: number
  longitude: number
  accuracy: number | null
  heading: number | null
  speed: number | null
  recorded_at: string
}

const fleetChannels = new Map<number, string>()
let fleetPollingTimer: ReturnType<typeof setInterval> | null = null
let gpsHealthTimer: ReturnType<typeof setInterval> | null = null

const gpsHealthTick = ref(Date.now())


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
      syncFleetSubscriptions()
  } catch (error: any) {
    fleetError.value =
      error.data?.message || 'Unable to load live courier locations.'
  } finally {
    fleetLoading.value = false
  }
}

const syncFleetSubscriptions = () => {
  if (!echo) return

  const activeOrderIds = new Set(
    liveCouriers.value.map(courier => courier.order_id)
  )

  // Unsubscribe orders that are no longer active
  for (const [orderId, channelName] of fleetChannels) {
    if (!activeOrderIds.has(orderId)) {
      echo.leave(channelName)
      fleetChannels.delete(orderId)
    }
  }

  // Subscribe to every active order
  for (const courier of liveCouriers.value) {
    const orderId = courier.order_id

    if (fleetChannels.has(orderId)) continue

    const channelName = `orders.${orderId}`

    echo.private(channelName)
      .listen(
        '.courier.location.updated',
        (event: FleetLocationEvent) => {
          if (event.order_id !== orderId) return

liveCouriers.value = liveCouriers.value.map(item => {
    if (item.order_id !== orderId) return item

    // Ignore outdated GPS events.
    if (
        Date.parse(event.recorded_at) <=
        Date.parse(item.recorded_at)
    ) {
        return item
    }

    return {
              ...item,
              latitude: event.latitude,
              longitude: event.longitude,
              accuracy: event.accuracy,
              heading: event.heading,
              speed: event.speed,
              recorded_at: event.recorded_at
            }
          })
        }
      )

    fleetChannels.set(orderId, channelName)
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

  fleetPollingTimer = setInterval(() => {
    fetchLiveCouriers()
  }, 30000)
  gpsHealthTimer = setInterval(() => {
  gpsHealthTick.value = Date.now()
}, 5000)
})

  onBeforeUnmount(() => {
  if (fleetPollingTimer !== null) {
    clearInterval(fleetPollingTimer)
    fleetPollingTimer = null
  }

  if (echo) {
    for (const channelName of fleetChannels.values()) {
      echo.leave(channelName)
    }
  }
  if (gpsHealthTimer !== null) {
  clearInterval(gpsHealthTimer)
  gpsHealthTimer = null
}

  fleetChannels.clear()
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


    <section class="fleet-section">
  <div class="section-heading">
    <h3>Live Fleet Monitoring</h3>
    <span>REALTIME TRACKING</span>
  </div>

  <div class="fleet-health-summary">
  <div class="health-item">
    <span class="health-indicator live"></span>
    <strong>{{ fleetHealthSummary.live }}</strong>
    <small>LIVE</small>
  </div>

  <div class="health-item">
    <span class="health-indicator stale"></span>
    <strong>{{ fleetHealthSummary.stale }}</strong>
    <small>STALE</small>
  </div>

  <div class="health-item">
    <span class="health-indicator offline"></span>
    <strong>{{ fleetHealthSummary.offline }}</strong>
    <small>OFFLINE</small>
  </div>
</div>

  <div class="fleet-toolbar">
    <div>
      <strong>{{ liveCouriers.length }}</strong>
      <span> active courier locations</span>
    </div>

    <button
      class="fleet-refresh"
      :disabled="fleetLoading"
      @click="fetchLiveCouriers"
    >
      {{ fleetLoading ? 'Refreshing...' : '↻ Refresh' }}
    </button>
  </div>

  <div v-if="fleetLoading && liveCouriers.length === 0" class="empty-state">
    Loading live courier locations...
  </div>

  <div v-else-if="fleetError" class="empty-state">
    <p>{{ fleetError }}</p>
    <button @click="fetchLiveCouriers">Try again</button>
  </div>

  <div v-else-if="liveCouriers.length === 0" class="empty-state">
    <div class="empty-icon">⌖</div>
    <h4>No active courier locations</h4>
    <p>Courier locations will appear here when an order is in transit and GPS data is available.</p>
  </div>

  <template v-else>
    <FleetMonitoringMap :couriers="liveCouriers" />

    <div class="fleet-courier-list">
      <article
        v-for="courier in liveCouriers"
        :key="courier.order_id"
        class="fleet-courier-item"
      >
        <div>
  <strong>{{ courier.courier_name }}</strong>
  <small>{{ courier.order_number }}</small>

  <span
    class="gps-health-badge"
    :class="`gps-${getGpsHealth(courier.recorded_at)}`"
  >
    {{ getGpsHealth(courier.recorded_at).toUpperCase() }}
  </span>
</div>

        <div class="fleet-courier-location">
          <span class="status-dot"></span>
          <small>{{ getGpsAge(courier.recorded_at) }}</small>
        </div>
      </article>
    </div>
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
