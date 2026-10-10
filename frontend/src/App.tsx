import { useEffect, useRef, useState, type ReactNode } from 'react'
import {
  fetchHealth,
  firstError,
  verifyBillingPayment,
  type Customer,
  type Measurement,
} from './api'
import { AuthProvider, useAuth } from './auth'
import AdminShopsPage from './pages/AdminShopsPage'
import CustomerDetailPage from './pages/CustomerDetailPage'
import CustomerFormPage from './pages/CustomerFormPage'
import CustomersPage from './pages/CustomersPage'
import ExpenseFormPage from './pages/ExpenseFormPage'
import ExpensesPage from './pages/ExpensesPage'
import HomePage from './pages/HomePage'
import JobFormPage from './pages/JobFormPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import HowToUseTutorial from './pages/HowToUseTutorial'
import LandingPage from './pages/LandingPage'
import LoginPage from './pages/LoginPage'
import MeasurementDetailPage from './pages/MeasurementDetailPage'
import MeasurementFormPage from './pages/MeasurementFormPage'
import MorePage from './pages/MorePage'
import PaymentFormPage from './pages/PaymentFormPage'
import RegisterPage from './pages/RegisterPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import SubscriptionBillingCard from './pages/SubscriptionBillingCard'
import WhatsAppComposePage from './pages/WhatsAppComposePage'
import { hasSeenTutorial, markTutorialSeen } from './tutorial'

