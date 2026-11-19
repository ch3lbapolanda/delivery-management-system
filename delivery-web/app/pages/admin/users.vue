
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
  is_active: boolean
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

type UserFormMode = 'create' | 'edit'

const api = useApi()
const { user: currentUser } = useAuth()

const users = ref<AdminUser[]>([])
const loading = ref(true)
const errorMessage = ref('')

const selectedRole = ref('all')
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalUsers = ref(0)
const selectedUser = ref<AdminUser | null>(null)

const showUserForm = ref(false)
const formMode = ref<UserFormMode>('create')
const submitting = ref(false)
const formError = ref('')

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'courier'
})

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
    } else if (selectedUser.value) {
      selectedUser.value =
        users.value.find(user => user.id === selectedUser.value?.id) || null
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

const openCreateForm = () => {
  formMode.value = 'create'
  form.value = {
    name: '',
    email: '',
    password: '',
    role: 'courier'
  }
  formError.value = ''
  showUserForm.value = true
}

const openEditForm = (user: AdminUser) => {
  if (user.role?.slug === 'admin') return

  formMode.value = 'edit'
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role?.slug === 'dispatcher' ? 'dispatcher' : 'courier'
  }
  formError.value = ''
  showUserForm.value = true
}

const closeUserForm = () => {
  if (submitting.value) return

  showUserForm.value = false
  formError.value = ''
}

const saveUser = async () => {
  formError.value = ''

  if (!form.value.name.trim()) {
    formError.value = 'Name is required.'
    return
  }

  if (!form.value.email.trim()) {
    formError.value = 'Email is required.'
    return
  }

  if (
    formMode.value === 'create' &&
    form.value.password.length < 8
  ) {
    formError.value = 'Password must contain at least 8 characters.'
    return
  }

  if (!['dispatcher', 'courier'].includes(form.value.role)) {
    formError.value = 'Please select a valid role.'
    return
  }

  submitting.value = true

  try {
    const payload: Record<string, string> = {
      name: form.value.name.trim(),
      email: form.value.email.trim(),
      role: form.value.role
    }

    if (formMode.value === 'create') {
      payload.password = form.value.password

      await api('/admin/users', {
        method: 'POST',
        body: payload
      })
    } else if (selectedUser.value) {
      await api(`/admin/users/${selectedUser.value.id}`, {
        method: 'PUT',
        body: payload
      })
    }

    showUserForm.value = false
    selectedUser.value = null
    searchQuery.value = ''
    selectedRole.value = 'all'
    currentPage.value = 1

    await fetchUsers()
  } catch (error: any) {
    const errors = error?.data?.errors

    if (errors) {
      formError.value = Object.values(errors)
        .flat()
        .join(' ')
    } else {
      formError.value =
        error?.data?.message || 'Unable to save user.'
    }
  } finally {
    submitting.value = false
  }
}

const toggleUserStatus = async (user: AdminUser) => {
  const action = user.is_active ? 'menonaktifkan' : 'mengaktifkan'

  if (!confirm(`Yakin ingin ${action} akun ${user.name}?`)) return

  try {
    await api(`/admin/users/${user.id}/status`, {
      method: 'PATCH',
      body: {
        is_active: !user.is_active
      }
    })

    await fetchUsers()
  } catch (error: any) {
    alert(error?.data?.message || `Gagal ${action} akun.`)
  }
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

  return new Date(date).toLocaleDateString('id-ID', {
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
        <div>
          <h3>All Users</h3>
          <span>USER DIRECTORY</span>
        </div>

        <button
          type="button"
          class="admin-add-user-button"
          @click="openCreateForm"
        >
          + Add User
        </button>
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
            type="button"
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

              <span
                class="admin-user-status"
                :class="user.is_active ? 'active' : 'inactive'"
              >
                {{ user.is_active ? 'ACTIVE' : 'INACTIVE' }}
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
            <p><strong>Status:</strong> {{ user.is_active ? 'Active' : 'Inactive' }}</p>
            <p><strong>Registered:</strong> {{ formatDate(user.created_at) }}</p>

            <div
              v-if="user.role?.slug !== 'admin' && user.id !== currentUser?.id"
              class="admin-user-actions"
            >
              <button
                type="button"
                class="admin-user-edit-button"
                @click.stop="openEditForm(user)"
              >
                Edit User
              </button>

              <button
                type="button"
                class="admin-user-status-button"
                :class="user.is_active ? 'deactivate' : 'activate'"
                @click.stop="toggleUserStatus(user)"
              >
                {{ user.is_active ? 'Deactivate Account' : 'Activate Account' }}
              </button>
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

    <footer>
      <span class="status-dot"></span>
      SYSTEM OPERATIONAL
      <span class="copyright">© 2026 KURIR MALAM</span>
    </footer>

    <!-- USER FORM MODAL -->
    <Teleport to="body">
      <div
        v-if="showUserForm"
        class="admin-user-modal-overlay"
        @click.self="closeUserForm"
      >
        <section class="admin-user-modal">
          <div class="admin-user-modal-header">
            <div>
              <span class="eyebrow">
                USER MANAGEMENT
              </span>

              <h2>
                {{ formMode === 'create' ? 'Add New User' : 'Edit User' }}
              </h2>

              <p>
                {{
                  formMode === 'create'
                    ? 'Create a dispatcher or courier account.'
                    : 'Update user account information.'
                }}
              </p>
            </div>

            <button
              type="button"
              class="admin-user-modal-close"
              :disabled="submitting"
              @click="closeUserForm"
            >
              ×
            </button>
          </div>

          <form class="admin-user-form" @submit.prevent="saveUser">

            <div class="admin-user-form-field">
              <label for="user-name">Full Name</label>
              <input
                id="user-name"
                v-model="form.name"
                type="text"
                placeholder="Enter full name"
                required
                maxlength="255"
              />
            </div>

            <div class="admin-user-form-field">
              <label for="user-email">Email Address</label>
              <input
                id="user-email"
                v-model="form.email"
                type="email"
                placeholder="Enter email address"
                required
                maxlength="255"
              />
            </div>

            <div
              v-if="formMode === 'create'"
              class="admin-user-form-field"
            >
              <label for="user-password">Password</label>
              <input
                id="user-password"
                v-model="form.password"
                type="password"
                placeholder="Minimum 8 characters"
                minlength="8"
                required
              />
            </div>

            <div class="admin-user-form-field">
              <label for="user-role">Account Role</label>
              <select
                id="user-role"
                v-model="form.role"
                required
              >
                <option value="dispatcher">Dispatcher</option>
                <option value="courier">Courier</option>
              </select>
            </div>

            <div v-if="formError" class="admin-user-form-error">
              {{ formError }}
            </div>

            <div class="admin-user-form-actions">
              <button
                type="button"
                class="admin-user-cancel-button"
                :disabled="submitting"
                @click="closeUserForm"
              >
                Cancel
              </button>

              <button
                type="submit"
                class="admin-user-save-button"
                :disabled="submitting"
              >
                {{
                  submitting
                    ? 'Saving...'
                    : formMode === 'create'
                      ? 'Create Account'
                      : 'Save Changes'
                }}
              </button>
            </div>

          </form>
        </section>
      </div>
    </Teleport>

  </main>
</template>

