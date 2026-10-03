import { useEffect, useState, type ReactNode } from 'react'
import { fetchHealth, type Customer } from './api'
import { AuthProvider, useAuth } from './auth'
import CustomerDetailPage from './pages/CustomerDetailPage'
import CustomerFormPage from './pages/CustomerFormPage'
import CustomersPage from './pages/CustomersPage'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'

type Gate = 'loading' | 'ready' | 'offline'
type Tab = 'home' | 'customers'
type Screen =
  | { name: 'home' }
  | { name: 'customers' }
  | { name: 'customer-create' }
  | { name: 'customer-detail'; id: string }
  | { name: 'customer-edit'; customer: Customer }

export default function App() {
  const [gate, setGate] = useState<Gate>('loading')
  const [appName, setAppName] = useState('Shop')

  useEffect(() => {
    let cancelled = false

    fetchHealth()
      .then((health) => {
        if (cancelled) {
          return
        }

        setAppName(health.app)
        document.title = health.app
        setGate('ready')
      })
      .catch(() => {
        if (!cancelled) {
          setGate('offline')
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  if (gate === 'loading') {
    return (
      <Shell appName={appName}>
        <p className="status">Checking the server…</p>
      </Shell>
    )
  }

  if (gate === 'offline') {
    return (
      <Shell appName={appName}>
        <section className="card">
          <h1>Can't reach the server.</h1>
          <p>Start the API, then refresh this page.</p>
        </section>
      </Shell>
    )
  }

  return (
    <AuthProvider appName={appName} offline={false}>
      <AuthenticatedApp />
    </AuthProvider>
  )
}

function AuthenticatedApp() {
  const { user, loading, appName } = useAuth()
  const [authScreen, setAuthScreen] = useState<'login' | 'register'>('login')
  const [screen, setScreen] = useState<Screen>({ name: 'home' })

  if (loading) {
    return (
      <Shell appName={appName}>
        <p className="status">Loading your shop…</p>
      </Shell>
    )
  }

  if (!user) {
    return (
      <Shell appName={appName} signedOut>
        {authScreen === 'login' ? (
          <LoginPage onShowRegister={() => setAuthScreen('register')} />
        ) : (
          <RegisterPage onShowLogin={() => setAuthScreen('login')} />
        )}
      </Shell>
    )
  }

  const tab: Tab =
    screen.name === 'home' ? 'home' : 'customers'

  return (
    <Shell
      appName={appName}
      tab={tab}
      onTabChange={(next) => {
        setScreen(next === 'home' ? { name: 'home' } : { name: 'customers' })
      }}
    >
      {screen.name === 'home' && (
        <HomePage onAddCustomer={() => setScreen({ name: 'customer-create' })} />
      )}

      {screen.name === 'customers' && (
        <CustomersPage
          onOpen={(id) => setScreen({ name: 'customer-detail', id })}
          onCreate={() => setScreen({ name: 'customer-create' })}
        />
      )}

      {screen.name === 'customer-create' && (
        <CustomerFormPage
          onCancel={() => setScreen({ name: 'customers' })}
          onSaved={(customer) =>
            setScreen({ name: 'customer-detail', id: customer.id })
          }
        />
      )}

      {screen.name === 'customer-detail' && (
        <CustomerDetailPage
          id={screen.id}
          onBack={() => setScreen({ name: 'customers' })}
          onEdit={(customer) => setScreen({ name: 'customer-edit', customer })}
          onArchived={() => setScreen({ name: 'customers' })}
        />
      )}

      {screen.name === 'customer-edit' && (
        <CustomerFormPage
          customer={screen.customer}
          onCancel={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
          onSaved={(customer) =>
            setScreen({ name: 'customer-detail', id: customer.id })
          }
        />
      )}
    </Shell>
  )
}

function Shell({
  appName,
  children,
  signedOut = false,
  tab = 'home',
  onTabChange,
}: {
  appName: string
  children: ReactNode
  signedOut?: boolean
  tab?: Tab
  onTabChange?: (tab: Tab) => void
}) {
  return (
    <div className="app">
      <header className="topbar">
        <p className="app-name">{appName || 'Shop'}</p>
      </header>

      <main>{children}</main>

      {!signedOut && (
        <nav className="tabbar" aria-label="Main">
          <button
            type="button"
            aria-current={tab === 'home' ? 'page' : undefined}
            onClick={() => onTabChange?.('home')}
          >
            Home
          </button>
          <button
            type="button"
            aria-current={tab === 'customers' ? 'page' : undefined}
            onClick={() => onTabChange?.('customers')}
          >
            Customers
          </button>
          <button type="button" disabled>
            Finance
          </button>
          <button type="button" disabled>
            More
          </button>
        </nav>
      )}
    </div>
  )
}
