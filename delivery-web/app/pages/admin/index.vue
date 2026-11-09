
<script setup lang="ts">
type Person = {
  id: number
  name: string
  email: string
  role?: {
    id?: number
    name?: string
    slug?: string
  }
  created_at?: string
}

type Order = {
  id: number
  order_number: string
  status: string
  customer?: Person
  courier?: Person | null
  recipient_name: string
  recipient_phone?: string
  pickup_address: string
  delivery_address: string
  price?: number | string | null
  notes?: string | null
  created_at: string
}

type OperationalStatistics = {
  success_rate: number
  cancellation_rate: number
  active_orders: number
  average_delivery_duration_minutes: number | null
  completed_orders: number
  daily_orders: {
    date: string
    total: number
  }[]
}

type Dashboard = {
  orders: {
    total: number
    by_status: Record<string, number>
  }
  users: {
    total: number
    customers: number
    dispatchers: number
    couriers: number
    admins: number
  }
  recent_orders: Order[]
  operational_statistics: OperationalStatistics
}

type Pagination<T> = {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

const api = useApi()
const route = useRoute()
const { logout } = useAuth()

const dashboard = ref<Dashboard | null>(null)
const loading = ref(true)
const pageError = ref('')

const sheetOpen = ref(false)
const sheetType = ref('')
const sheetTitle = ref('')
const sheetLoading = ref(false)
const sheetError = ref('')

const sheetOrders = ref<Order[]>([])
const sheetUsers = ref<Person[]>([])

const selectedOrder = ref<Order | null>(null)
const selectedUser = ref<Person | null>(null)

const selectedFilter = ref('all')
const selectedRole = ref('all')
const currentPage = ref(1)
const lastPage = ref(1)
const totalRecords = ref(0)

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'Pending', value: 'pending' },
  { label: 'Assigned', value: 'assigned' },
  { label: 'Picked up', value: 'picked_up' },
  { label: 'In transit', value: 'in_transit' },
  { label: 'Delivered', value: 'delivered' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'In progress', value: 'in_progress' },
]

const roleFilters = [
  { label: 'All users', value: 'all' },
  { label: 'Admin', value: 'admin' },
  { label: 'Dispatcher', value: 'dispatcher' },
  { label: 'Courier', value: 'courier' },
  { label: 'Customer', value: 'customer' },
]

const statusItems = [
  { label: 'Pending', value: 'pending' },
  { label: 'Assigned', value: 'assigned' },
  { label: 'Picked up', value: 'picked_up' },
  { label: 'In transit', value: 'in_transit' },
  { label: 'Delivered', value: 'delivered' },
  { label: 'Cancelled', value: 'cancelled' },
]

const userItems = [
  { label: 'Customers', key: 'customers', role: 'customer' },
  { label: 'Dispatchers', key: 'dispatchers', role: 'dispatcher' },
  { label: 'Couriers', key: 'couriers', role: 'courier' },
  { label: 'Admins', key: 'admins', role: 'admin' },
]

const userStats = computed(() => dashboard.value?.users ?? {
  total: 0,
  customers: 0,
  dispatchers: 0,
  couriers: 0,
  admins: 0,
})

const orderStats = computed(() => dashboard.value?.orders ?? {
  total: 0,
  by_status: {},
})

const operationalStats = computed(() =>
  dashboard.value?.operational_statistics ?? {
    success_rate: 0,
    cancellation_rate: 0,
    active_orders: 0,
    average_delivery_duration_minutes: null,
    completed_orders: 0,
    daily_orders: [],
  }
)

const dailyOrderBars = computed(() => {
  const rows = operationalStats.value.daily_orders
  const max = Math.max(...rows.map(item => item.total), 1)

  return rows.map(item => ({
    ...item,
    label: new Date(item.date + 'T00:00:00').toLocaleDateString(
      'id-ID',
      { day: '2-digit', month: 'short' }
    ),
    height: item.total > 0
      ? Math.max((item.total / max) * 100, 8)
      : 0,
  }))
})

function userCount(key: string) {
  const counts: Record<string, number> = {
    customers: userStats.value.customers,
    dispatchers: userStats.value.dispatchers,
    couriers: userStats.value.couriers,
    admins: userStats.value.admins,
  }

  return counts[key] || 0
}

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    pending: 'Pending',
    assigned: 'Assigned',
    picked_up: 'Picked up',
    in_transit: 'In transit',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
  }

  return labels[status] || status
}

function formatDate(value?: string) {
  if (!value) return '-'

  return new Date(value).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function formatPrice(value?: number | string | null) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value))
}

