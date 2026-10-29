import { type FormEvent } from 'react'
import {
  type AdminReferralActivity,
  type AdminReferralOffer,
  type AdminReferralReward,
  type AdminSalesperson,
} from '../api'

export default function AdminReferralsPage({
  offer,
  salespeople,
  rewards,
  activities,
  skip,
  amount,
  salespersonName,
  busy,
  onSkip,
  onAmount,
  onSalespersonName,
  onSaveRate,
  onAddSalesperson,
  onApprove,
  onDecline,
}: {
  offer: AdminReferralOffer | null
  salespeople: AdminSalesperson[]
  rewards: AdminReferralReward[]
  activities: AdminReferralActivity[]
  skip: string
  amount: string
  salespersonName: string
  busy: boolean
  onSkip: (value: string) => void
  onAmount: (value: string) => void
  onSalespersonName: (value: string) => void
  onSaveRate: (event: FormEvent) => void
  onAddSalesperson: (event: FormEvent) => void
  onApprove: (id: string) => void
  onDecline: (id: string) => void
}) {
  const pending = rewards.filter((reward) => reward.status === 'pending')
  const waiting = rewards.filter((reward) => reward.status === 'signed_up').length
  const approved = rewards.filter((reward) => reward.status === 'approved').length

  return (
    <>
      <section className="card">
        <h1>Referrals</h1>
        <p className="lede">
          A new shop that pays after using another shop’s code gives that shop
          one free month. You approve it here. The record stays on this page.
        </p>
        <div className="stat-grid">
          <article>
            <span>Signed up, not paid yet</span>
            <strong>{waiting}</strong>
          </article>
          <article>
            <span>Ready to approve</span>
            <strong>{pending.length}</strong>
          </article>
          <article>
            <span>Free months given</span>
            <strong>{approved}</strong>
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
                  {reward.referred_name ?? 'A new shop'} paid the monthly plan
                  with this shop’s code.
                </p>
                <p className="status">
                  {reward.referrer_email}
                  {reward.referred_email ? ` · invited ${reward.referred_email}` : ''}
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
            This rate is for a salesperson’s code. A shop’s reward is the free
            month above, not this amount.
            {offer ? ` Saved rate: ${offer.amount_label}.` : ''}
          </p>
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
                    {person.signed_up} signed up · {person.paid} paid ·{' '}
                    {person.commission_label}
                    {person.shops.length > 0
                      ? ` · ${person.shops
                          .map((shop) => `${shop.name} (${shop.paid ? 'paid' : 'signed up'})`)
                          .join(', ')}`
                      : ''}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </form>
      </section>
    </>
  )
}
