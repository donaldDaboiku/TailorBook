import { useState } from 'react'
import { useAuth } from '../auth'

export default function HomePage() {
  const { user, logout } = useAuth()
  const [busy, setBusy] = useState(false)
  const shop = user?.business?.name ?? 'your shop'

  async function onLogout() {
    setBusy(true)
    try {
      await logout()
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <section className="card">
        <h1>Good day, {user?.name}</h1>
        <p className="lede">{shop} is ready. Customers come next.</p>
      </section>

      <section className="card muted-card">
        <p className="status">Signed in as {user?.email}</p>
        <button
          type="button"
          className="secondary"
          onClick={onLogout}
          disabled={busy}
        >
          {busy ? 'Signing out…' : 'Sign out'}
        </button>
      </section>
    </>
  )
}
