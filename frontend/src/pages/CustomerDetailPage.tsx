import { useEffect, useState } from 'react'
import {
  archiveCustomer,
  archiveJob,
  archivePayment,
  firstError,
  getCustomer,
  listJobs,
  listMeasurements,
  listPayments,
  type Customer,
  type CustomerJob,
  type Measurement,
  type Payment,
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
  onAddJob,
  onAddPayment,
  onWhatsApp,
}: {
  id: string
  onBack: () => void
  onEdit: (customer: Customer) => void
  onArchived: () => void
  onTakeMeasurements: (customer: Customer) => void
  onOpenMeasurement: (customer: Customer, measurementId: string) => void
  onDuplicateMeasurement: (customer: Customer, measurement: Measurement) => void
  onAddJob: (customer: Customer) => void
  onAddPayment: (customer: Customer, jobId?: string) => void
  onWhatsApp: (customer: Customer) => void
}) {
  const { user } = useAuth()
  const unit = user?.business?.measurement_unit ?? 'in'
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [measurements, setMeasurements] = useState<Measurement[]>([])
  const [jobs, setJobs] = useState<CustomerJob[]>([])
  const [payments, setPayments] = useState<Payment[]>([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function reload() {
    const [row, history, jobRows, paymentRows] = await Promise.all([
      getCustomer(id),
      listMeasurements(id, unit),
      listJobs(id),
      listPayments(id),
    ])

    setCustomer(row)
    setMeasurements(history)
    setJobs(jobRows)
    setPayments(paymentRows)
  }

  useEffect(() => {
    let cancelled = false

    reload()
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

  async function onArchiveJob(job: CustomerJob) {
    if (!window.confirm(`Archive job “${job.title}”?`)) {
      return
    }

    try {
      await archiveJob(id, job.id)
      await reload()
    } catch (err) {
      setError(firstError(err))
    }
  }

  async function onArchivePayment(payment: Payment) {
    if (!window.confirm(`Archive payment ${payment.amount_label}?`)) {
      return
    }

    try {
      await archivePayment(id, payment.id)
      await reload()
    } catch (err) {
      setError(firstError(err))
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
  const finance = customer.finance

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
          <button
            type="button"
            className="primary compact"
            onClick={() => onWhatsApp(customer)}
          >
            WhatsApp
          </button>
        </div>
      </section>

      {finance && (
        <section className="card muted-card">
          <h2 className="section-title">Money</h2>
          <dl className="finance-grid">
            <div>
              <dt>Agreed</dt>
              <dd>{finance.total_agreed_label}</dd>
            </div>
            <div>
              <dt>Paid</dt>
              <dd>{finance.total_paid_label}</dd>
            </div>
            <div>
              <dt>Outstanding</dt>
              <dd className="outstanding">{finance.outstanding_label}</dd>
            </div>
          </dl>
          <div className="action-row tight">
            <button
              type="button"
              className="secondary"
              onClick={() => onAddJob(customer)}
            >
              Add job
            </button>
            <button
              type="button"
              className="secondary"
              onClick={() => onAddPayment(customer)}
            >
              Record payment
            </button>
          </div>
        </section>
      )}

      <section className="card muted-card">
        <div className="row-between">
          <h2 className="section-title">Jobs</h2>
          <button
            type="button"
            className="primary compact"
            onClick={() => onAddJob(customer)}
          >
            Add
          </button>
        </div>

        {jobs.length === 0 ? (
          <p className="status">No jobs yet.</p>
        ) : (
          <ul className="list compact-list">
            {jobs.map((job) => (
              <li key={job.id} className="stack-item">
                <div className="list-item static">
                  <span className="list-title">{job.title}</span>
                  <span className="list-meta">
                    {job.agreed_amount_label} · owes {job.outstanding_label}
                  </span>
                </div>
                <div className="action-row tight">
                  <button
                    type="button"
                    className="secondary"
                    onClick={() => onAddPayment(customer, job.id)}
                  >
                    Pay
                  </button>
                  <button
                    type="button"
                    className="secondary danger"
                    onClick={() => onArchiveJob(job)}
                  >
                    Archive
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card muted-card">
        <div className="row-between">
          <h2 className="section-title">Payments</h2>
          <button
            type="button"
            className="primary compact"
            onClick={() => onAddPayment(customer)}
          >
            Add
          </button>
        </div>

        {payments.length === 0 ? (
          <p className="status">No payments yet.</p>
        ) : (
          <ul className="list compact-list">
            {payments.map((payment) => (
              <li key={payment.id} className="stack-item">
                <div className="list-item static">
                  <span className="list-title">{payment.amount_label}</span>
                  <span className="list-meta">
                    {payment.paid_on} · {payment.method_label}
                    {payment.job_title ? ` · ${payment.job_title}` : ''}
                  </span>
                </div>
                <button
                  type="button"
                  className="secondary danger"
                  onClick={() => onArchivePayment(payment)}
                >
                  Archive
                </button>
              </li>
            ))}
          </ul>
        )}
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
