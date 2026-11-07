
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
    total: number
  }
}

const api = useApi()

const orders = ref<Order[]>([])
const loading = ref(true)
const errorMessage = ref('')

const currentPage = ref(1)
const lastPage = ref(1)
const totalOrders = ref(0)

const fetchOrders = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<OrderResponse>('/orders', {
      query: {
        page: currentPage.value
      }
    })

    orders.value = response.data.data || []
    currentPage.value = response.data.current_page || 1
    lastPage.value = response.data.last_page || 1
    totalOrders.value = response.data.total || 0
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to load orders.'
  } finally {
    loading.value = false
  }
}

const changePage = async (page: number) => {
  if (page < 1 || page > lastPage.value) return

  currentPage.value = page
  await fetchOrders()
}

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
    dateStyle: 'medium',
    timeStyle: 'short'
  })
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

      <NuxtLink
        to="/customer"
        class="logout-btn"
      >
        ← Home
      </NuxtLink>
    </header>

    <section class="welcome">
      <span class="eyebrow">CUSTOMER PORTAL</span>
      <h2>Your delivery<br /><span>history.</span></h2>
      <p>Monitor all your delivery orders in one place.</p>
    </section>

    <section class="stats">
      <article class="stat-card">
        <span class="stat-icon">▣</span>
        <p>TOTAL ORDERS</p>
        <h3>{{ totalOrders }}</h3>
        <small>Your delivery history</small>
      </article>
    </section>

    <section class="recent">
      <div class="section-heading">
        <h3>My Orders</h3>
        <span>HISTORY</span>
      </div>

      <div v-if="loading" class="empty-state">
        Loading orders...
      </div>

      <div v-else-if="errorMessage" class="empty-state">
        <h4>Unable to load orders</h4>
        <p>{{ errorMessage }}</p>
        <button @click="fetchOrders">
          Try again
        </button>
      </div>

      <div v-else-if="orders.length === 0" class="empty-state">
        <div class="empty-icon">☾</div>
        <h4>No orders yet</h4>
        <p>Your delivery history will appear here.</p>

        <NuxtLink
          to="/customer/create"
          class="submit-btn"
        >
          Create delivery →
        </NuxtLink>
      </div>

      <div v-else class="order-list">

        <NuxtLink
          v-for="order in orders"
          :key="order.id"
          :to="`/customer/orders/${order.id}`"
          class="order-card"
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

          <div class="order-detail-link">
            View order details →
          </div>
        </NuxtLink>

      </div>

      <div
        v-if="!loading && !errorMessage && lastPage > 1"
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
    </section>

  </main>
</template>

