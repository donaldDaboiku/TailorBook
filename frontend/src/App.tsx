import { useEffect, useState } from 'react'
import { fetchHealth } from './api'

type Status = 'loading' | 'ready' | 'offline'

export default function App() {
  const [status, setStatus] = useState<Status>('loading')
  const [appName, setAppName] = useState('')

  useEffect(() => {
    let cancelled = false

    fetchHealth()
      .then((health) => {
        if (cancelled) {
          return
        }

        setAppName(health.app)
        document.title = health.app
        setStatus('ready')
      })
      .catch(() => {
        if (!cancelled) {
          setStatus('offline')
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <div className="app">
      <header className="topbar">
        <p className="app-name">{appName || 'Shop'}</p>
      </header>

      <main>
        {status === 'loading' && <p className="status">Checking the server…</p>}

        {status === 'ready' && (
          <section className="card">
            <h1>{appName}</h1>
            <p>Server is connected.</p>
          </section>
        )}

        {status === 'offline' && (
          <section className="card">
            <h1>Can't reach the server.</h1>
            <p>Start the API, then refresh this page.</p>
          </section>
        )}
      </main>

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
    </div>
  )
}