async function fetchDashboard() {
  loading.value = true
  pageError.value = ''

  try {
    const response: any = await api('/admin/dashboard')
    dashboard.value = response.data
  } catch (error: any) {
    pageError.value =
      error?.data?.message || 'Failed to load dashboard.'
  } finally {
    loading.value = false
  }
}

async function fetchOrders() {
  sheetLoading.value = true
  sheetError.value = ''

  try {
    const response: any = await api('/admin/orders', {
      query: {
        status: selectedFilter.value,
        page: currentPage.value,
      },
    })

    const result = response.data as Pagination<Order>

    sheetOrders.value = result.data || []
    currentPage.value = result.current_page || 1
    lastPage.value = result.last_page || 1
    totalRecords.value = result.total || 0
  } catch (error: any) {
    sheetError.value =
      error?.data?.message || 'Failed to load orders.'
  } finally {
    sheetLoading.value = false
  }
}

async function fetchUsers() {
  sheetLoading.value = true
  sheetError.value = ''

  try {
    const response: any = await api('/admin/users', {
      query: {
        role: selectedRole.value,
        page: currentPage.value,
      },
    })

    const result = response.data as Pagination<Person>

    sheetUsers.value = result.data || []
    currentPage.value = result.current_page || 1
    lastPage.value = result.last_page || 1
    totalRecords.value = result.total || 0
  } catch (error: any) {
    sheetError.value =
      error?.data?.message || 'Failed to load users.'
  } finally {
    sheetLoading.value = false
  }
}

async function openOrders(filter = 'all') {
  sheetType.value = 'orders'
  sheetTitle.value = 'All Orders'
  selectedFilter.value = filter
  currentPage.value = 1

  sheetOrders.value = []
  sheetUsers.value = []
  selectedOrder.value = null
  selectedUser.value = null

  sheetOpen.value = true

  await fetchOrders()
}

async function openUsers(role = 'all') {
  sheetType.value = 'users'
  sheetTitle.value = 'User Management'
  selectedRole.value = role
  currentPage.value = 1

  sheetOrders.value = []
  sheetUsers.value = []
  selectedOrder.value = null
  selectedUser.value = null

  sheetOpen.value = true

  await fetchUsers()
}

async function changeOrderFilter(filter: string) {
  selectedFilter.value = filter
  currentPage.value = 1
  await fetchOrders()
}

async function changeRoleFilter(role: string) {
  selectedRole.value = role
  currentPage.value = 1
  await fetchUsers()
}

async function changePage(page: number) {
  if (page < 1 || page > lastPage.value) return

  currentPage.value = page

  if (sheetType.value === 'orders') {
    await fetchOrders()
  } else if (sheetType.value === 'users') {
    await fetchUsers()
  }
}

function openOrderDetail(order: Order) {
  selectedOrder.value = order
  sheetType.value = 'order-detail'
  sheetTitle.value = 'Order Details'
  sheetOpen.value = true
}

function openUserDetail(person: Person) {
  selectedUser.value = person
  sheetType.value = 'user-detail'
  sheetTitle.value = 'User Profile'
  sheetOpen.value = true
}

async function closeSheet() {
  sheetOpen.value = false
  selectedOrder.value = null
  selectedUser.value = null

  if (route.query.tab) {
    await navigateTo('/admin', { replace: true })
  }
}

function scrollToTop() {
  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}

async function handleLogout() {
  sheetOpen.value = false
  await logout()
  await navigateTo('/login')
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeSheet()
  }
}

watch(
  () => route.query.tab,
  async (tab) => {
    if (tab === 'orders') {
      await openOrders('all')
    } else if (tab === 'users') {
      await openUsers('all')
    } else {
      sheetOpen.value = false
    }
  },
  { immediate: true }
)

