
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
const cancelDialog = ref(false)
const cancelReason = ref('')
const cancelLoading = ref(false)
const cancelError = ref('')

const canCancel = computed(() =>
  ['pending', 'assigned'].includes(order.value?.status || '')
)

const submitCancellation = async () => {
  if (!cancelReason.value.trim()) {
    cancelError.value = 'Please provide a cancellation reason.'
    return
  }

  cancelLoading.value = true
  cancelError.value = ''

  try {
    await api(`/orders/${route.params.id}/cancel`, {
      method: 'POST',
      body: {
        cancellation_reason: cancelReason.value.trim()
      }
    })

    cancelDialog.value = false
    cancelReason.value = ''

    await fetchOrder()
  } catch (error: any) {
    cancelError.value =
      error.data?.message || 'Unable to cancel this order.'
  } finally {
    cancelLoading.value = false
  }
}

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

        <button
          v-if="canCancel"
          class="cancel-order-btn"
          @click="cancelDialog = true"
        >
          Cancel this order
        </button>
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
    <div v-if="cancelDialog" class="modal-overlay">
  <div class="cancel-modal">
    <span class="eyebrow">ORDER CANCELLATION</span>

    <h3>Cancel this delivery?</h3>

    <p>
      Are you sure you want to cancel
      <strong>{{ order?.order_number }}</strong>?
      This action cannot be undone.
    </p>

    <div class="form-field">
      <label>Reason for cancellation</label>

      <textarea
        v-model="cancelReason"
        maxlength="1000"
        placeholder="Tell us why you want to cancel..."
        rows="4"
      />

      <small>{{ cancelReason.length }}/1000 characters</small>
    </div>

    <p v-if="cancelError" class="form-error">
      {{ cancelError }}
    </p>

    <div class="modal-actions">
      <button
        class="modal-back-btn"
        :disabled="cancelLoading"
        @click="cancelDialog = false"
      >
        Keep order
      </button>

      <button
        class="modal-confirm-btn"
        :disabled="cancelLoading"
        @click="submitCancellation"
      >
        {{ cancelLoading ? 'Cancelling...' : 'Confirm cancellation' }}
      </button>
    </div>
  </div>
</div>
    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>
  </main>
</template>