type Gate = 'loading' | 'ready' | 'offline'
type Tab = 'home' | 'customers' | 'finance' | 'more'
type Screen =
  | { name: 'home' }
  | { name: 'customers' }
  | { name: 'customer-create' }
  | { name: 'customer-detail'; id: string }
  | { name: 'customer-edit'; customer: Customer }
  | { name: 'measurement-create'; customer: Customer; seed?: Measurement | null }
  | { name: 'measurement-detail'; customer: Customer; measurementId: string }
  | { name: 'job-create'; customer: Customer }
  | { name: 'payment-create'; customer: Customer; jobId?: string }
  | { name: 'whatsapp'; customer: Customer }
  | { name: 'expenses' }
  | { name: 'expense-create' }
  | { name: 'more' }

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
  const { user, loading, appName, setUser, logout } = useAuth()
  const [authScreen, setAuthScreen] = useState<
    'landing' | 'login' | 'register' | 'forgot' | 'reset'
  >(() => {
    const params = new URLSearchParams(window.location.search)
    return params.get('reset') === '1' ? 'reset' : 'landing'
  })
  const [resetEmail, setResetEmail] = useState(() => {
    const params = new URLSearchParams(window.location.search)
    return params.get('email') ?? ''
  })
  const [resetToken, setResetToken] = useState(() => {
    const params = new URLSearchParams(window.location.search)
    return params.get('token') ?? ''
  })
  const [screen, setScreen] = useState<Screen>({ name: 'home' })
  const [billingMessage, setBillingMessage] = useState('')
  const [billingError, setBillingError] = useState('')
  const [billingBusy, setBillingBusy] = useState(false)
  const [showTutorial, setShowTutorial] = useState(false)
  const wasSignedIn = useRef(false)

  useEffect(() => {
    if (user) {
      wasSignedIn.current = true
      return
    }

    if (!wasSignedIn.current) {
      return
    }

    wasSignedIn.current = false
    const params = new URLSearchParams(window.location.search)
    if (params.get('reset') !== '1') {
      setAuthScreen('landing')
    }
  }, [user])

  useEffect(() => {
    if (!user || user.role === 'admin') {
      setShowTutorial(false)
      return
    }

    setShowTutorial(!hasSeenTutorial(user.id))
  }, [user?.id, user?.role])

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    if (params.get('reset') !== '1') {
      return
    }

    setResetEmail(params.get('email') ?? '')
    setResetToken(params.get('token') ?? '')
    setAuthScreen('reset')
    window.history.replaceState({}, '', window.location.pathname)
  }, [])

  useEffect(() => {
    if (!user || user.role === 'admin') {
      return
    }

    const params = new URLSearchParams(window.location.search)
    if (params.get('billing') !== '1') {
      return
    }

    const reference = params.get('reference') || params.get('trxref')
    window.history.replaceState({}, '', window.location.pathname)

    if (!reference) {
      return
    }

    let cancelled = false
    setBillingBusy(true)
    setBillingError('')
    setBillingMessage('Confirming Paystack payment…')

    verifyBillingPayment(reference)
      .then((next) => {
        if (cancelled) {
          return
        }
        setUser(next)
        setBillingMessage('Subscription activated. Thank you.')
        setScreen({ name: 'more' })
      })
      .catch((err) => {
        if (!cancelled) {
          setBillingError(firstError(err))
          setBillingMessage('')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setBillingBusy(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [user?.id, user?.role, setUser])

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
        {authScreen === 'landing' ? (
          <LandingPage
            onCreate={() => setAuthScreen('register')}
            onSignIn={() => setAuthScreen('login')}
          />
        ) : authScreen === 'register' ? (
          <RegisterPage onShowLogin={() => setAuthScreen('login')} />
        ) : authScreen === 'forgot' ? (
          <ForgotPasswordPage onShowLogin={() => setAuthScreen('login')} />
        ) : authScreen === 'reset' && resetEmail && resetToken ? (
          <ResetPasswordPage
            email={resetEmail}
            token={resetToken}
            onDone={() => setAuthScreen('login')}
          />
        ) : (
          <LoginPage
            onShowRegister={() => setAuthScreen('register')}
            onShowForgot={() => setAuthScreen('forgot')}
          />
        )}
      </Shell>
    )
  }

  if (user.role === 'admin') {
    return (
      <Shell appName={appName} signedOut>
        <AdminShopsPage />
      </Shell>
    )
  }

  if (showTutorial) {
    return (
      <Shell appName={appName} signedOut>
        <HowToUseTutorial
          onDone={() => {
            markTutorialSeen(user.id)
            setShowTutorial(false)
          }}
        />
      </Shell>
    )
  }

  if (user.subscription_access === 'expired') {
    return (
      <Shell appName={appName} signedOut>
        <section className="card">
          <h1>Subscription expired</h1>
          <p className="lede">
            Renew your shop plan to open customers, jobs, and expenses again.
          </p>
          {billingBusy && <p className="status">{billingMessage}</p>}
          {billingMessage && !billingBusy && (
            <p className="status">{billingMessage}</p>
          )}
          {billingError && (
            <p className="form-error" role="alert">
              {billingError}
            </p>
          )}
        </section>
        <SubscriptionBillingCard title="Renew plan" />
        <section className="card muted-card">
          <button type="button" className="secondary" onClick={() => logout()}>
            Sign out
          </button>
        </section>
      </Shell>
    )
  }

  const tab: Tab =
    screen.name === 'home'
      ? 'home'
      : screen.name === 'expenses' || screen.name === 'expense-create'
        ? 'finance'
        : screen.name === 'more'
          ? 'more'
          : 'customers'

  return (
    <Shell
      appName={appName}
      tab={tab}
      onTabChange={(next) => {
        if (next === 'home') {
          setScreen({ name: 'home' })
        } else if (next === 'finance') {
          setScreen({ name: 'expenses' })
        } else if (next === 'more') {
          setScreen({ name: 'more' })
        } else {
          setScreen({ name: 'customers' })
        }
      }}
    >
      {(billingBusy || billingMessage || billingError) && (
        <section className="card muted-card">
          {billingBusy && <p className="status">{billingMessage}</p>}
          {billingMessage && !billingBusy && (
            <p className="status">{billingMessage}</p>
          )}
          {billingError && (
            <p className="form-error" role="alert">
              {billingError}
            </p>
          )}
        </section>
      )}

      {screen.name === 'home' && (
        <HomePage
          onAddCustomer={() => setScreen({ name: 'customer-create' })}
          onAddExpense={() => setScreen({ name: 'expense-create' })}
        />
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
          onTakeMeasurements={(customer) =>
            setScreen({ name: 'measurement-create', customer })
          }
          onOpenMeasurement={(customer, measurementId) =>
            setScreen({ name: 'measurement-detail', customer, measurementId })
          }
          onDuplicateMeasurement={(customer, measurement) =>
            setScreen({
              name: 'measurement-create',
              customer,
              seed: measurement,
            })
          }
          onAddJob={(customer) => setScreen({ name: 'job-create', customer })}
          onAddPayment={(customer, jobId) =>
            setScreen({ name: 'payment-create', customer, jobId })
          }
          onWhatsApp={(customer) => setScreen({ name: 'whatsapp', customer })}
        />
      )}

      {screen.name === 'whatsapp' && (
        <WhatsAppComposePage
          customer={screen.customer}
          onBack={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
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

      {screen.name === 'measurement-create' && (
        <MeasurementFormPage
          customer={screen.customer}
          seed={screen.seed}
          onCancel={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
          onSaved={(measurement) =>
            setScreen({
              name: 'measurement-detail',
              customer: screen.customer,
              measurementId: measurement.id,
            })
          }
        />
      )}

      {screen.name === 'measurement-detail' && (
        <MeasurementDetailPage
          customer={screen.customer}
          measurementId={screen.measurementId}
          onBack={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
          onDuplicate={(measurement) =>
            setScreen({
              name: 'measurement-create',
              customer: screen.customer,
              seed: measurement,
            })
          }
        />
      )}

      {screen.name === 'job-create' && (
        <JobFormPage
          customer={screen.customer}
          onCancel={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
          onSaved={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
        />
      )}

      {screen.name === 'payment-create' && (
        <PaymentFormPage
          customer={screen.customer}
          preferredJobId={screen.jobId}
          onCancel={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
          onSaved={() =>
            setScreen({ name: 'customer-detail', id: screen.customer.id })
          }
        />
      )}

      {screen.name === 'expenses' && (
        <ExpensesPage onCreate={() => setScreen({ name: 'expense-create' })} />
      )}

      {screen.name === 'expense-create' && (
        <ExpenseFormPage
          onCancel={() => setScreen({ name: 'expenses' })}
          onSaved={() => setScreen({ name: 'expenses' })}
        />
      )}

      {screen.name === 'more' && (
        <MorePage onShowTutorial={() => setShowTutorial(true)} />
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
          <button
            type="button"
            aria-current={tab === 'finance' ? 'page' : undefined}
            onClick={() => onTabChange?.('finance')}
          >
            Finance
          </button>
          <button
            type="button"
            aria-current={tab === 'more' ? 'page' : undefined}
            onClick={() => onTabChange?.('more')}
          >
            More
          </button>
        </nav>
      )}
    </div>
  )
}
