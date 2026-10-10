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
import JobsPage from './pages/JobsPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import HowToUseTutorial from './pages/HowToUseTutorial'
import LandingPage from './pages/LandingPage'
import LoginPage from './pages/LoginPage'
import MeasurementDetailPage from './pages/MeasurementDetailPage'
import MeasurementFormPage from './pages/MeasurementFormPage'
import MorePage from './pages/MorePage'
import PaymentFormPage from './pages/PaymentFormPage'
import PickCustomerPage from './pages/PickCustomerPage'
import RegisterPage from './pages/RegisterPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import SubscriptionBillingCard from './pages/SubscriptionBillingCard'
import WhatsAppComposePage from './pages/WhatsAppComposePage'
import { hasSeenTutorial, markTutorialSeen } from './tutorial'

type Gate = 'loading' | 'ready' | 'offline'
type Tab = 'home' | 'customers' | 'jobs' | 'finance' | 'more'
type Screen =
  | { name: 'home' }
  | { name: 'jobs' }
  | { name: 'pick-customer'; purpose: 'job' | 'payment' }
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
    screen.name === 'home' || screen.name === 'pick-customer'
      ? 'home'
      : screen.name === 'jobs'
        ? 'jobs'
        : screen.name === 'expenses' || screen.name === 'expense-create'
          ? 'finance'
          : screen.name === 'more'
            ? 'more'
            : 'customers'

  return (
    <Shell
      appName={appName}
      hideHeader={screen.name === 'home'}
      tab={tab}
      onTabChange={(next) => {
        if (next === 'home') {
          setScreen({ name: 'home' })
        } else if (next === 'jobs') {
          setScreen({ name: 'jobs' })
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
          onAddJob={() => setScreen({ name: 'pick-customer', purpose: 'job' })}
          onAddPayment={() =>
            setScreen({ name: 'pick-customer', purpose: 'payment' })
          }
          onAddExpense={() => setScreen({ name: 'expense-create' })}
          onOpenJobs={() => setScreen({ name: 'jobs' })}
          onOpenJob={(job) =>
            setScreen({ name: 'customer-detail', id: job.customer_id })
          }
          onOpenMore={() => setScreen({ name: 'more' })}
        />
      )}

      {screen.name === 'jobs' && (
        <JobsPage
          onBack={() => setScreen({ name: 'home' })}
          onOpen={(job) =>
            setScreen({ name: 'customer-detail', id: job.customer_id })
          }
        />
      )}

      {screen.name === 'pick-customer' && (
        <PickCustomerPage
          purpose={screen.purpose}
          onCancel={() => setScreen({ name: 'home' })}
          onCreate={() => setScreen({ name: 'customer-create' })}
          onPick={(customer) =>
            setScreen(
              screen.purpose === 'job'
                ? { name: 'job-create', customer }
                : { name: 'payment-create', customer },
            )
          }
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
  hideHeader = false,
  tab = 'home',
  onTabChange,
}: {
  appName: string
  children: ReactNode
  signedOut?: boolean
  hideHeader?: boolean
  tab?: Tab
  onTabChange?: (tab: Tab) => void
}) {
  return (
    <div className={signedOut ? 'app signed-out' : 'app'}>
      {!hideHeader && (
        <header className="topbar">
          <p className="app-name">{appName || 'Shop'}</p>
        </header>
      )}

      <main>{children}</main>

      {signedOut && (
        <footer className="builder-credit">Built by gitsystemng</footer>
      )}

      {!signedOut && (
        <div className="tab-dock">
          <footer className="builder-credit">Built by gitsystemng</footer>
          <nav className="tabbar" aria-label="Main">
          <TabButton
            label="Home"
            current={tab === 'home'}
            onClick={() => onTabChange?.('home')}
            icon="home"
          />
          <TabButton
            label="Clients"
            current={tab === 'customers'}
            onClick={() => onTabChange?.('customers')}
            icon="clients"
          />
          <TabButton
            label="Jobs"
            current={tab === 'jobs'}
            onClick={() => onTabChange?.('jobs')}
            icon="jobs"
          />
          <TabButton
            label="Finance"
            current={tab === 'finance'}
            onClick={() => onTabChange?.('finance')}
            icon="finance"
          />
          <TabButton
            label="More"
            current={tab === 'more'}
            onClick={() => onTabChange?.('more')}
            icon="more"
          />
        </nav>
        </div>
      )}
    </div>
  )
}

function TabButton({
  label,
  current,
  onClick,
  icon,
}: {
  label: string
  current: boolean
  onClick: () => void
  icon: 'home' | 'clients' | 'jobs' | 'finance' | 'more'
}) {
  return (
    <button
      type="button"
      aria-current={current ? 'page' : undefined}
      onClick={onClick}
    >
      <TabIcon name={icon} />
      {label}
    </button>
  )
}

function TabIcon({
  name,
}: {
  name: 'home' | 'clients' | 'jobs' | 'finance' | 'more'
}) {
  const common = {
    width: 20,
    height: 20,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
  }

  if (name === 'home') {
    return (
      <svg {...common}>
        <path d="M4 11.5 12 4l8 7.5" />
        <path d="M7 10.5V20h10v-9.5" />
      </svg>
    )
  }
  if (name === 'clients') {
    return (
      <svg {...common}>
        <circle cx="9" cy="8" r="2.5" />
        <circle cx="16" cy="9" r="2" />
        <path d="M4.5 18c.8-2.4 2.4-3.5 4.5-3.5s3.7 1.1 4.5 3.5" />
        <path d="M14 14.6c1.3-.4 2.5-.2 3.6.7 1 1 1.6 2 1.9 2.7" />
      </svg>
    )
  }
  if (name === 'jobs') {
    return (
      <svg {...common}>
        <path d="M8 7V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1" />
        <path d="M4 7h16v12H4z" />
      </svg>
    )
  }
  if (name === 'finance') {
    return (
      <svg {...common}>
        <rect x="3" y="6" width="18" height="12" rx="2" />
        <path d="M3 10h18" />
      </svg>
    )
  }
  return (
    <svg {...common}>
      <path d="M6 7h12M6 12h12M6 17h12" />
    </svg>
  )
}
