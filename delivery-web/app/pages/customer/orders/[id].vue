
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface OrderHistory {
  id: number
  old_status: string | null
  new_status: string
  note: string | null
  created_at: string
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
  notes: string | null
  created_at: string
  status_histories: OrderHistory[]
}

const route = useRoute()
const api = useApi()

const order = ref<Order | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const fetchOrder = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<{ data: Order }>(
      `/orders/${route.params.id}`
    )

    order.value = response.data
  } catch (error: any) {
    errorMessage.value =
      error.data?.message || 'Unable to load order details.'
  } finally {
    loading.value = false
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

onMounted(fetchOrder)
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

      <button class="logout-btn" @click="navigateTo('/customer')">
        ← Back
      </button>
    </header>

    <section class="welcome">
      <span class="eyebrow">ORDER DETAILS</span>
      <h2>Delivery<br /><span>information.</span></h2>
      <p>View your order and its delivery progress.</p>
    </section>

    <div v-if="loading" class="empty-state">
      Loading order details...
    </div>

    <div v-else-if="errorMessage" class="empty-state">
      <h4>Unable to load order</h4>
      <p>{{ errorMessage }}</p>
      <button @click="fetchOrder">Try again</button>
    </div>

    <template v-else-if="order">
      <section class="detail-card">
        <span class="eyebrow">ORDER NUMBER</span>
        <h2>{{ order.order_number }}</h2>

        <span class="status" :class="`status-${order.status}`">
          {{ order.status.replace('_', ' ') }}
        </span>

        <p class="detail-date">
          Created {{ formatDate(order.created_at) }}
        </p>
      </section>

      <section class="detail-card">
        <h3>Pickup information</h3>
        <p><strong>{{ order.sender_name }}</strong></p>
        <p>{{ order.sender_phone }}</p>
        <p>{{ order.pickup_address }}</p>
      </section>

      <section class="detail-card">
        <h3>Recipient information</h3>
        <p><strong>{{ order.recipient_name }}</strong></p>
        <p>{{ order.recipient_phone }}</p>
        <p>{{ order.delivery_address }}</p>
      </section>

      <section class="detail-card">
        <h3>Delivery information</h3>
        <p>Type: {{ order.delivery_type }}</p>
        <p>Notes: {{ order.notes || 'No additional notes' }}</p>
      </section>

      <section class="detail-card">
        <h3>Order history</h3>

        <div
          v-for="history in order.status_histories"
          :key="history.id"
          class="history-item"
        >
          <span class="history-dot"></span>
          <div>
            <strong>
              {{ history.new_status.replace('_', ' ') }}
            </strong>
            <p>{{ history.note || 'Status updated' }}</p>
            <small>{{ formatDate(history.created_at) }}</small>
          </div>
        </div>
      </section>
    </template>

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>
  </main>
</template>
