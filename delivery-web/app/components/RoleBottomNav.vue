
<script setup lang="ts">
const route = useRoute()
const { user, logout } = useAuth()

const role = computed(() => user.value?.role?.slug || '')

const menuItems = computed(() => {
  if (role.value === 'admin') {
    return [
      { label: 'Home', icon: '⌂', path: '/admin' },
      { label: 'Orders', icon: '▤', path: '/admin/orders' },
      { label: 'Users', icon: '♙', path: '/admin/users' },
      { label: 'Logout', icon: '⇥', path: 'logout' }
    ]
  }

  if (role.value === 'dispatcher') {
    return [
      { label: 'Home', icon: '⌂', path: '/dispatcher' },
      { label: 'Orders', icon: '▤', path: '/dispatcher?view=all' },
      { label: 'Queue', icon: '☷', path: '/dispatcher?view=queue' },
      { label: 'Logout', icon: '⇥', path: 'logout' }
    ]
  }

  if (role.value === 'courier') {
    return [
      { label: 'Home', icon: '⌂', path: '/courier' },
      { label: 'Deliveries', icon: '▤', path: '/courier?view=active' },
      { label: 'Completed', icon: '✓', path: '/courier?view=completed' },
      { label: 'Logout', icon: '⇥', path: 'logout' }
    ]
  }

  if (role.value === 'customer') {
    return [
      { label: 'Home', icon: '⌂', path: '/customer' },
      { label: 'Orders', icon: '▤', path: '/customer/orders' },
      { label: 'Create', icon: '+', path: '/customer/create' },
      { label: 'Logout', icon: '⇥', path: 'logout' }
    ]
  }

  return []
})

const visible = computed(() => {
  const paths: Record<string, string> = {
    admin: '/admin',
    dispatcher: '/dispatcher',
    courier: '/courier',
    customer: '/customer'
  }

  const home = paths[role.value]

  return !!home && route.path.startsWith(home)
})

function isActive(label: string) {
  const key = label.toLowerCase()

  if (role.value === 'admin') {
    if (key === 'home') return route.path === '/admin'
    if (key === 'orders') return route.path.startsWith('/admin/orders')
    if (key === 'users') return route.path.startsWith('/admin/users')
  }

  if (role.value === 'customer') {
    if (key === 'home') return route.path === '/customer'
    if (key === 'orders') return route.path.startsWith('/customer/orders')
    if (key === 'create') return route.path === '/customer/create'
  }

  if (role.value === 'dispatcher') {
    if (key === 'home') return route.path === '/dispatcher' && !route.query.view
    if (key === 'orders') return route.path === '/dispatcher' && route.query.view === 'all'
    if (key === 'queue') return route.path === '/dispatcher' && route.query.view === 'queue'
  }

  if (role.value === 'courier') {
    if (key === 'home') return route.path === '/courier' && !route.query.view
    if (key === 'deliveries') return route.path === '/courier' && route.query.view === 'active'
    if (key === 'completed') return route.path === '/courier' && route.query.view === 'completed'
  }

  return false
}

async function handleClick(path: string) {
  if (path === 'logout') {
    await logout()
    await navigateTo('/login')
    return
  }

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
      @click="handleClick(item.path)"
    >
      <span class="role-nav-icon">{{ item.icon }}</span>
      <small>{{ item.label }}</small>
    </button>
  </nav>
</template>


