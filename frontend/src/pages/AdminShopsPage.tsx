import { useEffect, useState, type FormEvent } from 'react'
import {
  firstError,
  listAdminShops,
  resetAdminShopPassword,
  suspendAdminShop,
  unsuspendAdminShop,
  updateAdminShopSubscription,
  type AdminShop,
  type SubscriptionStatus,
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

  function replaceShop(updated: AdminShop) {
    setShops((current) =>
      current.map((row) => (row.id === updated.id ? updated : row)),
    )
  }

  async function onToggleSuspend(shop: AdminShop) {
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
      const updated = shop.suspended
        ? await unsuspendAdminShop(shop.id)
        : await suspendAdminShop(shop.id)
      replaceShop(updated)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setActionId(null)
    }
  }

  async function onSaveSubscription(
    shop: AdminShop,
    status: SubscriptionStatus,
    until: string,
  ) {
    setActionId(shop.id)
    setError('')

    try {
      const updated = await updateAdminShopSubscription(shop.id, {
        subscription_status: status,
        subscribed_until: status === 'subscribed' ? until : null,
      })
      replaceShop(updated)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setActionId(null)
    }
  }

  async function onResetPassword(
    shop: AdminShop,
    password: string,
    passwordConfirmation: string,
  ) {
    setActionId(shop.id)
    setError('')

    try {
      await resetAdminShopPassword(shop.id, {
        password,
        password_confirmation: passwordConfirmation,
      })
      window.alert(
        `Password updated for ${shop.email}. Tell the owner the new password securely (not by public chat if you can avoid it).`,
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
        <ShopCard
          key={shop.id}
          shop={shop}
          busy={actionId === shop.id}
          onToggleSuspend={() => void onToggleSuspend(shop)}
          onSaveSubscription={(status, until) =>
            void onSaveSubscription(shop, status, until)
          }
          onResetPassword={(password, confirmation) =>
            void onResetPassword(shop, password, confirmation)
          }
        />
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

function ShopCard({
  shop,
  busy,
  onToggleSuspend,
  onSaveSubscription,
  onResetPassword,
}: {
  shop: AdminShop
  busy: boolean
  onToggleSuspend: () => void
  onSaveSubscription: (status: SubscriptionStatus, until: string) => void
  onResetPassword: (password: string, confirmation: string) => void
}) {
  const [status, setStatus] = useState<SubscriptionStatus>(
    shop.subscription_status === 'subscribed' ? 'subscribed' : 'free',
  )
  const [until, setUntil] = useState(shop.subscribed_until ?? defaultUntil())
  const [newPassword, setNewPassword] = useState('')
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('')

  useEffect(() => {
    setStatus(shop.subscription_status === 'subscribed' ? 'subscribed' : 'free')
    setUntil(shop.subscribed_until ?? defaultUntil())
  }, [shop.id, shop.subscription_status, shop.subscribed_until])

  function onSubmit(event: FormEvent) {
    event.preventDefault()
    onSaveSubscription(status, until)
  }

  function onSubmitPassword(event: FormEvent) {
    event.preventDefault()
    onResetPassword(newPassword, newPasswordConfirmation)
    setNewPassword('')
    setNewPasswordConfirmation('')
  }

  return (
    <section className="card muted-card">
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
        <div>
          <dt>Subscription</dt>
          <dd>
            <span className={subscriptionBadgeClass(shop.subscription_access)}>
              {subscriptionLabel(shop.subscription_access)}
            </span>
            {shop.subscribed_until ? ` · until ${shop.subscribed_until}` : ''}
          </dd>
        </div>
      </dl>

      <form className="form" onSubmit={onSubmit}>
        <fieldset className="unit-field">
          <legend>Plan</legend>
          <label className="choice">
            <input
              type="radio"
              name={`plan-${shop.id}`}
              checked={status === 'free'}
              onChange={() => setStatus('free')}
            />
            Free
          </label>
          <label className="choice">
            <input
              type="radio"
              name={`plan-${shop.id}`}
              checked={status === 'subscribed'}
              onChange={() => setStatus('subscribed')}
            />
            Subscribed
          </label>
        </fieldset>

        {status === 'subscribed' && (
          <label>
            Subscribed until
            <input
              type="date"
              value={until}
              onChange={(event) => setUntil(event.target.value)}
              required
            />
          </label>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Saving…' : 'Save subscription'}
        </button>
      </form>

      <form className="form" onSubmit={onSubmitPassword}>
        <h3 className="section-title">Reset password</h3>
        <p className="status">
          Set a temporary password, then tell the owner privately.
        </p>
        <label>
          New password
          <input
            type="password"
            autoComplete="new-password"
            value={newPassword}
            onChange={(event) => setNewPassword(event.target.value)}
            required
            minLength={8}
          />
        </label>
        <label>
          Confirm password
          <input
            type="password"
            autoComplete="new-password"
            value={newPasswordConfirmation}
            onChange={(event) => setNewPasswordConfirmation(event.target.value)}
            required
            minLength={8}
          />
        </label>
        <button type="submit" className="secondary" disabled={busy}>
          {busy ? 'Saving…' : 'Set new password'}
        </button>
      </form>

      <button
        type="button"
        className={shop.suspended ? 'primary' : 'secondary danger'}
        onClick={onToggleSuspend}
        disabled={busy}
      >
        {busy
          ? 'Updating…'
          : shop.suspended
            ? 'Activate shop'
            : 'Suspend shop'}
      </button>
    </section>
  )
}

function defaultUntil(): string {
  const date = new Date()
  date.setMonth(date.getMonth() + 1)
  return date.toISOString().slice(0, 10)
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

function subscriptionLabel(access: AdminShop['subscription_access']): string {
  switch (access) {
    case 'subscribed':
      return 'Subscribed'
    case 'expired':
      return 'Expired'
    case 'admin':
      return 'Admin'
    default:
      return 'Free'
  }
}

function subscriptionBadgeClass(
  access: AdminShop['subscription_access'],
): string {
  if (access === 'expired') {
    return 'badge danger'
  }
  if (access === 'subscribed') {
    return 'badge ok'
  }
  return 'badge'
}
