
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface Person {
  id: number
  name: string
  email?: string
}

interface StatusHistory {
  id: number
  old_status: string | null
  new_status: string
  note: string | null
  created_at: string
  changed_by: Person | null
}

interface OrderEta {
  available: boolean
  distance_km: number | null
  duration_minutes: number | null
  estimated_arrival_at: string | null
  message: string
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

  interface PricingDetails {
  delivery_type: string
  distance_km: number
  base_price: number
  price_per_km: number
  distance_fee: number
  additional_fee: number
  minimum_price: number
  total_price: number
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
  scheduled_pickup_at: string | null
  notes: string | null
  price: string | number | null
  pricing_details?: PricingDetails | null
  eta?: OrderEta
  customer: Person | null
  status_histories: StatusHistory[]
  delivery_proof?: DeliveryProof | null
}

interface OrderResponse {
  message: string
  data: Order
  eta?: OrderEta
}

const route = useRoute()
const api = useApi()

const order = ref<Order | null>(null)
const loading = ref(true)
const updating = ref(false)
const uploading = ref(false)
const compressing = ref(false)

const error = ref('')
const actionMessage = ref('')
const uploadError = ref('')

const photoFile = ref<File | null>(null)
const photoPreview = ref('')
const originalPhotoSize = ref(0)
const recipientName = ref('')
const proofNotes = ref('')

const fileInput = ref<HTMLInputElement | null>(null)

const fetchOrder = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api<OrderResponse>(
      `/courier/orders/${route.params.id}`
    )

    order.value = {
      ...response.data,
      eta: response.eta
    }
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal mengambil detail pengiriman.'
  } finally {
    loading.value = false
  }
}

const nextStatus: Record<string, string> = {
  assigned: 'picked_up',
  picked_up: 'in_transit'
}

const actionLabels: Record<string, string> = {
  assigned: 'Mark as Picked Up',
  picked_up: 'Start Delivery'
}

const statusLabels: Record<string, string> = {
  assigned: 'Kurir Ditugaskan',
  picked_up: 'Sudah Dijemput',
  in_transit: 'Dalam Perjalanan',
  delivered: 'Selesai',
  cancelled: 'Dibatalkan',
  pending: 'Menunggu'
}

const statusLabel = (status: string) => {
  return statusLabels[status] || status
}

const statusClass = (status: string) => {
  return `status-${status}`
}

const formatDate = (date: string | null) => {
  if (!date) return 'Tidak dijadwalkan'

  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatCurrency = (value: string | number | null) => {
  if (value === null || value === undefined) {
    return 'Price unavailable'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0
  }).format(Number(value))
}

