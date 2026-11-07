
<script setup lang="ts">
const route = useRoute()
const { user, logout } = useAuth()

const role = computed(() => user.value?.role?.slug || '')

const homePaths: Record<string, string> = {
  admin: '/admin',
  dispatcher: '/dispatcher',
  courier: '/courier',
  customer: '/customer',
}

const menuItems = computed(() => {
  const home = homePaths[role.value] || '/'

  if (role.value === 'admin') {
    return [
      { label: 'Home', icon: '⌂', path: home },
      { label: 'Orders', icon: '▤', path: '/admin?tab=orders' },
      { label: 'Users', icon: '♙', path: '/admin?tab=users' },
      { label: 'Logout', icon: '⇥', path: 'logout' },
    ]
  }

  if (role.value === 'dispatcher') {
    return [
      { label: 'Home', icon: '⌂', path: home },
      { label: 'Orders', icon: '▤', path: home },
      { label: 'Queue', icon: '☷', path: '/dispatcher?status=pending' },
      { label: 'Logout', icon: '⇥', path: 'logout' },
    ]
  }

  if (role.value === 'courier') {
    return [
      { label: 'Home', icon: '⌂', path: home },
      { label: 'Deliveries', icon: '▤', path: home },
      { label: 'Completed', icon: '✓', path: home },
      { label: 'Logout', icon: '⇥', path: 'logout' },
    ]
  }

  if (role.value === 'customer') {
    return [
      { label: 'Home', icon: '⌂', path: home },
      { label: 'Orders', icon: '▤', path: home },
      { label: 'Create', icon: '+', path: '/customer/create' },
      { label: 'Logout', icon: '⇥', path: 'logout' },
    ]
  }

  return []
})

const visible = computed(() => {
  return !!role.value &&
    !!homePaths[role.value] &&
    route.path.startsWith(homePaths[role.value])
})

const activeMenu = ref('home')

watch(
  () => route.path,
  (path) => {
    if (path === homePaths[role.value]) {
      activeMenu.value = 'home'
    }
  },
  { immediate: true }
)

function isActive(label: string) {
  return activeMenu.value === label.toLowerCase()
}

async function handleClick(path: string, label: string) {
  if (path === 'logout') {
    await logout()
    await navigateTo('/login')
    return
  }

  activeMenu.value = label.toLowerCase()
  await navigateTo(path)
}
</script>

<template>
  <nav
    v-if="visible"
    class="role-bottom-nav"
    aria-label="Main navigation"
  >
    <button
      v-for="item in menuItems"
      :key="item.label"
      type="button"
      class="role-nav-item"
      :class="{ active: isActive(item.label) }"
      @click="handleClick(item.path, item.label)"
    >
      <span class="role-nav-icon">{{ item.icon }}</span>
      <small>{{ item.label }}</small>
    </button>
  </nav>
</template>
