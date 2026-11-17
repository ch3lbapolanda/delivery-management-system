
<script setup lang="ts">
interface NotificationData {
  type: string
  title: string
  message: string
  order_id: number
  order_number: string
  data?: Record<string, any>
}

interface AppNotification {
  id: string
  type: string
  data: NotificationData
  read_at: string | null
  created_at: string
}

interface NotificationResponse {
  message: string
  data: {
    data: AppNotification[]
  }
  unread_count: number
}

interface UnreadCountResponse {
  data: {
    count: number
  }
}

const api = useApi()
const { user } = useAuth()
const nuxtApp = useNuxtApp() as any
const echo = nuxtApp.$echo

const notifications = ref<AppNotification[]>([])
const unreadCount = ref(0)
const loading = ref(false)
const opened = ref(false)
const errorMessage = ref('')

let pollingTimer: ReturnType<typeof setInterval> | null = null
let channelName: string | null = null

const fetchNotifications = async () => {
  try {
    const response = await api<NotificationResponse>(
      '/notifications'
    )

    notifications.value = response.data.data || []
    unreadCount.value = response.unread_count || 0
    errorMessage.value = ''
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to load notifications.'
  }
}

const fetchUnreadCount = async () => {
  try {
    const response = await api<UnreadCountResponse>(
      '/notifications/unread-count'
    )

    unreadCount.value = response.data.count
  } catch {
    // Polling berikutnya akan mencoba kembali.
  }
}

const markAsRead = async (notification: AppNotification) => {
  if (notification.read_at) return

  try {
    await api(`/notifications/${notification.id}/read`, {
      method: 'PATCH'
    })

    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to update notification.'
  }
}

const markAllAsRead = async () => {
  if (unreadCount.value === 0) return

  try {
    await api('/notifications/read-all', {
      method: 'PATCH'
    })

    notifications.value.forEach((notification) => {
      notification.read_at ||= new Date().toISOString()
    })

    unreadCount.value = 0
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Failed to update notifications.'
  }
}

const handleRealtimeNotification = (event: any) => {
  const incoming: AppNotification = {
    id: event.id,
    type: event.type,
    data: {
      type: event.type,
      title: event.title || 'Order Update',
      message: event.message || 'You have a new notification.',
      order_id: event.order_id,
      order_number: event.order_number,
      data: event.data || {}
    },
    read_at: null,
    created_at: new Date().toISOString()
  }

  if (!notifications.value.some(item => item.id === incoming.id)) {
    notifications.value.unshift(incoming)
    notifications.value = notifications.value.slice(0, 20)
    unreadCount.value += 1
  }
}

const openNotifications = async () => {
  opened.value = !opened.value

  if (opened.value) {
    loading.value = true
    await fetchNotifications()
    loading.value = false
  }
}

const handleNotificationClick = async (
  notification: AppNotification
) => {
  await markAsRead(notification)

  if (!notification.data.order_id) return

  const role = user.value?.role.slug
  const orderId = notification.data.order_id

  opened.value = false

  if (role === 'dispatcher') {
    await navigateTo(`/dispatcher/orders/${orderId}`)
  } else if (role === 'customer') {
    await navigateTo(`/customer/orders/${orderId}`)
  } else if (role === 'courier') {
    await navigateTo(`/courier/orders/${orderId}`)
  } else if (role === 'admin') {
    await navigateTo('/admin/orders')
  }
}

const formatNotificationDate = (date: string) => {
  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(async () => {
  if (!user.value) return

  await fetchNotifications()

  if (echo) {
    channelName = `App.Models.User.${user.value.id}`

    echo.private(channelName)
      .notification(handleRealtimeNotification)
  }

  pollingTimer = setInterval(() => {
    fetchUnreadCount()

    if (opened.value) {
      fetchNotifications()
    }
  }, 30000)
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }

  if (channelName && echo) {
    echo.leave(channelName)
    channelName = null
  }
})
</script>

