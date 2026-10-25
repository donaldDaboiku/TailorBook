import { useEffect, useState, type FormEvent } from 'react'
import { fetchShopReview, firstError, saveShopReview, type ShopReview } from '../api'

export default function ShopReviewCard() {
  const [rating, setRating] = useState(0)
  const [body, setBody] = useState('')
  const [review, setReview] = useState<ShopReview | null>(null)
  const [loading, setLoading] = useState(true)
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    fetchShopReview()
      .then((saved) => {
        if (cancelled || !saved) {
          return
        }
        setReview(saved)
        setRating(saved.rating)
        setBody(saved.body)
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setNote('')

    if (rating < 1) {
      setError('Choose a star rating.')
      return
    }

    if (body.trim() === '') {
      setError('Tell us how TailorMate helps your shop.')
      return
    }

    setBusy(true)

    try {
      const saved = await saveShopReview({ rating, body: body.trim() })
      setReview(saved)
      setNote('Submitted. Status: Pending.')
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card muted-card">
      <h2 className="section-title">Review TailorMate</h2>
      <p className="status">
        Help other tailors discover how TailorMate can improve their business.
      </p>
      <p className="status">
        Your shop name appears on the TailorMate site if this review is approved.
      </p>
      {loading && <p className="status">Loading your review…</p>}
      {!loading && review && (
        <p className="status">
          Status: {review.status_label}
          {review.status === 'rejected' && review.rejection_reason
            ? `. ${review.rejection_reason}`
            : ''}
        </p>
      )}
      <form className="form" onSubmit={(event) => void onSubmit(event)}>
        <div className="star-pick" role="group" aria-label="Rating from 1 to 5 stars">
          {[1, 2, 3, 4, 5].map((value) => (
            <button
              key={value}
              type="button"
              aria-label={`${value} ${value === 1 ? 'star' : 'stars'}`}
              aria-pressed={value <= rating}
              onClick={() => setRating(value)}
            >
              ★
            </button>
          ))}
        </div>
        <label>
          Review
          <textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            maxLength={400}
            required
            placeholder="Tell us how TailorMate helps you manage your tailoring business..."
          />
        </label>
        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        {note && <p className="status">{note}</p>}
        <button type="submit" className="secondary" disabled={busy || loading}>
          {busy ? 'Submitting…' : 'Submit Review'}
        </button>
      </form>
    </section>
  )
}
