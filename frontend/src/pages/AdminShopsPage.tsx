import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  fetchPresence,
  firstError,
  listAdminFeedback,
  listAdminReviews,
  updateAdminReview,
  listAdminPayments,
  listAdminShops,
  resetAdminShopPassword,
  suspendAdminShop,
  unsuspendAdminShop,
  updateAdminCataloguePrice,
  updateAdminReferral,
  approveAdminReferral,
  rejectAdminReferral,
  createAdminSalesperson,
  listAdminReferrals,
  updateAdminShopState,
  updateAdminShopSubscription,
  type AdminCatalogueOffer,
  type AdminPayment,
  type AdminReferralOffer,
  type AdminReferralActivity,
  type AdminReferralReward,
  type AdminSalesperson,
  type AdminShopReview,
  type ReviewStatus,
  type FeedbackNote,
  type AdminShop,
  type PaymentStatus,
  type SubscriptionStatus,
} from '../api'
import { useAuth } from '../auth'
import AdminReferralsPage from './AdminReferralsPage'

function AdminDesk({
  desk,
  pending,
  onChange,
}: {
  desk: 'shops' | 'referrals'
  pending: number
  onChange: (next: 'shops' | 'referrals') => void
}) {
  return (
    <nav className="admin-nav" aria-label="Admin pages">
      <button
        type="button"
        aria-pressed={desk === 'shops'}
        onClick={() => onChange('shops')}
      >
        Shops
      </button>
      <button
        type="button"
        aria-pressed={desk === 'referrals'}
        onClick={() => onChange('referrals')}
      >
        Referrals{pending > 0 ? ` · ${pending}` : ''}
      </button>
    </nav>
  )
}

