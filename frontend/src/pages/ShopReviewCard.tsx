import { useEffect, useState, type FormEvent } from 'react'
import { fetchShopReview, firstError, saveShopReview } from '../api'

export default function ShopReviewCard() {
  const [rating, setRating] = useState(5)
  const [body, setBody] = useState('')
  const [approved, setApproved] = useState(false)
  const [saved, setSaved] = useState(false)
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    fetchShopReview()
      .then((review) => {
        if (cancelled || !review) {
          return
        }
        setRating(review.rating)
        setBody(review.body)
        setApproved(review.approved)
        setSaved(true)
      })
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNote('')

    try {
      const review = await saveShopReview({ rating, body })
      setApproved(review.approved)
      setSaved(true)
      setNote('Saved. It stays off the site until the admin posts it.')
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card muted-card">
      <h2 className="section-title">Your review</h2>
      <p className="status">
        Say what TailorMate does for your shop. The admin chooses which
        reviews appear on the TailorMate site. Your shop name is shown with
        the review.
      </p>
      {saved && (
        <p className="status">
          {approved
            ? 'This review is on the TailorMate site.'
            : 'Waiting for the admin to post this review.'}
        </p>
      )}
      <form className="form" onSubmit={(event) => void onSubmit(event)}>
        <div className="unit-field" role="group" aria-label="Rating">
          {[1, 2, 3, 4, 5].map((value) => (
            <label key={value} className="choice">
              <input
                type="radio"
                name="shop-review-rating"
                checked={rating === value}
                onChange={() => setRating(value)}
              />
              {value} {value === 1 ? 'star' : 'stars'}
            </label>
          ))}
        </div>
        <label>
          Review
          <textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            maxLength={400}
            required
            placeholder="What should other tailors know?"
          />
        </label>
        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        {note && <p className="status">{note}</p>}
        <button type="submit" className="secondary" disabled={busy}>
          {busy ? 'Saving…' : 'Save review'}
        </button>
      </form>
    </section>
  )
}
