import { useEffect, useState } from 'react'
import { fetchHome, firstError, type HomeDashboard, type ShopJob } from '../api'
import { useAuth } from '../auth'

function timeGreeting(now = new Date()): string {
  const hour = now.getHours()
  if (hour < 12) {
    return 'Good morning'
  }
  if (hour < 17) {
    return 'Good afternoon'
  }
  return 'Good evening'
}

export default function HomePage({
  onAddCustomer,
  onAddJob,
  onAddPayment,
  onAddExpense,
  onOpenJobs,
  onOpenJob,
  onOpenMore,
}: {
  onAddCustomer: () => void
  onAddJob: () => void
  onAddPayment: () => void
  onAddExpense: () => void
  onOpenJobs: () => void
  onOpenJob: (job: ShopJob) => void
  onOpenMore: () => void
}) {
  const { user, appName } = useAuth()
  const [home, setHome] = useState<HomeDashboard | null>(null)
  const [error, setError] = useState('')
  const initial = (user?.name ?? 'S').trim().charAt(0).toUpperCase()

  useEffect(() => {
    let cancelled = false

    fetchHome()
      .then((next) => {
        if (!cancelled) {
          setHome(next)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <div className="dash">
      <header className="dash-head">
        <div>
          <p className="app-name">{appName || 'TailorMate'}</p>
          <h1>{timeGreeting()}!</h1>
          <p className="status">Let’s organize your shop.</p>
        </div>
        <button
          type="button"
          className="avatar"
          onClick={onOpenMore}
          aria-label="Account"
        >
          {initial}
        </button>
      </header>

      <section className="balance-card">
        <p className="balance-label">Outstanding balances</p>
        <p className="balance-amount">
          {home?.outstanding_label ?? '—'}
        </p>
        <p className="balance-note">Still owed across open jobs.</p>
        <div className="balance-stats">
          <div>
            <strong>{home?.active_jobs ?? '—'}</strong>
            <span>Active jobs</span>
          </div>
          <div>
            <strong>{home?.due_soon ?? '—'}</strong>
            <span>Due soon</span>
          </div>
        </div>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      <div className="dash-grid">
        <button type="button" className="dash-tile accent" onClick={onAddCustomer}>
          <TileIcon name="person" />
          <span className="dash-tile-title">New customer</span>
          <span className="dash-tile-note">Save details & measurements</span>
        </button>
        <button type="button" className="dash-tile" onClick={onAddJob}>
          <TileIcon name="job" />
          <span className="dash-tile-title">New job</span>
          <span className="dash-tile-note">Record an outfit order</span>
        </button>
        <button type="button" className="dash-tile" onClick={onAddPayment}>
          <TileIcon name="pay" />
          <span className="dash-tile-title">Record payment</span>
          <span className="dash-tile-note">Deposit or final payment</span>
        </button>
        <button type="button" className="dash-tile" onClick={onAddExpense}>
          <TileIcon name="expense" />
          <span className="dash-tile-title">Add expense</span>
          <span className="dash-tile-note">Track shop spending</span>
        </button>
      </div>

      <section className="card dash-upcoming">
        <div className="row-between">
          <h2 className="section-title">Upcoming jobs</h2>
          <button type="button" className="link" onClick={onOpenJobs}>
            View all
          </button>
        </div>
        <button type="button" className="week-chip" onClick={onOpenJobs}>
          <span>
            <strong>Jobs due this week</strong>
            <span className="status">Check delivery dates and fittings</span>
          </span>
          <span aria-hidden="true">›</span>
        </button>
        {(home?.upcoming_jobs.length ?? 0) === 0 ? (
          <p className="status">No upcoming jobs to display.</p>
        ) : (
          <ul className="admin-list">
            {home?.upcoming_jobs.map((job) => (
              <li key={job.id}>
                <button
                  type="button"
                  className="admin-list-row"
                  onClick={() => onOpenJob(job)}
                >
                  <span className="admin-list-main">
                    <strong>{job.title}</strong>
                    <span className="status">
                      {job.customer_name ?? 'Customer'} · {job.service_date}
                    </span>
                  </span>
                  <span className="status">{job.outstanding_label}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

function TileIcon({ name }: { name: 'person' | 'job' | 'pay' | 'expense' }) {
  const common = {
    width: 22,
    height: 22,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
  }

  if (name === 'person') {
    return (
      <svg {...common}>
        <circle cx="12" cy="8" r="3" />
        <path d="M5 19c1.5-3 3.8-4.5 7-4.5S17.5 16 19 19" />
      </svg>
    )
  }

  if (name === 'job') {
    return (
      <svg {...common}>
        <path d="M8 7V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1" />
        <path d="M4 7h16v12H4z" />
        <path d="M4 12h16" />
      </svg>
    )
  }

  if (name === 'pay') {
    return (
      <svg {...common}>
        <rect x="3" y="6" width="18" height="12" rx="2" />
        <path d="M3 10h18" />
      </svg>
    )
  }

  return (
    <svg {...common}>
      <path d="M6 3h9l3 3v15H6z" />
      <path d="M15 3v4h4" />
      <path d="M8 13h8M8 17h5" />
    </svg>
  )
}
