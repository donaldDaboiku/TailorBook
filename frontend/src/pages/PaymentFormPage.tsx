import { useEffect, useState, type FormEvent } from 'react'
import {
  createPayment,
  firstError,
  listJobs,
  todayDate,
  type Customer,
  type CustomerJob,
  type PaymentMethod,
} from '../api'
import { useI18n } from '../i18n'

export default function PaymentFormPage({
  customer,
  preferredJobId,
  onCancel,
  onSaved,
}: {
  customer: Customer
  preferredJobId?: string
  onCancel: () => void
  onSaved: () => void
}) {
  const { t } = useI18n()
  const [jobs, setJobs] = useState<CustomerJob[]>([])
  const [amount, setAmount] = useState('')
  const [paidOn, setPaidOn] = useState(todayDate())
  const [method, setMethod] = useState<PaymentMethod>('cash')
  const [jobId, setJobId] = useState(preferredJobId ?? '')
  const [reference, setReference] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    listJobs(customer.id)
      .then((rows) => {
        if (!cancelled) {
          setJobs(rows)
          if (preferredJobId) {
            setJobId(preferredJobId)
          }
        }
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
  }, [customer.id, preferredJobId])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      await createPayment(customer.id, {
        amount,
        paid_on: paidOn,
        method,
        customer_job_id: jobId || undefined,
        reference,
        note,
      })
      onSaved()
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <p className="status">{t('pay.loading')}</p>
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onCancel}>
        {t('form.back', { name: customer.name })}
      </button>
      <h1>{t('pay.title')}</h1>
      <p className="lede">{t('pay.lede', { name: customer.name })}</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          {t('pay.amount')}
          <input
            type="number"
            inputMode="decimal"
            min="1"
            step="0.01"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            required
          />
        </label>

        <label>
          {t('pay.method')}
          <select
            value={method}
            onChange={(event) => setMethod(event.target.value as PaymentMethod)}
          >
            <option value="cash">{t('pay.cash')}</option>
            <option value="bank_transfer">{t('pay.transfer')}</option>
            <option value="pos">{t('pay.pos')}</option>
            <option value="other">{t('pay.other')}</option>
          </select>
        </label>

        <label>
          {t('pay.forJob')}
          <select
            value={jobId}
            onChange={(event) => setJobId(event.target.value)}
          >
            <option value="">{t('pay.general')}</option>
            {jobs.map((job) => (
              <option key={job.id} value={job.id}>
                {job.title} · {t('pay.owes', { amount: job.outstanding_label })}
              </option>
            ))}
          </select>
        </label>

        <label>
          {t('form.date')}
          <input
            type="date"
            value={paidOn}
            onChange={(event) => setPaidOn(event.target.value)}
            required
          />
        </label>

        <label>
          {t('pay.reference')}
          <input
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            placeholder={t('pay.referenceHint')}
          />
        </label>

        <label>
          {t('pay.note')}
          <textarea
            value={note}
            onChange={(event) => setNote(event.target.value)}
            rows={2}
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? t('form.saving') : t('pay.save')}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          {t('form.cancel')}
        </button>
      </form>
    </section>
  )
}
