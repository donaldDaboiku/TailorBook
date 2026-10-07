import { useEffect, useState } from 'react'
import {
  fetchBillingPlan,
  firstError,
  startBillingCheckout,
  type BillingPlan,
} from '../api'
import { useAuth } from '../auth'

export default function SubscriptionBillingCard({
  title = 'Subscription',
  lead,
}: {
  title?: string
  lead?: string
}) {
  const { user } = useAuth()
  const [plan, setPlan] = useState<BillingPlan | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loadingPlan, setLoadingPlan] = useState(true)

  useEffect(() => {
    let cancelled = false

    fetchBillingPlan()
      .then((next) => {
        if (!cancelled) {
          setPlan(next)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingPlan(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function onPay() {
    setError('')
    setBusy(true)

    try {
      const checkout = await startBillingCheckout()
      window.location.assign(checkout.authorization_url)
    } catch (err) {
      setError(firstError(err))
      setBusy(false)
    }
  }

  const access = user?.subscription_access ?? 'free'
  const until = user?.subscribed_until

  return (
    <section className="card muted-card">
      <h2 className="section-title">{title}</h2>
      {lead && <p className="lede">{lead}</p>}

      {!lead && access === 'free' && (
        <p className="status">Your shop is on the free plan.</p>
      )}
      {access === 'subscribed' && (
        <p className="status">
          Subscribed{until ? ` until ${until}` : ''}.
        </p>
      )}
      {access === 'expired' && (
        <p className="status">
          Your paid plan ended{until ? ` on ${until}` : ''}. Renew to keep
          using the shop.
        </p>
      )}

      {loadingPlan && <p className="status">Loading plan…</p>}

      {plan && (
        <p className="lede">
          {plan.label}: {plan.amount_label} for {plan.days} days (Paystack).
        </p>
      )}

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      <button
        type="button"
        className="primary"
        onClick={onPay}
        disabled={busy || loadingPlan || !plan?.enabled}
      >
        {busy
          ? 'Opening Paystack…'
          : access === 'subscribed'
            ? 'Extend with Paystack'
            : 'Pay with Paystack'}
      </button>

      {plan && !plan.enabled && (
        <p className="status">
          Online payments are not set up yet. Ask the admin to configure
          Paystack, or renew manually.
        </p>
      )}
    </section>
  )
}