onMounted(() => {
  fetchDashboard()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <main class="admin-dashboard">
    <header class="admin-topbar">
      <div>
        <span class="admin-eyebrow">KURIR MALAM</span>
        <h1>Admin Portal</h1>
        <p>Manage your delivery operations.</p>
      </div>

      <button class="admin-logout" @click="handleLogout">
        Logout
      </button>
    </header>

    <div v-if="loading" class="admin-state">
      Loading dashboard...
    </div>

    <div v-else-if="pageError" class="admin-state admin-error">
      {{ pageError }}
      <button @click="fetchDashboard">Try again</button>
    </div>

    <template v-else>
      <section class="admin-section">
        <div class="admin-section-heading">
          <div>
            <span class="admin-eyebrow">OVERVIEW</span>
            <h2>Dashboard Overview</h2>
          </div>
        </div>

        <div class="admin-stats-grid">
          <button
            class="admin-stat-card admin-stat-gold"
            @click="openOrders('all')"
          >
            <span>Total Orders</span>
            <strong>{{ orderStats.total }}</strong>
            <small>View all orders →</small>
          </button>

          <button
            class="admin-stat-card"
            @click="openUsers('all')"
          >
            <span>Total Users</span>
            <strong>{{ userStats.total }}</strong>
            <small>View all users →</small>
          </button>

          <button
            class="admin-stat-card"
            @click="openOrders('in_progress')"
          >
            <span>In Progress</span>
            <strong>
              {{
                (orderStats.by_status.assigned || 0) +
                (orderStats.by_status.picked_up || 0) +
                (orderStats.by_status.in_transit || 0)
              }}
            </strong>
            <small>View active orders →</small>
          </button>

          <button
            class="admin-stat-card"
            @click="openOrders('delivered')"
          >
            <span>Delivered</span>
            <strong>{{ orderStats.by_status.delivered || 0 }}</strong>
            <small>View delivered orders →</small>
          </button>
        </div>
      </section>

            <section class="admin-section">
        <div class="admin-section-heading">
          <div>
            <span class="admin-eyebrow">PERFORMANCE</span>
            <h2>Operational Statistics</h2>
          </div>
        </div>

        <div class="admin-operations-grid">
          <div class="admin-operation-card">
            <span>Success Rate</span>
            <strong class="operation-green">
              {{ operationalStats.success_rate }}%
            </strong>
            <small>Successfully delivered</small>
          </div>

          <div class="admin-operation-card">
            <span>Cancellation Rate</span>
            <strong class="operation-red">
              {{ operationalStats.cancellation_rate }}%
            </strong>
            <small>Cancelled orders</small>
          </div>

          <div class="admin-operation-card">
            <span>Active Orders</span>
            <strong>{{ operationalStats.active_orders }}</strong>
            <small>Currently being processed</small>
          </div>

          <div class="admin-operation-card">
            <span>Average Delivery</span>
            <strong>
              {{ operationalStats.average_delivery_duration_minutes ?? '-' }}
              <small v-if="operationalStats.average_delivery_duration_minutes !== null">
                min
              </small>
            </strong>
            <small>Transit to delivered</small>
          </div>
        </div>

        <div class="admin-chart-card">
          <div class="admin-chart-heading">
            <div>
              <h3>Daily Orders</h3>
              <p>Order activity over the last 7 days</p>
            </div>
            <strong>{{ operationalStats.completed_orders }} delivered</strong>
          </div>

          <div class="admin-chart">
            <div
              v-for="item in dailyOrderBars"
              :key="item.date"
              class="admin-chart-column"
            >
              <strong>{{ item.total }}</strong>
              <div class="admin-chart-track">
                <div
                  class="admin-chart-bar"
                  :style="{ height: `${item.height}%` }"
                ></div>
              </div>
              <small>{{ item.label }}</small>
            </div>
          </div>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-heading">
          <div>
            <span class="admin-eyebrow">ORDER MONITORING</span>
            <h2>Order Status</h2>
          </div>

          <button
            class="admin-text-button"
            @click="openOrders('all')"
          >
            View all →
          </button>
        </div>

        <div class="admin-status-list">
          <button
            v-for="item in statusItems"
            :key="item.value"
            class="admin-status-row"
            @click="openOrders(item.value)"
          >
            <span class="admin-status-name">
              <i :class="`status-dot status-${item.value}`"></i>
              {{ item.label }}
            </span>

            <strong>
              {{ orderStats.by_status[item.value] || 0 }}
            </strong>

            <span class="admin-row-arrow">›</span>
          </button>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-heading">
          <div>
            <span class="admin-eyebrow">USER MANAGEMENT</span>
            <h2>User Accounts</h2>
          </div>

          <button
            class="admin-text-button"
            @click="openUsers('all')"
          >
            View all →
          </button>
        </div>

        <div class="admin-user-grid">
          <button
            v-for="item in userItems"
            :key="item.role"
            class="admin-user-card"
            @click="openUsers(item.role)"
          >
            <span>{{ item.label }}</span>
            <strong>{{ userCount(item.key) }}</strong>
            <small>View accounts →</small>
          </button>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-heading">
          <div>
            <span class="admin-eyebrow">LATEST ACTIVITY</span>
            <h2>Recent Orders</h2>
          </div>

          <button
            class="admin-text-button"
            @click="openOrders('all')"
          >
            All orders →
          </button>
        </div>

        <div
          v-if="!dashboard?.recent_orders?.length"
          class="admin-empty"
        >
          No recent orders.
        </div>

        <div v-else class="admin-recent-list">
          <button
            v-for="order in dashboard.recent_orders"
            :key="order.id"
            class="admin-recent-card"
            @click="openOrderDetail(order)"
          >
            <div class="admin-recent-top">
              <strong>{{ order.order_number }}</strong>

              <span
                class="admin-order-badge"
                :class="`status-${order.status}`"
              >
                {{ statusLabel(order.status) }}
              </span>
            </div>

            <p>
              {{ order.customer?.name || 'Unknown customer' }}
              → {{ order.recipient_name }}
            </p>

            <small>{{ formatDate(order.created_at) }}</small>
          </button>
        </div>
      </section>
    </template>

    <Teleport to="body">
      <Transition name="admin-sheet">
        <div
          v-if="sheetOpen"
          class="admin-sheet-overlay"
          @click.self="closeSheet"
        >
          <section class="admin-bottom-sheet">
            <div class="admin-sheet-handle"></div>

            <header class="admin-sheet-header">
              <div>
                <span class="admin-eyebrow">ADMIN PORTAL</span>
                <h3>{{ sheetTitle }}</h3>
              </div>

              <button
                class="admin-sheet-close"
                aria-label="Close"
                @click="closeSheet"
              >
                ×
              </button>
            </header>

            <div class="admin-sheet-content">
              <template v-if="sheetType === 'orders'">
                <div class="admin-sheet-filters">
                  <button
                    v-for="filter in statusFilters"
                    :key="filter.value"
                    :class="{ active: selectedFilter === filter.value }"
                    @click="changeOrderFilter(filter.value)"
                  >
                    {{ filter.label }}
                  </button>
                </div>

                <div class="admin-sheet-count">
                  {{ totalRecords }} orders found
                </div>

                <div v-if="sheetLoading" class="admin-sheet-state">
                  Loading orders...
                </div>

                <div v-else-if="sheetError" class="admin-sheet-state">
                  {{ sheetError }}
                  <button @click="fetchOrders">Try again</button>
                </div>

                <div
                  v-else-if="sheetOrders.length === 0"
                  class="admin-sheet-state"
                >
                  <strong>No orders found</strong>
                  <p>There are no orders in this category.</p>
                </div>

                <div v-else class="admin-sheet-list">
                  <button
                    v-for="order in sheetOrders"
                    :key="order.id"
                    class="admin-sheet-order"
                    @click="openOrderDetail(order)"
                  >
                    <div class="admin-recent-top">
                      <strong>{{ order.order_number }}</strong>

                      <span
                        class="admin-order-badge"
                        :class="`status-${order.status}`"
                      >
                        {{ statusLabel(order.status) }}
                      </span>
                    </div>

                    <p>
                      {{ order.customer?.name || 'Unknown customer' }}
                      → {{ order.recipient_name }}
                    </p>

                    <small>{{ formatDate(order.created_at) }}</small>
                  </button>
                </div>

                <div
                  v-if="!sheetLoading && lastPage > 1"
                  class="admin-pagination"
                >
                  <button
                    :disabled="currentPage <= 1"
                    @click="changePage(currentPage - 1)"
                  >
                    Previous
                  </button>

                  <span>{{ currentPage }} / {{ lastPage }}</span>

                  <button
                    :disabled="currentPage >= lastPage"
                    @click="changePage(currentPage + 1)"
                  >
                    Next
                  </button>
                </div>
              </template>

              <template v-else-if="sheetType === 'users'">
                <div class="admin-sheet-filters">
                  <button
                    v-for="filter in roleFilters"
                    :key="filter.value"
                    :class="{ active: selectedRole === filter.value }"
                    @click="changeRoleFilter(filter.value)"
                  >
                    {{ filter.label }}
                  </button>
                </div>

                <div class="admin-sheet-count">
                  {{ totalRecords }} accounts found
                </div>

                <div v-if="sheetLoading" class="admin-sheet-state">
                  Loading users...
                </div>

                <div v-else-if="sheetError" class="admin-sheet-state">
                  {{ sheetError }}
                  <button @click="fetchUsers">Try again</button>
                </div>

                <div
                  v-else-if="sheetUsers.length === 0"
                  class="admin-sheet-state"
                >
                  <strong>No users found</strong>
                  <p>There are no accounts in this category.</p>
                </div>

                <div v-else class="admin-sheet-list">
                  <button
                    v-for="person in sheetUsers"
                    :key="person.id"
                    class="admin-sheet-user"
                    @click="openUserDetail(person)"
                  >
                    <span class="admin-avatar">
                      {{ person.name.charAt(0).toUpperCase() }}
                    </span>

                    <span class="admin-user-list-info">
                      <strong>{{ person.name }}</strong>
                      <small>{{ person.email }}</small>
                      <small>{{ person.role?.name || 'Unknown role' }}</small>
                    </span>

                    <span class="admin-row-arrow">›</span>
                  </button>
                </div>

                <div
                  v-if="!sheetLoading && lastPage > 1"
                  class="admin-pagination"
                >
                  <button
                    :disabled="currentPage <= 1"
                    @click="changePage(currentPage - 1)"
                  >
                    Previous
                  </button>

                  <span>{{ currentPage }} / {{ lastPage }}</span>

                  <button
                    :disabled="currentPage >= lastPage"
                    @click="changePage(currentPage + 1)"
                  >
                    Next
                  </button>
                </div>
              </template>

              <template
                v-else-if="sheetType === 'order-detail' && selectedOrder"
              >
                <button
                  class="admin-back-button"
                  @click="openOrders(selectedFilter)"
                >
                  ← Back to orders
                </button>

                <div class="admin-detail-card">
                  <span class="admin-eyebrow">ORDER NUMBER</span>
                  <h2>{{ selectedOrder.order_number }}</h2>

                  <span
                    class="admin-order-badge"
                    :class="`status-${selectedOrder.status}`"
                  >
                    {{ statusLabel(selectedOrder.status) }}
                  </span>
                </div>

                <div class="admin-detail-card">
                  <h4>Customer</h4>
                  <p>{{ selectedOrder.customer?.name || 'Unknown' }}</p>
                  <small>{{ selectedOrder.customer?.email || '-' }}</small>
                </div>

                
                <div class="admin-detail-card">
                  <h4>Delivery Information</h4>

                  <p>
                    <strong>Recipient:</strong>
                    {{ selectedOrder.recipient_name }}
                  </p>

                  <p>
                    <strong>Phone:</strong>
                    {{ selectedOrder.recipient_phone || '-' }}
                  </p>

                  <p>
                    <strong>Pickup:</strong>
                    {{ selectedOrder.pickup_address }}
                  </p>

                  <p>
                    <strong>Destination:</strong>
                    {{ selectedOrder.delivery_address }}
                  </p>

                  <p>
                    <strong>Courier:</strong>
                    {{ selectedOrder.courier?.name || 'Not assigned' }}
                  </p>

                  <p>
                    <strong>Price:</strong>
                    {{ formatPrice(selectedOrder.price) }}
                  </p>

                  <p>
                    <strong>Created:</strong>
                    {{ formatDate(selectedOrder.created_at) }}
                  </p>

                  <p v-if="selectedOrder.notes">
                    <strong>Notes:</strong>
                    {{ selectedOrder.notes }}
                  </p>
                </div>
              </template>

              <!-- USER DETAIL -->
              <template
                v-else-if="sheetType === 'user-detail' && selectedUser"
              >
                <button
                  class="admin-back-button"
                  @click="openUsers(selectedRole)"
                >
                  ← Back to users
                </button>

                <div class="admin-profile-card">
                  <div class="admin-profile-avatar">
                    {{ selectedUser.name.charAt(0).toUpperCase() }}
                  </div>

                  <h2>{{ selectedUser.name }}</h2>
                  <p>{{ selectedUser.email }}</p>

                  <span class="admin-order-badge status-assigned">
                    {{ selectedUser.role?.name || 'Unknown role' }}
                  </span>
                </div>

                <div class="admin-detail-card">
                  <h4>Account Information</h4>

                  <p>
                    <strong>User ID:</strong>
                    {{ selectedUser.id }}
                  </p>

                  <p>
                    <strong>Email:</strong>
                    {{ selectedUser.email }}
                  </p>

                  <p>
                    <strong>Role:</strong>
                    {{ selectedUser.role?.name || '-' }}
                  </p>

                  <p>
                    <strong>Registered:</strong>
                    {{ formatDate(selectedUser.created_at) }}
                  </p>
                </div>
              </template>
            </div>
          </section>
        </div>
      </Transition>
    </Teleport>
  </main>
</template>
