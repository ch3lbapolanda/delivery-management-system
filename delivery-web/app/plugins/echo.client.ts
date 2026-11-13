
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

export default defineNuxtPlugin(() => {
  window.Pusher = Pusher

  const config = useRuntimeConfig()
  const { token } = useAuth()
  const reverbStatus = useState<string>(
  'reverb-status',
  () => 'connecting'
)

  const echo = new Echo({
    broadcaster: 'reverb',
    key: config.public.reverbKey,
    wsHost: config.public.reverbHost,
    wsPort: config.public.reverbPort,
    wssPort: config.public.reverbPort,
    forceTLS: false,
    enabledTransports: ['ws'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Accept: 'application/json'
      }
    },
    authorizer: (channel: any) => ({
  authorize: (
    socketId: string,
    callback: (error: Error | null, data: any) => void
  ) => {
        $fetch('/api/broadcasting/auth', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token.value || localStorage.getItem('auth-token') || ''}`
          },
          body: {
            socket_id: socketId,
            channel_name: channel.name
          }
        })
          .then((data) => callback(null, data))
.catch((error) => callback(
  error instanceof Error ? error : new Error(String(error)),
  null
))
      }
    })
  })
  const connection = (echo.connector as any)?.pusher?.connection

if (connection) {
  reverbStatus.value = connection.state

  connection.bind(
    'state_change',
    (states: { current: string }) => {
      reverbStatus.value = states.current
    }
  )
}

  return {
  provide: {
    echo,
    reverbStatus: readonly(reverbStatus)
  }
  }
})
