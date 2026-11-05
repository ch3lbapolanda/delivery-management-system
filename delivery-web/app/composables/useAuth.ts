interface User {
  id: number
  name: string
  email: string
  role: {
    id: number
    name: string
    slug: string
  }
}

interface LoginResponse {
  message: string
  user: User
  token: string
}

interface MeResponse {
  user: User
}

export const useAuth = () => {
  const user = useState<User | null>('auth-user', () => null)
  const token = useState<string | null>('auth-token', () => null)

  const api = useApi()

  // LOGIN
  const login = async (email: string, password: string) => {
    const response = await api<LoginResponse>('/login', {
      method: 'POST',
      body: { email, password }
    })

    token.value = response.token
    user.value = response.user

    if (import.meta.client) {
      localStorage.setItem('auth-token', response.token)
    }

    return response
  }

  // RESTORE SESSION
  const restoreSession = async () => {
    if (!import.meta.client) return

    const savedToken = localStorage.getItem('auth-token')

    if (!savedToken) return

    token.value = savedToken

    try {
      const response = await api<MeResponse>('/me')
      user.value = response.user
    } catch {
      token.value = null
      user.value = null
      localStorage.removeItem('auth-token')
    }
  }

  // LOGOUT
  const logout = async () => {
    try {
      if (token.value) {
        await api('/logout', {
          method: 'POST'
        })
      }
    } finally {
      token.value = null
      user.value = null

      if (import.meta.client) {
        localStorage.removeItem('auth-token')
      }
    }
  }

  return {
    user,
    token,
    login,
    logout,
    restoreSession
  }
}