<template>
  <div class="notification-wrapper">
    <button
      class="notification-trigger"
      type="button"
      aria-label="Notifications"
      @click="openNotifications"
    >
      <span class="notification-icon">♧</span>

      <span
        v-if="unreadCount > 0"
        class="notification-badge"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="opened"
      class="notification-panel"
    >
      <header class="notification-header">
        <div>
          <strong>Notifications</strong>
          <small>{{ unreadCount }} unread</small>
        </div>

        <button
          v-if="unreadCount > 0"
          type="button"
          class="notification-mark-all"
          @click="markAllAsRead"
        >
          Mark all read
        </button>
      </header>

      <div v-if="loading" class="notification-state">
        Loading notifications...
      </div>

      <div
        v-else-if="errorMessage && notifications.length === 0"
        class="notification-state"
      >
        {{ errorMessage }}
        <button @click="fetchNotifications">
          Retry
        </button>
      </div>

      <div
        v-else-if="notifications.length === 0"
        class="notification-state"
      >
        <span>♧</span>
        <p>No notifications yet.</p>
      </div>

      <div v-else class="notification-list">
        <button
          v-for="notification in notifications"
          :key="notification.id"
          type="button"
          class="notification-item"
          :class="{ unread: !notification.read_at }"
          @click="handleNotificationClick(notification)"
        >
          <span class="notification-item-dot"></span>

          <span class="notification-item-content">
            <strong>{{ notification.data.title }}</strong>
            <span>{{ notification.data.message }}</span>
            <small>
              {{ notification.data.order_number }}
              · {{ formatNotificationDate(notification.created_at) }}
            </small>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notification-wrapper {
  position: relative;
  display: inline-flex;
  z-index: 100;
}

.notification-trigger {
  position: relative;
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px;
  background: rgba(255,255,255,.04);
  color: inherit;
  cursor: pointer;
}

.notification-icon {
  font-size: 23px;
}

.notification-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 19px;
  height: 19px;
  padding: 0 5px;
  display: grid;
  place-items: center;
  border-radius: 20px;
  background: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
}

.notification-panel {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: min(350px, calc(100vw - 32px));
  max-height: 430px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 15px;
  background: #151923;
  color: #f1f5f9;
  box-shadow: 0 15px 45px rgba(0,0,0,.4);
}

.notification-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 16px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}

.notification-header div {
  display: grid;
  gap: 4px;
}

.notification-header small,
.notification-item small {
  color: #94a3b8;
  font-size: 11px;
}

.notification-mark-all {
  border: 0;
  background: transparent;
  color: #60a5fa;
  font-size: 11px;
  cursor: pointer;
}

.notification-list {
  overflow-y: auto;
}

.notification-item {
  width: 100%;
  display: flex;
  gap: 11px;
  padding: 14px 16px;
  border: 0;
  border-bottom: 1px solid rgba(255,255,255,.06);
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.notification-item.unread {
  background: rgba(59,130,246,.08);
}

.notification-item-dot {
  flex: 0 0 8px;
  width: 8px;
  height: 8px;
  margin-top: 5px;
  border-radius: 50%;
  background: #64748b;
}

.notification-item.unread .notification-item-dot {
  background: #3b82f6;
}

.notification-item-content {
  min-width: 0;
  display: grid;
  gap: 5px;
}

.notification-item-content strong {
  font-size: 13px;
}

.notification-item-content span {
  color: #cbd5e1;
  font-size: 12px;
  line-height: 1.5;
}

.notification-item-content small {
  font-size: 10px;
}

.notification-state {
  padding: 28px 16px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}

.notification-state button {
  display: block;
  margin: 10px auto 0;
  border: 0;
  background: transparent;
  color: #60a5fa;
  cursor: pointer;
}

@media (max-width: 480px) {
  .notification-panel {
    position: fixed;
    top: 75px;
    right: 12px;
    width: calc(100vw - 24px);
  }
}
</style>
