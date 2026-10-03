import { useState, type FormEvent } from 'react'
import { createJob, firstError, todayDate, type Customer } from '../api'

export default function JobFormPage({
  customer,
  onCancel,
  onSaved,
}: {
  customer: Customer
  onCancel: () => void
  onSaved: () => void
}) {
  const [title, setTitle] = useState('')
  const [agreedAmount, setAgreedAmount] = useState('')
  const [serviceDate, setServiceDate] = useState(todayDate())
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      await createJob(customer.id, {
        title: title.trim(),
        agreed_amount: agreedAmount,
        service_date: serviceDate,
      })
      onSaved()
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onCancel}>
        Back to {customer.name}
      </button>
      <h1>New job</h1>
      <p className="lede">What should {customer.name} pay for?</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          Job
          <input
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            placeholder="Gown, suit, shirt…"
            required
          />
        </label>

        <label>
          Agreed amount (₦)
          <input
            type="number"
            inputMode="decimal"
            min="1"
            step="0.01"
            value={agreedAmount}
            onChange={(event) => setAgreedAmount(event.target.value)}
            required
          />
        </label>

        <label>
          Date
          <input
            type="date"
            value={serviceDate}
            onChange={(event) => setServiceDate(event.target.value)}
            required
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Saving…' : 'Save job'}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          Cancel
        </button>
      </form>
    </section>
  )
}