const formatSize = (size: number) => {
  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(0)} KB`
  }

  return `${(size / (1024 * 1024)).toFixed(2)} MB`
}

const assignmentHistory = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'assigned')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0] || null
})

const actualDeliveredAt = computed(() => {
  const histories = order.value?.status_histories || []

  return histories
    .filter(history => history.new_status === 'delivered')
    .sort((a, b) =>
      new Date(b.created_at).getTime() -
      new Date(a.created_at).getTime()
    )[0]?.created_at || null
})

const updateStatus = async () => {
  if (!order.value || updating.value) return

  const targetStatus = nextStatus[order.value.status]

  if (!targetStatus) return

  updating.value = true
  error.value = ''
  actionMessage.value = ''

  const notes: Record<string, string> = {
    picked_up: 'Barang sudah dijemput kurir.',
    in_transit: 'Barang sedang dalam perjalanan.'
  }

  try {
    await api(
      `/courier/orders/${order.value.id}/status`,
      {
        method: 'PATCH',
        body: {
          status: targetStatus,
          note: notes[targetStatus]
        }
      }
    )

    actionMessage.value =
      `Status berhasil diperbarui menjadi ${statusLabel(targetStatus)}.`

    await fetchOrder()
  } catch (err: any) {
    error.value =
      err?.data?.message || 'Gagal memperbarui status pengiriman.'
  } finally {
    updating.value = false
  }
}

// Kompresi foto di browser.
const compressImage = async (file: File): Promise<File> => {
  const bitmap = await createImageBitmap(file)

  try {
    const maxDimension = 1600

    const scale = Math.min(
      1,
      maxDimension / Math.max(bitmap.width, bitmap.height)
    )

    const width = Math.round(bitmap.width * scale)
    const height = Math.round(bitmap.height * scale)

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height

    const context = canvas.getContext('2d')

    if (!context) {
      throw new Error('Tidak dapat memproses foto.')
    }

    context.drawImage(bitmap, 0, 0, width, height)

    const blob = await new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        result => {
          if (result) resolve(result)
          else reject(new Error('Gagal mengompres foto.'))
        },
        'image/jpeg',
        0.78
      )
    })

    return new File(
      [blob],
      file.name.replace(/\.[^.]+$/, '') + '.jpg',
      {
        type: 'image/jpeg',
        lastModified: Date.now()
      }
    )
  } finally {
    bitmap.close()
  }
}

const handlePhotoChange = async (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  uploadError.value = ''
  actionMessage.value = ''

  if (!file) return

  const allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp'
  ]

  if (!allowedTypes.includes(file.type)) {
    uploadError.value = 'Format foto harus JPG, PNG, atau WebP.'
    input.value = ''
    return
  }

  if (file.size > 20 * 1024 * 1024) {
    uploadError.value = 'Foto asli maksimal 20 MB.'
    input.value = ''
    return
  }

  compressing.value = true
  originalPhotoSize.value = file.size

  try {
    const compressedFile = await compressImage(file)

    if (compressedFile.size > 5 * 1024 * 1024) {
      uploadError.value =
        'Foto setelah kompresi masih melebihi 5 MB.'
      input.value = ''
      return
    }

    if (photoPreview.value) {
      URL.revokeObjectURL(photoPreview.value)
    }

    photoFile.value = compressedFile
    photoPreview.value = URL.createObjectURL(compressedFile)

    actionMessage.value =
      `Foto berhasil dikompres: ${formatSize(file.size)} → ${formatSize(compressedFile.size)}`
  } catch (err: any) {
    uploadError.value =
      err?.message || 'Gagal memproses foto.'
    input.value = ''
  } finally {
    compressing.value = false
  }
}

const removePhoto = () => {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }

  photoFile.value = null
  photoPreview.value = ''
  originalPhotoSize.value = 0
  actionMessage.value = ''
  uploadError.value = ''

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const uploadProof = async () => {
  if (!order.value || uploading.value || compressing.value) return

  uploadError.value = ''

  if (order.value.status !== 'in_transit') {
    uploadError.value =
      'Bukti hanya dapat diunggah saat order dalam perjalanan.'
    return
  }

  if (!photoFile.value) {
    uploadError.value = 'Silakan pilih foto bukti pengiriman.'
    return
  }

  if (!recipientName.value.trim()) {
    uploadError.value = 'Nama penerima wajib diisi.'
    return
  }

  const formData = new FormData()

  formData.append('photo', photoFile.value)
  formData.append('recipient_name', recipientName.value.trim())

  if (proofNotes.value.trim()) {
    formData.append('notes', proofNotes.value.trim())
  }

  uploading.value = true
  actionMessage.value = ''

  try {
    await api(
      `/courier/orders/${order.value.id}/proof`,
      {
        method: 'POST',
        body: formData
      }
    )

    removePhoto()
    recipientName.value = ''
    proofNotes.value = ''

    await fetchOrder()

    actionMessage.value =
      'Bukti berhasil diunggah. Pengiriman telah selesai!'
  } catch (err: any) {
    uploadError.value =
      err?.data?.message || 'Gagal mengunggah bukti pengiriman.'
  } finally {
    uploading.value = false
  }
}

const actionButtonLabel = computed(() => {
  if (!order.value) return ''

  return actionLabels[order.value.status] || ''
})

onMounted(fetchOrder)

onUnmounted(() => {
  if (photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value)
  }
})
</script>



<template>
  <main class="courier-detail">

    <button
      class="courier-back-button"
      @click="navigateTo('/courier')"
    >
      ← Back to Dashboard
    </button>

    <div v-if="loading" class="courier-detail-state">
      Memuat detail pengiriman...
    </div>

    <div v-else-if="error && !order" class="courier-detail-state error">
      {{ error }}

      <button @click="fetchOrder">
        Coba Lagi
      </button>
    </div>

    <template v-else-if="order">

      <header class="courier-detail-header">
        <div>
          <span class="courier-detail-eyebrow">
            COURIER WORKSPACE
          </span>

          <h1>Delivery Detail</h1>
          <p>Informasi tugas pengiriman</p>
        </div>

        <span
          class="courier-detail-status"
          :class="statusClass(order.status)"
        >
          {{ statusLabel(order.status) }}
        </span>
      </header>

      <section class="courier-detail-card order-highlight">
        <span>ORDER NUMBER</span>
        <h2>{{ order.order_number }}</h2>

        <div class="courier-order-meta">
          <span>Order ID: {{ order.id }}</span>
          <span>
            {{ order.delivery_type === 'instant'
              ? 'Instant Delivery'
              : 'Scheduled Delivery' }}
          </span>
        </div>
      </section>

      <!-- DELIVERY PRICE -->
<section class="courier-detail-card courier-price-card">
  <span class="courier-detail-eyebrow">
    PAYMENT INFORMATION
  </span>

  <h3>Delivery Price</h3>

  <div class="courier-info-row">
    <span>Service Type</span>
    <strong>
      {{
        order.pricing_details?.delivery_type === 'instant'
          ? 'Instant'
          : order.pricing_details?.delivery_type === 'scheduled'
            ? 'Scheduled'
            : order.delivery_type === 'instant'
              ? 'Instant'
              : 'Scheduled'
      }}
    </strong>
  </div>

  <template v-if="order.pricing_details">
    <div class="courier-info-row">
      <span>Delivery Distance</span>
      <strong>{{ order.pricing_details.distance_km }} km</strong>
    </div>

    <div class="courier-info-row">
      <span>Base Price</span>
      <strong>{{ formatCurrency(order.pricing_details.base_price) }}</strong>
    </div>

    <div class="courier-info-row">
      <span>Price per KM</span>
      <strong>{{ formatCurrency(order.pricing_details.price_per_km) }}</strong>
    </div>

    <div class="courier-info-row">
      <span>Distance Fee</span>
      <strong>{{ formatCurrency(order.pricing_details.distance_fee) }}</strong>
    </div>

    <div class="courier-info-row">
      <span>Additional Fee</span>
      <strong>{{ formatCurrency(order.pricing_details.additional_fee) }}</strong>
    </div>

    <div class="courier-info-row">
      <span>Minimum Price</span>
      <strong>{{ formatCurrency(order.pricing_details.minimum_price) }}</strong>
    </div>
  </template>

  <div class="courier-price-total">
    <span>Total Delivery Price</span>
    <strong>
      {{ formatCurrency(order.pricing_details?.total_price ?? order.price) }}
    </strong>
  </div>

  <p class="courier-price-note">
    {{
      order.pricing_details
        ? 'Historical pricing snapshot recorded when this order was created.'
        : 'Historical pricing details are unavailable for this order.'
    }}
  </p>
</section>


      <!-- ETA -->
<section class="courier-detail-card eta-card">
  <span class="courier-detail-eyebrow">
    DELIVERY ESTIMATE
  </span>

  <template v-if="order.status === 'delivered'">
    <h3>Delivery Completed</h3>

    <div class="eta-highlight">
      DELIVERED
    </div>

    <div class="eta-info">
      <div class="eta-info-row">
        <span>Actual Delivery Time</span>
        <strong v-if="actualDeliveredAt">
          {{ formatDate(actualDeliveredAt) }}
        </strong>
        <strong v-else>
          Waktu pengiriman tidak tersedia
        </strong>
      </div>
    </div>

    <p class="eta-disclaimer">
      Pesanan telah berhasil diterima dan diselesaikan.
    </p>
  </template>

  <template v-else-if="order.eta?.available">
    <h3>Estimated Arrival</h3>

    <div class="eta-highlight">
      {{ order.eta.duration_minutes }} minutes
    </div>

    <div class="eta-info">
      <div class="eta-info-row">
        <span>Estimated Distance</span>
        <strong>{{ order.eta.distance_km }} km</strong>
      </div>

      <div class="eta-info-row">
        <span>Travel Duration</span>
        <strong>{{ order.eta.duration_minutes }} minutes</strong>
      </div>

      <div class="eta-info-row">
        <span>Estimated Arrival</span>
        <strong>
          {{ formatDate(order.eta.estimated_arrival_at) }}
        </strong>
      </div>
    </div>

    <p class="eta-disclaimer">
      {{ order.eta.message }}
    </p>
  </template>

  <template v-else>
    <h3>Estimated Arrival</h3>

    <p class="eta-unavailable">
      {{ order.eta?.message || 'ETA belum tersedia.' }}
    </p>

    <p class="eta-disclaimer">
      Estimasi tersedia setelah perjalanan dimulai
      dan koordinat pickup serta tujuan tersedia.
    </p>
  </template>
</section>

      <!-- PICKUP -->
      <section class="courier-detail-card">
        <h3>Pickup Information</h3>

        <div class="courier-person">
          <span>Sender</span>
          <strong>{{ order.sender_name }}</strong>
          <p>{{ order.sender_phone }}</p>
        </div>

        <div class="courier-address">
          {{ order.pickup_address }}
        </div>
      </section>

      <!-- DELIVERY -->
      <section class="courier-detail-card">
        <h3>Delivery Information</h3>

        <div class="courier-person">
          <span>Recipient</span>
          <strong>{{ order.recipient_name }}</strong>
          <p>{{ order.recipient_phone }}</p>
        </div>

        <div class="courier-address">
          {{ order.delivery_address }}
        </div>
      </section>

      <!-- ASSIGNMENT -->
      <section
        v-if="assignmentHistory"
        class="courier-detail-card"
      >
        <h3>Assignment Information</h3>

        <div class="courier-info-row">
          <span>Assigned By</span>
          <strong>
            {{ assignmentHistory.changed_by?.name || 'System' }}
          </strong>
        </div>

        <div class="courier-info-row">
          <span>Assignment Time</span>
          <strong>
            {{ formatDate(assignmentHistory.created_at) }}
          </strong>
        </div>

        <div class="courier-notes">
          <span>Assignment Note</span>
          <p>
            {{ assignmentHistory.note || 'Tidak ada catatan.' }}
          </p>
        </div>
      </section>

      <!-- SCHEDULE -->
      <section class="courier-detail-card">
        <h3>Delivery Schedule</h3>

        <div class="courier-info-row">
          <span>Pickup Schedule</span>
          <strong>{{ formatDate(order.scheduled_pickup_at) }}</strong>
        </div>

        <div class="courier-info-row">
          <span>Customer</span>
          <strong>{{ order.customer?.name || '-' }}</strong>
        </div>

        <div v-if="order.notes" class="courier-notes">
          <span>Customer Notes</span>
          <p>{{ order.notes }}</p>
        </div>
      </section>

      <!-- DELIVERY PROGRESS -->
      <section class="courier-action-card">
        <span class="courier-detail-eyebrow">
          DELIVERY PROGRESS
        </span>

        <h3>Update Delivery Status</h3>

        <p v-if="order.status === 'delivered'">
          Pengiriman telah selesai. Terima kasih atas pekerjaanmu!
        </p>

        <p v-else-if="order.status === 'cancelled'">
          Pesanan ini telah dibatalkan.
        </p>

        <p v-else-if="order.status === 'in_transit'">
          Barang sedang dalam perjalanan. Setelah diterima,
          unggah foto bukti pengiriman di bawah.
        </p>

        <p v-else>
          Perbarui status setelah setiap tahap pengiriman
          benar-benar selesai.
        </p>

        <button
          v-if="actionButtonLabel"
          class="courier-action-button"
          :disabled="updating"
          @click="updateStatus"
        >
          {{ updating ? 'Updating...' : actionButtonLabel }}
        </button>

        <div
          v-if="actionMessage"
          class="courier-success-message"
        >
          {{ actionMessage }}
        </div>

        <div
          v-if="error && order"
          class="courier-action-error"
        >
          {{ error }}
        </div>
      </section>

      <!-- UPLOAD PROOF -->
      <section
        v-if="order.status === 'in_transit' && !order.delivery_proof"
        class="courier-detail-card courier-proof-form"
      >
        <span class="courier-detail-eyebrow">
          FINAL DELIVERY STEP
        </span>

        <h3>Upload Proof of Delivery</h3>

        <p class="proof-description">
          Ambil foto atau pilih foto barang yang sudah diterima
          sebagai bukti penyelesaian pengiriman.
        </p>

        <div class="proof-field">
          <label>Delivery Photo *</label>

          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            @change="handlePhotoChange"
          />

          <small>JPG, PNG atau WebP. Foto asli maksimal 20 MB.</small>
        </div>

        <div v-if="compressing" class="proof-processing">
          Mengompres foto...
        </div>

        <div v-if="photoPreview" class="proof-preview">
          <img
            :src="photoPreview"
            alt="Delivery proof preview"
          />

          <div class="proof-size-info">
            <span>Original: {{ formatSize(originalPhotoSize) }}</span>
            <span>Compressed: {{ formatSize(photoFile?.size || 0) }}</span>
          </div>

          <button
            type="button"
            class="proof-remove-button"
            :disabled="uploading || compressing"
            @click="removePhoto"
          >
            Remove Photo
          </button>
        </div>

        <div class="proof-field">
          <label>Recipient Name *</label>

          <input
            v-model="recipientName"
            type="text"
            maxlength="255"
            placeholder="Nama orang yang menerima barang"
            :disabled="uploading"
          />
        </div>

        <div class="proof-field">
          <label>Delivery Notes</label>

          <textarea
            v-model="proofNotes"
            maxlength="1000"
            rows="4"
            placeholder="Catatan tambahan pengiriman (opsional)"
            :disabled="uploading"
          />
        </div>

        <p v-if="uploadError" class="proof-error">
          {{ uploadError }}
        </p>

        <button
          class="proof-submit-button"
          :disabled="uploading || compressing"
          @click="uploadProof"
        >
          {{
            compressing
              ? 'Compressing Photo...'
              : uploading
                ? 'Uploading Proof...'
                : 'Submit Delivery Proof'
          }}
        </button>

        <p class="proof-warning">
          Setelah bukti berhasil diunggah, status order otomatis
          berubah menjadi Delivered dan tidak dapat diunggah ulang.
        </p>
      </section>

      <!-- COMPLETED PROOF -->
      <section
        v-if="order.delivery_proof"
        class="courier-detail-card courier-proof-result"
      >
        <span class="courier-detail-eyebrow">
          DELIVERY CONFIRMATION
        </span>

        <h3>Proof of Delivery</h3>

        <div class="proof-completed-status">
          <span>✓</span>
          Delivery successfully completed
        </div>

        <a
          :href="order.delivery_proof.photo_url"
          target="_blank"
          rel="noopener noreferrer"
        >
          <img
            :src="order.delivery_proof.photo_url"
            alt="Delivery proof photo"
            class="proof-result-image"
          />
        </a>

        <div class="courier-info-row">
          <span>Received By</span>
          <strong>{{ order.delivery_proof.recipient_name }}</strong>
        </div>

        <div class="courier-info-row">
          <span>Uploaded By</span>
          <strong>
            {{ order.delivery_proof.uploaded_by?.name || 'Courier' }}
          </strong>
        </div>

        <div class="courier-info-row">
          <span>Upload Time</span>
          <strong>
            {{ formatDate(order.delivery_proof.created_at) }}
          </strong>
        </div>

        <div
          v-if="order.delivery_proof.notes"
          class="courier-notes"
        >
          <span>Courier Notes</span>
          <p>{{ order.delivery_proof.notes }}</p>
        </div>
      </section>

      <!-- DELIVERY HISTORY -->
      <section class="courier-detail-card">
        <h3>Delivery History</h3>

        <div
          v-if="order.status_histories?.length"
          class="courier-history-list"
        >
          <div
            v-for="history in order.status_histories"
            :key="history.id"
            class="courier-history-item"
          >
            <div class="courier-history-dot"></div>

            <div class="courier-history-content">
              <strong>
                {{ statusLabel(history.new_status) }}
              </strong>

              <p>
                {{ history.note || 'Status diperbarui.' }}
              </p>

              <span>
                {{ history.changed_by?.name || 'System' }}
                · {{ formatDate(history.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <p v-else class="courier-empty-history">
          Belum ada riwayat pengiriman.
        </p>
      </section>

    </template>

  </main>
</template>