export default function AdminShopsPage() {
  const { user, logout, appName } = useAuth()
  const [shops, setShops] = useState<AdminShop[]>([])
  const [payments, setPayments] = useState<AdminPayment[]>([])
  const [feedback, setFeedback] = useState<FeedbackNote[]>([])
  const [reviews, setReviews] = useState<AdminShopReview[]>([])
  const [reviewQuery, setReviewQuery] = useState('')
  const [reviewStatus, setReviewStatus] = useState<ReviewStatus | ''>('')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [q, setQ] = useState('')
  const [paymentFilter, setPaymentFilter] = useState<PaymentStatus | ''>('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [actionId, setActionId] = useState<number | null>(null)
  const [place, setPlace] = useState<string | null>(null)
  const [stateOptions, setStateOptions] = useState<string[]>([])
  const [catalogue, setCatalogue] = useState<AdminCatalogueOffer | null>(null)
  const [freeStyles, setFreeStyles] = useState('40')
  const [packStyles, setPackStyles] = useState('10')
  const [packPrice, setPackPrice] = useState('1000')
  const [referralOffer, setReferralOffer] = useState<AdminReferralOffer | null>(null)
  const [referralSkip, setReferralSkip] = useState('0')
  const [referralAmount, setReferralAmount] = useState('0')
  const [salespeople, setSalespeople] = useState<AdminSalesperson[]>([])
  const [rewards, setRewards] = useState<AdminReferralReward[]>([])
  const [activities, setActivities] = useState<AdminReferralActivity[]>([])
  const [salespersonName, setSalespersonName] = useState('')
  const [desk, setDesk] = useState<'shops' | 'referrals'>('shops')

  const places = useMemo(() => {
    const counts = new Map<string, number>()
    for (const shop of shops) {
      const key = shop.state?.trim() || ''
      counts.set(key, (counts.get(key) ?? 0) + 1)
    }

    return [...counts.entries()].sort((left, right) => {
      if (left[0] === '') {
        return 1
      }
      if (right[0] === '') {
        return -1
      }

      return right[1] - left[1] || left[0].localeCompare(right[0])
    })
  }, [shops])

  const visibleShops =
    place === null
      ? shops
      : shops.filter((shop) => (shop.state?.trim() || '') === place)

  const selectedShop =
    selectedId === null
      ? null
      : (shops.find((shop) => shop.id === selectedId) ?? null)

  async function reload(search = q, status = paymentFilter) {
    setLoading(true)
    try {
      const [shopList, paymentRows, notes, reviewRows, referralRows] = await Promise.all([
        listAdminShops(search),
        listAdminPayments({ q: search, status }),
        listAdminFeedback(),
        listAdminReviews(),
        listAdminReferrals(),
      ])
      setShops(shopList.shops)
      setCatalogue(shopList.catalogue)
      setFreeStyles(String(shopList.catalogue.free))
      setPackStyles(String(shopList.catalogue.pack))
      setPackPrice(String(shopList.catalogue.amount))
      setPayments(paymentRows)
      setFeedback(notes)
      setReviews(reviewRows)
      setReferralOffer(referralRows.offer)
      setReferralSkip(String(referralRows.offer.skip))
      setReferralAmount(String(referralRows.offer.amount))
      setSalespeople(referralRows.salespeople)
      setRewards(referralRows.rewards)
      setActivities(referralRows.activities)
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

  useEffect(() => {
    let cancelled = false
    fetchPresence()
      .then((presence) => {
        if (!cancelled) {
          setStateOptions(presence.state_options)
        }
      })
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (place !== null && !places.some(([name]) => name === place)) {
      setPlace(null)
    }
  }, [place, places])

  useEffect(() => {
    if (!selectedShop) {
      return
    }

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setSelectedId(null)
      }
    }

    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [selectedShop])

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

  async function onSaveState(shop: AdminShop, state: string) {
    setActionId(shop.id)
    try {
      const updated = await updateAdminShopState(shop.id, state)
      replaceShop(updated)
    } finally {
      setActionId(null)
    }
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

  async function onModerate(
    review: AdminShopReview,
    action: 'approve' | 'reject' | 'hide',
    reason?: string,
  ) {
    setError('')

    try {
      const updated = await updateAdminReview(review.id, action, reason)
      setReviews((current) =>
        current.map((row) => (row.id === updated.id ? updated : row)),
      )
    } catch (err) {
      setError(firstError(err))
    }
  }

  const visibleReviews = reviews.filter((review) => {
    if (reviewStatus && review.status !== reviewStatus) {
      return false
    }

    const needle = reviewQuery.trim().toLowerCase()
    if (!needle) {
      return true
    }

    return `${review.shop_name ?? ''} ${review.owner_email ?? ''} ${review.body}`
      .toLowerCase()
      .includes(needle)
  })

  async function onSavePackPrice(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      const updated = await updateAdminCataloguePrice({
        free: Number(freeStyles),
        pack: Number(packStyles),
        amount: Number(packPrice),
      })
      setCatalogue(updated)
      setFreeStyles(String(updated.free))
      setPackStyles(String(updated.pack))
      setPackPrice(String(updated.amount))
      await reload(q, paymentFilter)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onSaveReferral(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      const updated = await updateAdminReferral({
        skip: Number(referralSkip),
        amount: Number(referralAmount),
      })
      setReferralOffer(updated)
      setReferralSkip(String(updated.skip))
      setReferralAmount(String(updated.amount))
      await reload(q, paymentFilter)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onAddSalesperson(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      await createAdminSalesperson(salespersonName.trim())
      setSalespersonName('')
      await reload(q, paymentFilter)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onApproveReward(id: string) {
    setBusy(true)
    setError('')
    try {
      await approveAdminReferral(id)
      await reload(q, paymentFilter)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onDeclineReward(id: string) {
    if (!window.confirm('Decline this free month?')) {
      return
    }
    setBusy(true)
    setError('')
    try {
      await rejectAdminReferral(id)
      await reload(q, paymentFilter)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
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

  const pendingRewards = rewards.filter((reward) => reward.status === 'pending').length

  if (desk === 'referrals') {
    return (
      <>
        <AdminDesk desk={desk} pending={pendingRewards} onChange={setDesk} />
        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        <AdminReferralsPage
          offer={referralOffer}
          salespeople={salespeople}
          rewards={rewards}
          activities={activities}
          skip={referralSkip}
          amount={referralAmount}
          salespersonName={salespersonName}
          busy={busy}
          onSkip={setReferralSkip}
          onAmount={setReferralAmount}
          onSalespersonName={setSalespersonName}
          onSaveRate={(event) => void onSaveReferral(event)}
          onAddSalesperson={(event) => void onAddSalesperson(event)}
          onApprove={(id) => void onApproveReward(id)}
          onDecline={(id) => void onDeclineReward(id)}
        />
      </>
    )
  }

  return (
    <>
      <AdminDesk desk={desk} pending={pendingRewards} onChange={setDesk} />
      <section className="card">
        <h1>Admin</h1>
        <p className="lede">
          {appName} shops. Signed in as {user?.email}.
        </p>
        <form className="form" onSubmit={(event) => void onSavePackPrice(event)}>
          <h2 className="section-title">Catalogue for every shop</h2>
          <label>
            Free styles
            <input
              type="number"
              min="0"
              max="500"
              step="1"
              value={freeStyles}
              onChange={(event) => setFreeStyles(event.target.value)}
              required
            />
          </label>
          <label>
            Paid styles in one purchase
            <input
              type="number"
              min="1"
              max="500"
              step="1"
              value={packStyles}
              onChange={(event) => setPackStyles(event.target.value)}
              required
            />
          </label>
          <label>
            Price for that purchase (₦)
            <input
              type="number"
              min="1"
              max="100000"
              step="1"
              value={packPrice}
              onChange={(event) => setPackPrice(event.target.value)}
              required
            />
          </label>
          <p className="status">
            Every shop gets {freeStyles || '0'} styles free. The next{' '}
            {packStyles || '0'} styles cost ₦{packPrice || '0'}.
            {catalogue ? ` Saved price: ${catalogue.amount_label}.` : ''}
          </p>
          <button type="submit" className="secondary" disabled={busy}>
            {busy ? 'Saving…' : 'Save catalogue'}
          </button>
        </form>
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
        <label className="search-label">
          Payment status
          <select
            value={paymentFilter}
            onChange={(event) =>
              setPaymentFilter(event.target.value as PaymentStatus | '')
            }
          >
            <option value="">All payments</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="pending">Pending</option>
            <option value="refunded">Refunded</option>
          </select>
        </label>
        <button
          type="button"
          className="primary"
          onClick={() => void reload(q, paymentFilter)}
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

      {!loading && (
        <section className="card muted-card">
          <h2 className="section-title">Recent payments</h2>
          <p className="status">
            Tap a shop in the list below to manage it. Successful payments
            activate plans automatically.
          </p>
          {payments.length === 0 ? (
            <p className="status">No payments yet.</p>
          ) : (
            <ul className="admin-list">
              {payments.map((payment) => (
                <li key={payment.id}>
                  <button
                    type="button"
                    className="admin-list-row"
                    onClick={() => {
                      if (payment.user_id) {
                        setSelectedId(payment.user_id)
                      }
                    }}
                    disabled={!payment.user_id}
                  >
                    <span className="admin-list-main">
                      <strong>
                        {payment.shop_name ?? payment.email ?? 'Shop'}
                      </strong>
                      <span className="status">
                        {payment.amount_label} · {formatWhen(payment.created_at)}
                        {payment.failure_message
                          ? ` · ${payment.failure_message}`
                          : ''}
                      </span>
                    </span>
                    <span className={paymentBadgeClass(payment.status)}>
                      {payment.status}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {!loading && (
        <section className="card muted-card">
          <div className="row-between">
            <h2 className="section-title">Shop reviews</h2>
            <span className="status">{reviews.length}</span>
          </div>
          <p className="status">
            Approve a review to put it on the TailorMate site. Reject or hide
            keeps it off the site.
          </p>
          <div className="form">
            <label>
              Search reviews
              <input
                value={reviewQuery}
                onChange={(event) => setReviewQuery(event.target.value)}
                placeholder="Shop, email, or words"
              />
            </label>
            <label>
              Status
              <select
                value={reviewStatus}
                onChange={(event) =>
                  setReviewStatus(event.target.value as ReviewStatus | '')
                }
              >
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="published">Published</option>
                <option value="rejected">Not approved</option>
              </select>
            </label>
          </div>
          {reviews.length === 0 ? (
            <p className="status">No shop reviews yet.</p>
          ) : visibleReviews.length === 0 ? (
            <p className="status">No reviews match this search.</p>
          ) : (
            <ul className="plain-list">
              {visibleReviews.map((review) => (
                <ReviewRow
                  key={review.id}
                  review={review}
                  onModerate={onModerate}
                />
              ))}
            </ul>
          )}
        </section>
      )}

      {!loading && (
        <section className="card muted-card">
          <div className="row-between">
            <h2 className="section-title">App feedback</h2>
            <span className="status">{feedback.length}</span>
          </div>
          <p className="status">
            Messages about TailorMate. Reply with the contact they left.
          </p>
          {feedback.length === 0 ? (
            <p className="status">No messages yet.</p>
          ) : (
            <ul className="plain-list">
              {feedback.map((note) => (
                <li key={note.id}>
                  <strong>{note.name}</strong>
                  {' · '}
                  {note.contact.includes('@') ? (
                    <a href={`mailto:${note.contact}`}>{note.contact}</a>
                  ) : (
                    <a href={`tel:${note.contact}`}>{note.contact}</a>
                  )}
                  {note.shop_email ? ` · signed in as ${note.shop_email}` : ''}
                  <br />
                  {note.message}
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {!loading && (
        <section className="card muted-card">
          <div className="row-between">
            <h2 className="section-title">Shops</h2>
            <span className="status">
              {place === null
                ? shops.length
                : `${visibleShops.length} of ${shops.length}`}
            </span>
          </div>

          {shops.length > 0 && (
            <>
              <p className="status">
                Grouped by the state each shop chose. Tap a state, then tap a
                shop for the owner, phone, and plan.
              </p>
              <div className="location-picks" role="group" aria-label="Shop locations">
                <button
                  type="button"
                  aria-pressed={place === null}
                  onClick={() => setPlace(null)}
                >
                  All · {shops.length}
                </button>
                {places.map(([name, count]) => (
                  <button
                    key={name || 'none'}
                    type="button"
                    aria-pressed={place === name}
                    onClick={() => setPlace(name)}
                  >
                    {name || 'No state yet'} · {count}
                  </button>
                ))}
              </div>
            </>
          )}

          {visibleShops.length === 0 ? (
            <p className="status">No shops found.</p>
          ) : (
            <ul className="admin-list">
              {visibleShops.map((shop) => (
                <li key={shop.id}>
                  <button
                    type="button"
                    className="admin-list-row"
                    onClick={() => setSelectedId(shop.id)}
                  >
                    <span className="admin-list-main">
                      <strong>{shop.shop_name ?? 'No shop name'}</strong>
                      <span className="status">
                        {shop.owner_name} · {shop.email}
                      </span>
                    </span>
                    <span className="admin-list-meta">
                      <span className={shop.state?.trim() ? 'badge ok' : 'badge'}>
                        {shop.state?.trim() || 'No state'}
                      </span>
                      <span
                        className={
                          shop.suspended ? 'badge danger' : 'badge ok'
                        }
                      >
                        {shop.suspended ? 'Suspended' : 'Active'}
                      </span>
                      <span
                        className={subscriptionBadgeClass(
                          shop.subscription_access,
                        )}
                      >
                        {subscriptionLabel(shop.subscription_access)}
                      </span>
                      <span className="badge">
                        {shop.catalogue_used ?? 0}/{shop.catalogue_limit ?? 40} styles
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

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

      {selectedShop && (
        <ShopModal
          shop={selectedShop}
          busy={actionId === selectedShop.id}
          stateOptions={stateOptions}
          onClose={() => setSelectedId(null)}
          onToggleSuspend={() => void onToggleSuspend(selectedShop)}
          onSaveState={(state) => onSaveState(selectedShop, state)}
          onSaveSubscription={(status, until) =>
            void onSaveSubscription(selectedShop, status, until)
          }
          onResetPassword={(password, confirmation) =>
            void onResetPassword(selectedShop, password, confirmation)
          }
        />
      )}
    </>
  )
}

function ShopModal({
  shop,
  busy,
  stateOptions,
  onClose,
  onToggleSuspend,
  onSaveState,
  onSaveSubscription,
  onResetPassword,
}: {
  shop: AdminShop
  busy: boolean
  stateOptions: string[]
  onClose: () => void
  onToggleSuspend: () => void
  onSaveState: (state: string) => Promise<void>
  onSaveSubscription: (status: SubscriptionStatus, until: string) => void
  onResetPassword: (password: string, confirmation: string) => void
}) {
  const [status, setStatus] = useState<SubscriptionStatus>(
    shop.subscription_status === 'subscribed' ? 'subscribed' : 'free',
  )
  const [until, setUntil] = useState(shop.subscribed_until ?? defaultUntil())
  const [newPassword, setNewPassword] = useState('')
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('')
  const [stateName, setStateName] = useState(shop.state ?? '')
  const [stateError, setStateError] = useState('')
  const [stateSaved, setStateSaved] = useState(false)

  useEffect(() => {
    setStatus(shop.subscription_status === 'subscribed' ? 'subscribed' : 'free')
    setUntil(shop.subscribed_until ?? defaultUntil())
    setStateName(shop.state ?? '')
    setStateSaved(false)
    setStateError('')
  }, [shop.id, shop.subscription_status, shop.subscribed_until, shop.state])

  function onSubmitState(event: FormEvent) {
    event.preventDefault()
    setStateError('')
    setStateSaved(false)
    void onSaveState(stateName)
      .then(() => setStateSaved(true))
      .catch((err) => setStateError(firstError(err)))
  }

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
    <div
      className="modal-backdrop"
      role="presentation"
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          onClose()
        }
      }}
    >
      <div
        className="modal-sheet"
        role="dialog"
        aria-modal="true"
        aria-labelledby={`shop-modal-${shop.id}`}
      >
        <div className="row-between">
          <h2 className="section-title" id={`shop-modal-${shop.id}`}>
            {shop.shop_name ?? 'No shop name'}
          </h2>
          <button type="button" className="secondary" onClick={onClose}>
            Close
          </button>
        </div>

        <div className="admin-modal-badges">
          <span className={shop.suspended ? 'badge danger' : 'badge ok'}>
            {shop.suspended ? 'Suspended' : 'Active'}
          </span>
          <span className={subscriptionBadgeClass(shop.subscription_access)}>
            {subscriptionLabel(shop.subscription_access)}
            {shop.subscribed_until ? ` · until ${shop.subscribed_until}` : ''}
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
            <dt>State</dt>
            <dd>
              <form className="form" onSubmit={onSubmitState}>
                <label>
                  Nigerian state
                  <select
                    value={stateName}
                    onChange={(event) => setStateName(event.target.value)}
                    required
                  >
                    <option value="">Choose a state</option>
                    {stateOptions.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                    {stateName && !stateOptions.includes(stateName) ? (
                      <option value={stateName}>{stateName}</option>
                    ) : null}
                  </select>
                </label>
                {stateError && (
                  <p className="form-error" role="alert">
                    {stateError}
                  </p>
                )}
                {stateSaved && <p className="status">State saved.</p>}
                <button type="submit" className="secondary" disabled={busy || !stateName}>
                  {busy ? 'Saving…' : 'Save state'}
                </button>
              </form>
            </dd>
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
            <dt>Latest payment</dt>
            <dd>
              {shop.latest_payment_status ? (
                <>
                  <span className={paymentBadgeClass(shop.latest_payment_status)}>
                    {shop.latest_payment_status}
                  </span>
                  {shop.latest_payment_at
                    ? ` · ${formatWhen(shop.latest_payment_at)}`
                    : ''}
                </>
              ) : (
                'None'
              )}
            </dd>
          </div>
        </dl>

        {(shop.payments?.length ?? 0) > 0 && (
          <div>
            <h3 className="section-title">Payments</h3>
            <ul className="plain-list">
              {shop.payments?.map((payment) => (
                <li key={payment.id}>
                  {payment.amount_label}
                  {payment.purpose === 'catalogue' ? ' · 10 catalogue styles' : ''} ·{' '}
                  <span className={paymentBadgeClass(payment.status)}>
                    {payment.status}
                  </span>
                  {' · '}
                  {formatWhen(payment.created_at)}
                  {payment.channel ? ` · ${payment.channel}` : ''}
                  {payment.failure_message
                    ? ` · ${payment.failure_message}`
                    : ''}
                </li>
              ))}
            </ul>
          </div>
        )}

        <form className="form" onSubmit={onSubmit}>
          <fieldset className="unit-field">
            <legend>Manual plan override</legend>
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

        <p className="status">
          Catalogue: {shop.catalogue_used ?? 0} of {shop.catalogue_limit ?? 40}{' '}
          styles are in use. The free count and the paid pack are set for every
          shop at the top of this page.
        </p>
        <p className="status">
          Invite code: {shop.invite_code ?? '—'}.{' '}
          {shop.referred_by
            ? `Invited by ${shop.referred_by}.`
            : 'No invite code was used.'}{' '}
          {shop.referral_signed_up ?? 0} shops used this code,{' '}
          {shop.referral_paid ?? 0} have paid. A paid invite can receive one
          free month from the Referrals page.
        </p>
        {(shop.referral_shops ?? []).length > 0 && (
          <ul>
            {(shop.referral_shops ?? []).map((row) => (
              <li key={`${row.email ?? row.name}`}>
                {row.name} · {row.paid ? 'paid' : 'signed up'}
              </li>
            ))}
          </ul>
        )}

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
      </div>
    </div>
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
    case 'trial':
      return 'Trial'
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

function paymentBadgeClass(status: PaymentStatus): string {
  if (status === 'failed' || status === 'refunded') {
    return 'badge danger'
  }
  if (status === 'success') {
    return 'badge ok'
  }
  return 'badge'
}

function reviewDate(iso: string | null): string {
  if (!iso) {
    return ''
  }

  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function ReviewRow({
  review,
  onModerate,
}: {
  review: AdminShopReview
  onModerate: (
    review: AdminShopReview,
    action: 'approve' | 'reject' | 'hide',
    reason?: string,
  ) => Promise<void>
}) {
  const [reason, setReason] = useState(review.rejection_reason ?? '')
  const [busy, setBusy] = useState(false)
  const submitted = reviewDate(review.submitted_at)

  useEffect(() => {
    setReason(review.rejection_reason ?? '')
  }, [review.rejection_reason, review.status])

  async function run(action: 'approve' | 'reject' | 'hide') {
    setBusy(true)
    await onModerate(review, action, reason)
    setBusy(false)
  }

  return (
    <li>
      <strong>{review.shop_name ?? 'Shop'}</strong>
      {review.state ? ` · ${review.state}` : ''}
      {review.owner_email ? ` · ${review.owner_email}` : ''}
      <br />
      {'★'.repeat(review.rating)}
      {'☆'.repeat(Math.max(0, 5 - review.rating))} {review.body}
      <br />
      <span className="status">
        {review.status_label}
        {submitted ? ` · ${submitted}` : ''}
        {review.rejection_reason ? ` · ${review.rejection_reason}` : ''}
      </span>
      <div className="form">
        <label>
          Reason if not approved
          <input
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            maxLength={200}
            placeholder="Optional"
          />
        </label>
      </div>
      <div className="home-actions">
        {review.status !== 'published' && (
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={() => void run('approve')}
          >
            Approve
          </button>
        )}
        {review.status !== 'rejected' && (
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={() => void run('reject')}
          >
            Reject
          </button>
        )}
        {review.status === 'published' && (
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={() => void run('hide')}
          >
            Hide
          </button>
        )}
      </div>
    </li>
  )
}
