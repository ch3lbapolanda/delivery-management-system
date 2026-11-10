
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Person {
  id: number
  name: string
  email?: string
}

interface DeliveryProof {
  id: number
  order_id: number
  uploaded_by: Person | null
  photo_path: string
  photo_url: string
  recipient_name: string
  notes: string | null
  created_at: string
}

interface AdminOrder {
  id: number
  order_number: string
  status: string
  delivery_type: string
  customer?: Person
  courier?: Person | null
  sender_name: string
  sender_phone: string
  recipient_name: string
  recipient_phone: string
  pickup_address: string
  delivery_address: string
  price: number | string | null
  notes: string | null
  created_at: string
  delivery_proof?: DeliveryProof | null
}

interface OrdersResponse {
  message: string
  data: {
    data: AdminOrder[]
    current_page: number
    last_page: number
    total: number
  }
}

const api = useApi()
const route = useRoute()

const orders = ref<AdminOrder[]>([])
const loading = ref(true)
const errorMessage = ref('')

const selectedStatus = ref('all')
const currentPage = ref(1)
const lastPage = ref(1)
const totalOrders = ref(0)

const selectedOrder = ref<AdminOrder | null>(null)

const filters = [
  { label: 'All', value: 'all' },
  { label: 'Pending', value: 'pending' },
  { label: 'Assigned', value: 'assigned' },
  { label: 'Picked up', value: 'picked_up' },
  { label: 'In transit', value: 'in_transit' },
  { label: 'Delivered', value: 'delivered' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'In progress', value: 'in_progress' },
]

const statusLabel = (status: string) => {
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

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

const formatPrice = (price: number | string | null) => {
  if (price === null || price === '') {
    return 'Belum ditentukan'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(price))
}

const fetchOrders = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<OrdersResponse>('/admin/orders', {
      query: {
        status: selectedStatus.value,
        page: currentPage.value,
      },
    })

    orders.value = response.data.data || []
    currentPage.value = response.data.current_page || 1
    lastPage.value = response.data.last_page || 1
    totalOrders.value = response.data.total || 0

    if (selectedOrder.value) {
      selectedOrder.value =
        orders.value.find(
          order => order.id === selectedOrder.value?.id
        ) || null
    }
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to load orders.'
  } finally {
    loading.value = false
  }
}

const changeFilter = async (status: string) => {
  selectedStatus.value = status
  currentPage.value = 1
  selectedOrder.value = null

  await fetchOrders()
}

const changePage = async (page: number) => {
  if (page < 1 || page > lastPage.value) return

  currentPage.value = page
  selectedOrder.value = null

  await fetchOrders()
}

const openOrder = (order: AdminOrder) => {
  selectedOrder.value =
    selectedOrder.value?.id === order.id ? null : order
}

const goBack = () => {
  navigateTo('/admin')
}

watch(
  () => route.query.status,
  (status) => {
    if (
      typeof status === 'string' &&
      filters.some(filter => filter.value === status)
    ) {
      selectedStatus.value = status
      currentPage.value = 1
      selectedOrder.value = null
      fetchOrders()
    }
  }
)

onMounted(fetchOrders)
</script>

