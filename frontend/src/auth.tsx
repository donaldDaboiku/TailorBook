import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import {
  fetchMe,
  getToken,
  login as apiLogin,
  logout as apiLogout,
  register as apiRegister,
  setToken,
  type User,
} from './api'
import { markTutorialUnseen } from './tutorial'

type AuthContextValue = {
  user: User | null
  appName: string
  loading: boolean
  offline: boolean
  login: (email: string, password: string) => Promise<void>
  register: (payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    business_name: string
    phone: string
    country: string
    measurement_unit: 'cm' | 'in'
    currency: string
  }) => Promise<void>
  setUser: (user: User) => void
  clearUser: () => void
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({
  children,
  appName,
  offline,
}: {
  children: ReactNode
  appName: string
  offline: boolean
}) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    async function boot() {
      if (!getToken()) {
        if (!cancelled) {
          setLoading(false)
        }
        return
      }

      try {
        const me = await fetchMe()
        if (!cancelled) {
          setUser(me)
        }
      } catch {
        if (!cancelled) {
          setToken(null)
          setUser(null)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void boot()

    return () => {
      cancelled = true
    }
  }, [])

  async function login(email: string, password: string) {
    const result = await apiLogin({ email, password })
    setUser(result.user)
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    business_name: string
    phone: string
    country: string
    measurement_unit: 'cm' | 'in'
    currency: string
  }) {
    const result = await apiRegister(payload)
    markTutorialUnseen(result.user.id)
    setUser(result.user)
  }

  async function logout() {
    await apiLogout()
    setUser(null)
  }

  function clearUser() {
    setToken(null)
    setUser(null)
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        appName,
        loading,
        offline,
        login,
        register,
        setUser,
        clearUser,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext)

  if (!value) {
    throw new Error('useAuth needs AuthProvider')
  }

  return value
}
