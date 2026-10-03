import { useEffect, useState, type ReactNode } from 'react'
import { fetchHealth, type Customer, type Measurement } from './api'
import { AuthProvider, useAuth } from './auth'
import CustomerDetailPage from './pages/CustomerDetailPage'
import CustomerFormPage from './pages/CustomerFormPage'
import CustomersPage from './pages/CustomersPage'
import ExpenseFormPage from './pages/ExpenseFormPage'
import ExpensesPage from './pages/ExpensesPage'
import HomePage from './pages/HomePage'
import JobFormPage from './pages/JobFormPage'
import LoginPage from './pages/LoginPage'
import MeasurementDetailPage from './pages/MeasurementDetailPage'
import MeasurementFormPage from './pages/MeasurementFormPage'
import PaymentFormPage from './pages/PaymentFormPage'
import RegisterPage from './pages/RegisterPage'
import WhatsAppComposePage from './pages/WhatsAppComposePage'

type Gate = 'loading' | 'ready' | 'offline'
type Tab = 'home' | 'customers' | 'finance'
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
    screen.name === 'home'
      ? 'home'
      : screen.name === 'expenses' || screen.name === 'expense-create'
        ? 'finance'
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
        } else {
          setScreen({ name: 'customers' })
        }
      }}
    >
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
          <button type="button" disabled>
            More
          </button>
        </nav>
      )}
    </div>
  )
}