<template>
  <main class="admin-dashboard">

    <header class="admin-topbar">
      <div>
        <span class="admin-eyebrow">KURIR MALAM</span>
        <h1>Order Management</h1>
        <p>Monitor all delivery orders.</p>
      </div>

      <button class="admin-text-button" @click="goBack">
        ← Dashboard
      </button>
    </header>

    <section class="admin-section">

      <div class="admin-section-heading">
        <div>
          <span class="admin-eyebrow">ORDER OPERATIONS</span>
          <h2>All Orders</h2>
        </div>

        <button
          class="admin-text-button"
          @click="fetchOrders"
          :disabled="loading"
        >
          ↻ Refresh
        </button>
      </div>

      <div class="admin-detail-card admin-orders-summary">
        <span class="admin-eyebrow">TOTAL ORDERS</span>
        <h2>{{ totalOrders }}</h2>
        <p>Orders matching the selected filter.</p>
      </div>

      <div class="admin-sheet-filters">
        <button
          v-for="filter in filters"
          :key="filter.value"
          :class="{ active: selectedStatus === filter.value }"
          @click="changeFilter(filter.value)"
        >
          {{ filter.label }}
        </button>
      </div>

      <div class="admin-sheet-count">
        {{ totalOrders }} orders found
      </div>

      <div v-if="loading" class="admin-sheet-state">
        Loading orders...
      </div>

      <div v-else-if="errorMessage" class="admin-sheet-state">
        <strong>Unable to load orders</strong>
        <p>{{ errorMessage }}</p>
        <button @click="fetchOrders">Try again</button>
      </div>

      <div
        v-else-if="orders.length === 0"
        class="admin-sheet-state"
      >
        <strong>No orders found</strong>
        <p>There are no orders in this category.</p>
      </div>

      <div v-else class="admin-sheet-list">

        <article
          v-for="order in orders"
          :key="order.id"
          class="admin-sheet-order"
        >
          <button
            class="admin-order-select"
            @click="openOrder(order)"
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

            <span class="admin-row-arrow">
              {{ selectedOrder?.id === order.id ? '⌃' : '⌄' }}
            </span>
          </button>

          <div
            v-if="selectedOrder?.id === order.id"
            class="admin-detail-card"
          >
            <h3>Order Details</h3>
            
            <div class="admin-price-card">
              <span class="admin-eyebrow">PAYMENT INFORMATION</span>

              <h4>Delivery Price</h4>

              <div class="admin-price-row">
                <span>Service Type</span>
                <strong>
                  {{ order.delivery_type === 'instant' ? 'Instant' : 'Scheduled' }}
                </strong>
              </div>

              <div class="admin-price-total">
                <span>Total Delivery Price</span>
                <strong>{{ formatPrice(order.price) }}</strong>
              </div>

              <p class="admin-price-note">
                Final delivery price calculated by Kurir Malam.
              </p>
            </div>


            <h4>Customer</h4>
            <p>{{ order.customer?.name || 'Unknown' }}</p>
            <small>{{ order.customer?.email || '-' }}</small>

            <div class="admin-order-detail-spacing">
              <h4>Sender Information</h4>
              <p>{{ order.sender_name }}</p>
              <p>{{ order.sender_phone }}</p>
              <p>{{ order.pickup_address }}</p>
            </div>

            <div class="admin-order-detail-spacing">
              <h4>Recipient Information</h4>
              <p>{{ order.recipient_name }}</p>
              <p>{{ order.recipient_phone }}</p>
              <p>{{ order.delivery_address }}</p>
            </div>

            <div class="admin-order-detail-spacing">
              <h4>Courier</h4>
              <p>{{ order.courier?.name || 'Not assigned' }}</p>
              <p>{{ order.courier?.email || '-' }}</p>
            </div>

              <div class="admin-order-detail-spacing">
              <h4>Delivery Information</h4>
              <p>{{ order.delivery_type }}</p>
              <p>{{ formatDate(order.created_at) }}</p>
            </div>

            <div
              v-if="order.notes"
              class="admin-order-detail-spacing"
            >
              <h4>Customer Notes</h4>
              <p>{{ order.notes }}</p>
            </div>

            <!-- PROOF OF DELIVERY -->
            <div class="admin-order-detail-spacing admin-pod-section">
              <h4>Proof of Delivery</h4>

              <div
                v-if="order.delivery_proof"
                class="admin-pod-content"
              >
                <div class="admin-pod-status">
                  <span>✓</span>
                  Delivery proof available
                </div>

                <a
                  :href="order.delivery_proof.photo_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="admin-pod-image-link"
                >
                  <img
                    :src="order.delivery_proof.photo_url"
                    alt="Proof of delivery"
                    class="admin-pod-image"
                  />
                </a>

                <div class="admin-pod-info">
                  <div>
                    <span>Received By</span>
                    <strong>
                      {{ order.delivery_proof.recipient_name }}
                    </strong>
                  </div>

                  <div>
                    <span>Uploaded By</span>
                    <strong>
                      {{ order.delivery_proof.uploaded_by?.name || 'Unknown' }}
                    </strong>
                  </div>

                  <div>
                    <span>Upload Time</span>
                    <strong>
                      {{ formatDate(order.delivery_proof.created_at) }}
                    </strong>
                  </div>

                  <div v-if="order.delivery_proof.notes">
                    <span>Courier Notes</span>
                    <strong>{{ order.delivery_proof.notes }}</strong>
                  </div>
                </div>

                <a
                  :href="order.delivery_proof.photo_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="admin-pod-open-link"
                >
                  Open Full Image ↗
                </a>
              </div>

              <div v-else class="admin-pod-empty">
                <template v-if="order.status === 'delivered'">
                  Order delivered, but no proof was uploaded.
                </template>

                <template v-else>
                  No delivery proof available yet.
                </template>
              </div>
            </div>

          </div>
        </article>

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
