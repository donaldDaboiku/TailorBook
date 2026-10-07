import { useEffect, useState } from 'react'
import {
  firstError,
  listAdminShops,
  suspendAdminShop,
  unsuspendAdminShop,
  type AdminShop,
} from '../api'
import { useAuth } from '../auth'

export default function AdminShopsPage() {
  const { user, logout, appName } = useAuth()
  const [shops, setShops] = useState<AdminShop[]>([])
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [actionId, setActionId] = useState<number | null>(null)

  async function reload(search = q) {
    setLoading(true)
    try {
      const rows = await listAdminShops(search)
      setShops(rows)
      setError('')
    } catch (err) {
      setError(firstError(err))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void reload('')
  }, [])

  async function onLogout() {
    setBusy(true)
    try {
      await logout()
    } finally {
      setBusy(false)
    }
  }

  async function onToggleSuspend(shop: AdminShop) {
    const nextAction = shop.suspended ? 'activate' : 'suspend'
    if (
      !window.confirm(
        shop.suspended
          ? `Activate ${shop.shop_name ?? shop.email}? They can sign in again.`
          : `Suspend ${shop.shop_name ?? shop.email}? They will be signed out and cannot log in.`,
      )
    ) {
      return
    }

    setActionId(shop.id)
    setError('')

    try {
      const updated =
        nextAction === 'suspend'
          ? await suspendAdminShop(shop.id)
          : await unsuspendAdminShop(shop.id)

      setShops((current) =>
        current.map((row) => (row.id === updated.id ? updated : row)),
      )
    } catch (err) {
      setError(firstError(err))
    } finally {
      setActionId(null)
    }
  }

  return (
    <>
      <section className="card">
        <h1>Admin</h1>
        <p className="lede">
          {appName} shops. Signed in as {user?.email}.
        </p>
        <label className="search-label">
          Search
          <input
            value={q}
            onChange={(event) => setQ(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                void reload(q)
              }
            }}
            placeholder="Shop, owner, email, or phone"
          />
        </label>
        <button
          type="button"
          className="primary"
          onClick={() => void reload(q)}
          disabled={loading}
        >
          Search
        </button>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      {loading && <p className="status">Loading shops…</p>}

      {!loading && shops.length === 0 && (
        <section className="card muted-card">
          <p>No shops found.</p>
        </section>
      )}

      {shops.map((shop) => (
        <section key={shop.id} className="card muted-card">
          <div className="row-between">
            <h2 className="section-title">{shop.shop_name ?? 'No shop name'}</h2>
            <span className={shop.suspended ? 'badge danger' : 'badge ok'}>
              {shop.suspended ? 'Suspended' : 'Active'}
            </span>
          </div>
          <dl className="details">
            <div>
              <dt>Owner</dt>
              <dd>{shop.owner_name}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{shop.email}</dd>
            </div>
            <div>
              <dt>Phone</dt>
              <dd>{shop.phone ?? '—'}</dd>
            </div>
            <div>
              <dt>Joined</dt>
              <dd>{formatWhen(shop.created_at)}</dd>
            </div>
            <div>
              <dt>Last login</dt>
              <dd>{formatWhen(shop.last_login_at)}</dd>
            </div>
          </dl>
          <button
            type="button"
            className={shop.suspended ? 'primary' : 'secondary danger'}
            onClick={() => void onToggleSuspend(shop)}
            disabled={actionId === shop.id}
          >
            {actionId === shop.id
              ? 'Updating…'
              : shop.suspended
                ? 'Activate shop'
                : 'Suspend shop'}
          </button>
        </section>
      ))}

      <section className="card muted-card">
        <button
          type="button"
          className="secondary danger"
          onClick={onLogout}
          disabled={busy}
        >
          {busy ? 'Signing out…' : 'Sign out'}
        </button>
      </section>
    </>
  )
}

function formatWhen(value: string | null): string {
  if (!value) {
    return 'Never'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString()
}
