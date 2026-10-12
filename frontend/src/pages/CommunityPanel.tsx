import { useEffect, useState, type FormEvent } from 'react'
import {
  fetchPresence,
  firstError,
  sendFeedback,
  type PublicPresence,
} from '../api'

export default function CommunityPanel({
  variant = 'page',
  defaultName = '',
  defaultContact = '',
}: {
  variant?: 'page' | 'compact'
  defaultName?: string
  defaultContact?: string
}) {
  const [presence, setPresence] = useState<PublicPresence | null>(null)
  const [unavailable, setUnavailable] = useState(false)
  const [name, setName] = useState(defaultName)
  const [contact, setContact] = useState(defaultContact)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [sent, setSent] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    fetchPresence()
      .then((next) => {
        if (!cancelled) {
          setPresence(next)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUnavailable(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setSent('')
    setBusy(true)

    try {
      const note = await sendFeedback({
        name: name.trim(),
        contact: contact.trim(),
        message: message.trim(),
      })
      setSent(note)
      setMessage('')
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  const count = presence?.subscribers ?? 0
  const countLabel =
    count === 1 ? '1 subscribed shop' : `${count} subscribed shops`
  const contactLinks = presence?.contact
  const hasContact = Boolean(
    contactLinks?.email || contactLinks?.phone || contactLinks?.whatsapp_url,
  )

  return (
    <>
    <section className="card muted-card">
      <h2 className="section-title">Shops on TailorMate</h2>
      {presence ? (
        <>
          <p className="stat-number">{countLabel}</p>
          {presence.states.length > 0 ? (
            <ul className="state-list">
              {presence.states.map((row) => (
                <li key={row.state}>
                  {row.state} · {row.subscribers}
                </li>
              ))}
            </ul>
          ) : (
            <p className="status">State totals appear as shops subscribe.</p>
          )}
        </>
      ) : (
        <p className="status">
          {unavailable
            ? 'Subscriber numbers are unavailable right now.'
            : 'Loading subscriber numbers…'}
        </p>
      )}
    </section>

      {variant === 'compact' ? null : (
        <section className="card muted-card">
          <h2 className="section-title">Contact TailorMate</h2>
          <p className="lede">
            Reach the admin about the app. Messages here go to TailorMate.
          </p>
          {hasContact ? (
            <div className="social-links">
              {contactLinks?.whatsapp_url && (
                <a href={contactLinks.whatsapp_url} target="_blank" rel="noreferrer">
                  WhatsApp the admin
                </a>
              )}
              {contactLinks?.phone && (
                <a href={`tel:${contactLinks.phone}`}>Call {contactLinks.phone}</a>
              )}
              {contactLinks?.email && (
                <a href={`mailto:${contactLinks.email}`}>{contactLinks.email}</a>
              )}
            </div>
          ) : (
            <p className="status">
              The admin phone, email, and social links show here once they are set.
            </p>
          )}

          {presence && presence.social.length > 0 && (
            <>
              <h3 className="section-title">Social</h3>
              <div className="social-links">
                {presence.social.map((link) => (
                  <a key={link.name} href={link.url} target="_blank" rel="noreferrer">
                    {link.name}
                  </a>
                ))}
              </div>
            </>
          )}

          <h3 className="section-title">Feedback for the admin</h3>
          <form className="form" onSubmit={onSubmit}>
            <label>
              Your name
              <input
                value={name}
                onChange={(event) => setName(event.target.value)}
                required
                maxLength={120}
              />
            </label>
            <label>
              Email or phone
              <input
                value={contact}
                onChange={(event) => setContact(event.target.value)}
                required
                maxLength={160}
              />
            </label>
            <label>
              Message about the app
              <textarea
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                placeholder="What should the admin know about TailorMate?"
                required
                maxLength={2000}
                rows={4}
              />
            </label>
            {error && (
              <p className="form-error" role="alert">
                {error}
              </p>
            )}
            {sent && <p className="status">{sent}</p>}
            <button type="submit" className="secondary" disabled={busy}>
              {busy ? 'Sending…' : 'Send to the admin'}
            </button>
          </form>
        </section>
      )}
    </>
  )
}
