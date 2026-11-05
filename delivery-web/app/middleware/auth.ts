export default defineNuxtRouteMiddleware(async (to) => {
  // Token localStorage hanya tersedia di browser.
  if (import.meta.server) return

  const { user, restoreSession } = useAuth()

  await restoreSession()

  if (!user.value) {
    return navigateTo('/')
  }

  const roleRoutes: Record<string, string> = {
    admin: '/admin',
    dispatcher: '/dispatcher',
    courier: '/courier',
    customer: '/customer'
  }

  const allowedRoute = roleRoutes[user.value.role.slug]

  if (!allowedRoute) {
    return navigateTo('/')
  }

  if (
    to.path !== allowedRoute &&
    !to.path.startsWith(`${allowedRoute}/`)
  ) {
    return navigateTo(allowedRoute)
  }
})