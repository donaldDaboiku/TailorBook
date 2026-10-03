import { useEffect, useState } from 'react'
import {
  archiveCustomer,
  firstError,
  getCustomer,
  listMeasurements,
  type Customer,
  type Measurement,
} from '../api'
import { useAuth } from '../auth'

export default function CustomerDetailPage({
  id,
  onBack,
  onEdit,
  onArchived,
  onTakeMeasurements,
  onOpenMeasurement,
  onDuplicateMeasurement,
}: {
  id: string
  onBack: () => void
  onEdit: (customer: Customer) => void
  onArchived: () => void
  onTakeMeasurements: (customer: Customer) => void
  onOpenMeasurement: (customer: Customer, measurementId: string) => void
  onDuplicateMeasurement: (customer: Customer, measurement: Measurement) => void
}) {
  const { user } = useAuth()
  const unit = user?.business?.measurement_unit ?? 'in'
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [measurements, setMeasurements] = useState<Measurement[]>([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    Promise.all([getCustomer(id), listMeasurements(id, unit)])
      .then(([row, history]) => {
        if (!cancelled) {
          setCustomer(row)
          setMeasurements(history)
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
  }, [id, unit])

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

  const latest = measurements[0] ?? null

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
        <div className="row-between">
          <h2 className="section-title">Measurements</h2>
          <button
            type="button"
            className="primary compact"
            onClick={() => onTakeMeasurements(customer)}
          >
            Take
          </button>
        </div>

        {latest ? (
          <>
            <p className="status">Latest: {latest.taken_on}</p>
            <div className="action-row tight">
              <button
                type="button"
                className="secondary"
                onClick={() => onOpenMeasurement(customer, latest.id)}
              >
                View latest
              </button>
              <button
                type="button"
                className="secondary"
                onClick={() => onDuplicateMeasurement(customer, latest)}
              >
                Duplicate
              </button>
            </div>
          </>
        ) : (
          <p className="status">No measurements yet.</p>
        )}

        {measurements.length > 0 && (
          <ul className="list compact-list">
            {measurements.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className="list-item"
                  onClick={() => onOpenMeasurement(customer, item.id)}
                >
                  <span className="list-title">{item.taken_on}</span>
                  <span className="list-meta">{item.template.name}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
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
