import { useEffect, useState, type ReactNode } from 'react'
import { fetchHealth } from './api'
import { AuthProvider, useAuth } from './auth'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'

type Gate = 'loading' | 'ready' | 'offline'

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
  const [screen, setScreen] = useState<'login' | 'register'>('login')

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
        {screen === 'login' ? (
          <LoginPage onShowRegister={() => setScreen('register')} />
        ) : (
          <RegisterPage onShowLogin={() => setScreen('login')} />
        )}
      </Shell>
    )
  }

  return (
    <Shell appName={appName}>
      <HomePage />
    </Shell>
  )
}

function Shell({
  appName,
  children,
  signedOut = false,
}: {
  appName: string
  children: ReactNode
  signedOut?: boolean
}) {
  return (
    <div className="app">
      <header className="topbar">
        <p className="app-name">{appName || 'Shop'}</p>
      </header>

      <main>{children}</main>

      {!signedOut && (
        <nav className="tabbar" aria-label="Main">
          <button type="button" aria-current="page">
            Home
          </button>
          <button type="button" disabled>
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
