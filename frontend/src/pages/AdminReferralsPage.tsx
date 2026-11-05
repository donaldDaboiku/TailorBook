import { type FormEvent } from 'react'
import {
  type AdminReferralActivity,
  type AdminReferralOffer,
  type AdminReferralOverview,
  type AdminReferralReward,
  type AdminSalesperson,
  type AdminShopReferral,
} from '../api'

export default function AdminReferralsPage({
  offer,
  salespeople,
  rewards,
  referrals,
  overview,
  activities,
  skip,
  amount,
  threshold,
  salespersonName,
  busy,
  onSkip,
  onAmount,
  onThreshold,
  onSalespersonName,
  onSaveRate,
  onAddSalesperson,
  onApprove,
  onDecline,
  onRejectShop,
  onToggleSalesperson,
}: {
  offer: AdminReferralOffer | null
  salespeople: AdminSalesperson[]
  rewards: AdminReferralReward[]
  referrals: AdminShopReferral[]
  overview: AdminReferralOverview | null
  activities: AdminReferralActivity[]
  skip: string
  amount: string
  threshold: string
  salespersonName: string
  busy: boolean
  onSkip: (value: string) => void
  onAmount: (value: string) => void
  onThreshold: (value: string) => void
  onSalespersonName: (value: string) => void
  onSaveRate: (event: FormEvent) => void
  onAddSalesperson: (event: FormEvent) => void
  onApprove: (id: string) => void
  onDecline: (id: string) => void
  onRejectShop: (id: string) => void
  onToggleSalesperson: (id: string, active: boolean) => void
}) {
  const pending = rewards.filter((reward) => reward.status === 'pending')
  const approved = rewards.filter((reward) => reward.status === 'approved').length

  return (
    <>
      <section className="card">
        <h1>Referrals</h1>
        <p className="lede">
          {threshold || '5'} paid shops earn one free month. You approve it
          here. A salesperson code is separate and does not earn that month.
        </p>
        <div className="stat-grid">
          <article>
            <span>Registered</span>
            <strong>{overview?.registered ?? referrals.length}</strong>
          </article>
          <article>
            <span>Paid and counting</span>
            <strong>{overview?.successful ?? 0}</strong>
          </article>
          <article>
            <span>Waiting for payment</span>
            <strong>{overview?.pending_payment ?? 0}</strong>
          </article>
          <article>
            <span>Ready to approve</span>
            <strong>{overview?.pending_rewards ?? pending.length}</strong>
          </article>
          <article>
            <span>Free months given</span>
            <strong>{overview?.free_months ?? approved}</strong>
          </article>
          <article>
            <span>Codes</span>
            <strong>{overview?.codes ?? 0}</strong>
          </article>
        </div>
      </section>

      <section className="card">
        <h2 className="section-title">Ready to approve</h2>
        {pending.length === 0 ? (
          <p className="status">No free month is waiting.</p>
        ) : (
          <div className="reward-grid">
            {pending.map((reward) => (
              <article className="reward-card" key={reward.id}>
                <h3>{reward.referrer_name ?? 'Shop'}</h3>
                <p>
                  Set {reward.milestone} is complete. {reward.referrer_email} can
                  receive one free month.
                </p>
                <div className="row-actions">
                  <button
                    type="button"
                    className="primary"
                    disabled={busy}
                    onClick={() => onApprove(reward.id)}
                  >
                    Approve free month
                  </button>
                  <button
                    type="button"
                    className="secondary"
                    disabled={busy}
                    onClick={() => onDecline(reward.id)}
                  >
                    Decline
                  </button>
                </div>
              </article>
            ))}
          </div>
        )}
      </section>

      <section className="card">
        <h2 className="section-title">Shop referrals</h2>
        {referrals.length === 0 ? (
          <p className="status">No shop has registered with an invite code yet.</p>
        ) : (
          <ul className="activity-list">
            {referrals.map((row) => (
              <li key={row.id}>
                <p>
                  <strong>{row.shop_name || 'Shop'}</strong> · {row.status_label}
                  {row.referrer_name ? ` · invited by ${row.referrer_name}` : ''}
                </p>
                {(row.status === 'signed_up' || row.status === 'successful') && (
                  <button
                    type="button"
                    className="secondary"
                    disabled={busy}
                    onClick={() => onRejectShop(row.id)}
                  >
                    Reject
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card">
        <h2 className="section-title">Activity</h2>
        {activities.length === 0 ? (
          <p className="status">No referral activity yet.</p>
        ) : (
          <ol className="activity-list">
            {activities.map((row) => (
              <li key={row.id}>
                <time dateTime={row.at ?? undefined}>
                  {row.at ? new Date(row.at).toLocaleString() : ''}
                </time>
                <p>{row.summary}</p>
              </li>
            ))}
          </ol>
        )}
      </section>

      <section className="card">
        <form className="form" onSubmit={onSaveRate}>
          <h2 className="section-title">Salesperson commission</h2>
          <p className="status">
            Shop reward: {threshold || offer?.threshold || 5} paid shops, then
            one free month after you approve it. The naira amount below is only
            the salesperson commission.
            {offer ? ` Saved rate: ${offer.amount_label}.` : ''}
          </p>
          <label>
            Paid shops for one free month
            <input
              type="number"
              min="1"
              max="100"
              step="1"
              value={threshold}
              onChange={(event) => onThreshold(event.target.value)}
              required
            />
          </label>
          <label>
            Paying shops before commission starts
            <input
              type="number"
              min="0"
              max="500"
              step="1"
              value={skip}
              onChange={(event) => onSkip(event.target.value)}
              required
            />
          </label>
          <label>
            Naira for each paying shop after that
            <input
              type="number"
              min="0"
              max="100000"
              step="1"
              value={amount}
              onChange={(event) => onAmount(event.target.value)}
              required
            />
          </label>
          <button type="submit" className="secondary" disabled={busy}>
            {busy ? 'Saving…' : 'Save salesperson rate'}
          </button>
        </form>
        <form className="form" onSubmit={onAddSalesperson}>
          <h2 className="section-title">Salesperson</h2>
          <label>
            Name
            <input
              value={salespersonName}
              onChange={(event) => onSalespersonName(event.target.value)}
              maxLength={120}
              required
            />
          </label>
          <button type="submit" className="secondary" disabled={busy}>
            {busy ? 'Saving…' : 'Add salesperson'}
          </button>
          {salespeople.length === 0 ? (
            <p className="status">No salesperson yet.</p>
          ) : (
            <ul className="activity-list">
              {salespeople.map((person) => (
                <li key={person.id}>
                  <p>
                    <strong>{person.name}</strong> · {person.code}
                  </p>
                  <p className="status">
                    {person.active ? 'Active' : 'Off'} · {person.signed_up} signed
                    up · {person.paid} paid · {person.commission_label}
                    {person.shops.length > 0
                      ? ` · ${person.shops
                          .map((shop) => `${shop.name} (${shop.paid ? 'paid' : 'signed up'})`)
                          .join(', ')}`
                      : ''}
                  </p>
                  <button
                    type="button"
                    className="link"
                    disabled={busy}
                    onClick={() => onToggleSalesperson(person.id, !person.active)}
                  >
                    {person.active ? 'Turn off' : 'Turn on'}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </form>
      </section>
    </>
  )
}
