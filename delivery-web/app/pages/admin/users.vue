
<script setup lang="ts">
definePageMeta({
  middleware: 'auth'
})

interface UserRole {
  id: number
  name: string
  slug: string
}

interface AdminUser {
  id: number
  name: string
  email: string
  role_id: number
  role: UserRole | null
  created_at: string
}

interface UsersResponse {
  message: string
  data: {
    data: AdminUser[]
    current_page: number
    last_page: number
    total: number
  }
}

const api = useApi()

const users = ref<AdminUser[]>([])
const loading = ref(true)
const errorMessage = ref('')

const selectedRole = ref('all')
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalUsers = ref(0)
const selectedUser = ref<AdminUser | null>(null)

const fetchUsers = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api<UsersResponse>('/admin/users', {
      query: {
        role: selectedRole.value,
        page: currentPage.value
      }
    })

    users.value = response.data.data || []
    currentPage.value = response.data.current_page || 1
    lastPage.value = response.data.last_page || 1
    totalUsers.value = response.data.total || 0

    if (
      selectedUser.value &&
      !users.value.some(user => user.id === selectedUser.value?.id)
    ) {
      selectedUser.value = null
    }
  } catch (error: any) {
    errorMessage.value =
      error?.data?.message || 'Unable to load users.'
  } finally {
    loading.value = false
  }
}

const filteredUsers = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()

  if (!keyword) return users.value

  return users.value.filter(user =>
    user.name.toLowerCase().includes(keyword) ||
    user.email.toLowerCase().includes(keyword)
  )
})

const changeRole = async (role: string) => {
  selectedRole.value = role
  currentPage.value = 1
  selectedUser.value = null
  await fetchUsers()
}

const changePage = async (page: number) => {
  if (page < 1 || page > lastPage.value) return

  currentPage.value = page
  selectedUser.value = null
  await fetchUsers()
}

const toggleUser = (user: AdminUser) => {
  selectedUser.value =
    selectedUser.value?.id === user.id ? null : user
}

const roleLabel = (role: string | undefined) => {
  const labels: Record<string, string> = {
    admin: 'Admin',
    dispatcher: 'Dispatcher',
    courier: 'Courier',
    customer: 'Customer'
  }

  return labels[role || ''] || role || 'Unknown'
}

const formatDate = (date: string) => {
  if (!date) return '-'

  return new Date(date).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

onMounted(fetchUsers)
</script>

<template>
  <main class="admin-users-page">

    <header class="topbar">
      <div class="brand">
        <div class="brand-icon">☾</div>
        <div>
          <h1>KURIR<span>MALAM</span></h1>
          <p>DELIVERY MANAGEMENT</p>
        </div>
      </div>

      <NuxtLink to="/admin" class="logout-btn">
        ← Home
      </NuxtLink>
    </header>

    <section class="welcome">
      <span class="eyebrow">ADMINISTRATION</span>
      <h2>User<br /><span>Management.</span></h2>
      <p>Monitor and manage registered platform users.</p>
    </section>

    <section class="admin-users-summary">
      <article class="stat-card">
        <span class="stat-icon">♙</span>
        <p>TOTAL USERS</p>
        <h3>{{ totalUsers }}</h3>
        <small>Registered platform users</small>
      </article>
    </section>

    <section class="admin-users-content">

      <div class="section-heading">
        <h3>All Users</h3>
        <span>USER DIRECTORY</span>
      </div>

      <input
        v-model="searchQuery"
        class="admin-users-search"
        type="search"
        placeholder="Search name or email..."
      />

      <div class="admin-users-filters">
        <button
          v-for="role in [
            { label: 'All', value: 'all' },
            { label: 'Admin', value: 'admin' },
            { label: 'Dispatcher', value: 'dispatcher' },
            { label: 'Courier', value: 'courier' },
            { label: 'Customer', value: 'customer' }
          ]"
          :key="role.value"
          :class="{ active: selectedRole === role.value }"
          @click="changeRole(role.value)"
        >
          {{ role.label }}
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        Loading users...
      </div>

      <div v-else-if="errorMessage" class="empty-state">
        <h4>Unable to load users</h4>
        <p>{{ errorMessage }}</p>
        <button @click="fetchUsers">Try again</button>
      </div>

      <div v-else-if="filteredUsers.length === 0" class="empty-state">
        <h4>No users found</h4>
        <p>Try another search or role filter.</p>
      </div>

      <div v-else class="admin-user-list">

        <article
          v-for="user in filteredUsers"
          :key="user.id"
          class="admin-user-card"
        >
          <button
            class="admin-user-select"
            @click="toggleUser(user)"
          >
            <div class="admin-user-avatar">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>

            <div class="admin-user-list-info">
              <strong>{{ user.name }}</strong>
              <small>{{ user.email }}</small>
              <span class="admin-user-role">
                {{ roleLabel(user.role?.slug) }}
              </span>
            </div>

            <span class="admin-row-arrow">
              {{ selectedUser?.id === user.id ? '−' : '+' }}
            </span>
          </button>

          <div
            v-if="selectedUser?.id === user.id"
            class="admin-user-detail"
          >
            <h3>User Information</h3>

            <p><strong>User ID:</strong> {{ user.id }}</p>
            <p><strong>Name:</strong> {{ user.name }}</p>
            <p><strong>Email:</strong> {{ user.email }}</p>
            <p><strong>Role:</strong> {{ roleLabel(user.role?.slug) }}</p>
            <p><strong>Registered:</strong> {{ formatDate(user.created_at) }}</p>
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

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>

  </main>
</template>
