import { useEffect, useState } from 'react'
import {
  archiveCustomer,
  firstError,
  getCustomer,
  type Customer,
} from '../api'

export default function CustomerDetailPage({
  id,
  onBack,
  onEdit,
  onArchived,
}: {
  id: string
  onBack: () => void
  onEdit: (customer: Customer) => void
  onArchived: () => void
}) {
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    getCustomer(id)
      .then((row) => {
        if (!cancelled) {
          setCustomer(row)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })

    return () => {
      cancelled = true
    }
  }, [id])

  async function onArchive() {
    if (!customer) {
      return
    }

    if (!window.confirm(`Archive ${customer.name}? You can reuse the phone later.`)) {
      return
    }

    setBusy(true)
    try {
      await archiveCustomer(customer.id)
      onArchived()
    } catch (err) {
      setError(firstError(err))
      setBusy(false)
    }
  }

  if (error && !customer) {
    return (
      <section className="card">
        <p className="form-error" role="alert">
          {error}
        </p>
        <button type="button" className="secondary" onClick={onBack}>
          Back
        </button>
      </section>
    )
  }

  if (!customer) {
    return <p className="status">Loading customer…</p>
  }

  return (
    <>
      <section className="card">
        <button type="button" className="link back-link" onClick={onBack}>
          Back to customers
        </button>
        <h1>{customer.name}</h1>
        <p className="lede">{customer.phone}</p>

        <div className="action-row">
          <a className="primary compact" href={`tel:${customer.phone}`}>
            Call
          </a>
          <a
            className="primary compact"
            href={customer.whatsapp_url}
            target="_blank"
            rel="noreferrer"
          >
            WhatsApp
          </a>
        </div>
      </section>

      <section className="card muted-card">
        <dl className="details">
          <div>
            <dt>WhatsApp</dt>
            <dd>{customer.whatsapp_phone}</dd>
          </div>
          {customer.email && (
            <div>
              <dt>Email</dt>
              <dd>{customer.email}</dd>
            </div>
          )}
          {customer.gender && (
            <div>
              <dt>Gender</dt>
              <dd>{customer.gender === 'female' ? 'Female' : 'Male'}</dd>
            </div>
          )}
          {customer.address && (
            <div>
              <dt>Address</dt>
              <dd>{customer.address}</dd>
            </div>
          )}
          {customer.notes && (
            <div>
              <dt>Notes</dt>
              <dd>{customer.notes}</dd>
            </div>
          )}
        </dl>

        <button
          type="button"
          className="secondary"
          onClick={() => onEdit(customer)}
        >
          Edit customer
        </button>
        <button
          type="button"
          className="secondary danger"
          onClick={onArchive}
          disabled={busy}
        >
          {busy ? 'Archiving…' : 'Archive customer'}
        </button>
        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
      </section>
    </>
  )
}
