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
  setDemoAccount,
  setToken,
  type User,
} from './api'
import { syncNow } from './outbox'
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
    state: string
    measurement_unit: 'cm' | 'in'
    currency: string
    invite_code?: string
    adult: boolean
    privacy: boolean
    terms: boolean
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
          setDemoAccount(me.is_demo_account)
          setUser(me)
        }
      } catch {
        if (!cancelled) {
          setDemoAccount(false)
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
    setDemoAccount(result.user.is_demo_account)
    setUser(result.user)
    if (!result.user.is_demo_account) {
      void syncNow()
    }
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    business_name: string
    phone: string
    country: string
    state: string
    measurement_unit: 'cm' | 'in'
    currency: string
    invite_code?: string
    adult: boolean
    privacy: boolean
    terms: boolean
  }) {
    const result = await apiRegister(payload)
    markTutorialUnseen(result.user.id)
    setDemoAccount(result.user.is_demo_account)
    setUser(result.user)
    if (!result.user.is_demo_account) {
      void syncNow()
    }
  }

  async function logout() {
    await apiLogout()
    setDemoAccount(false)
    setUser(null)
  }

  function clearUser() {
    setDemoAccount(false)
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
