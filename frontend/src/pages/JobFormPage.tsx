import { useEffect, useState, type FormEvent } from 'react'
import {
  createJob,
  fetchCatalogue,
  firstError,
  todayDate,
  updateJob,
  type CatalogueStyle,
  type Customer,
  type CustomerJob,
} from '../api'
import { useI18n } from '../i18n'
import StylePhoto from '../StylePhoto'

function datePlusDays(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

function daysFromToday(iso: string): number {
  const [year, month, day] = iso.split('-').map(Number)
  const target = new Date(year, month - 1, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  target.setHours(0, 0, 0, 0)

  return Math.round((target.getTime() - today.getTime()) / 86400000)
}

function startingDays(job?: CustomerJob): string {
  if (!job) {
    return ''
  }

  if (job.finish_days !== null) {
    return String(job.finish_days)
  }

  const diff = daysFromToday(job.service_date)

  return diff >= 0 ? String(diff) : ''
}

export default function JobFormPage({
  customer,
  job,
  onCancel,
  onSaved,
}: {
  customer: Customer
  job?: CustomerJob
  onCancel: () => void
  onSaved: () => void
}) {
  const { t } = useI18n()
  const [title, setTitle] = useState(job?.title ?? '')
  const [agreedAmount, setAgreedAmount] = useState(job?.agreed_amount ?? '')
  const [days, setDays] = useState(startingDays(job))
  const [serviceDate, setServiceDate] = useState(job?.service_date ?? todayDate())
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [styles, setStyles] = useState<CatalogueStyle[]>([])
  const [picked, setPicked] = useState('')

  useEffect(() => {
    let cancelled = false

    fetchCatalogue()
      .then((list) => {
        if (!cancelled) {
          setStyles(list.styles)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setStyles([])
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    const typed = Number(days)
    const finishDays =
      Number.isInteger(typed) && typed >= 0 && typed <= 365
        ? typed
        : daysFromToday(serviceDate)

    if (finishDays < 0 || finishDays > 365) {
      setError(t('job.daysNote'))
      setBusy(false)
      return
    }

    const payload = {
      title: title.trim(),
      agreed_amount: agreedAmount,
      service_date: serviceDate,
      finish_days: finishDays,
    }

    try {
      if (job) {
        await updateJob(customer.id, job.id, payload)
      } else {
        await createJob(customer.id, payload)
      }
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
        {t('form.back', { name: customer.name })}
      </button>
      <h1>{job ? t('job.edit') : t('job.title')}</h1>
      <p className="lede">{t('job.lede', { name: customer.name })}</p>

      <form className="form" onSubmit={onSubmit}>
        {styles.length > 0 && (
          <div>
            <p className="section-title">{t('job.styles')}</p>
            <p className="status">{t('job.stylesNote')}</p>
            <div className="style-picks">
              {styles.map((style) => (
                <button
                  key={style.id}
                  type="button"
                  className={picked === style.id ? 'style-pick active' : 'style-pick'}
                  onClick={() => {
                    setPicked(style.id)
                    setTitle(style.name)
                    setAgreedAmount(style.price)
                  }}
                >
                  {style.has_image && <StylePhoto id={style.id} />}
                  <strong>{style.name}</strong>
                  <span>{style.price_label}</span>
                </button>
              ))}
            </div>
          </div>
        )}

        <label>
          {t('job.job')}
          <input
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            placeholder={t('job.placeholder')}
            required
          />
        </label>

        <label>
          {t('job.amount')}
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
          {t('job.days')}
          <input
            type="number"
            inputMode="numeric"
            min="0"
            max="365"
            step="1"
            value={days}
            onChange={(event) => {
              const next = event.target.value
              setDays(next)
              const count = Number(next)
              if (Number.isInteger(count) && count >= 0 && count <= 365) {
                setServiceDate(datePlusDays(count))
              }
            }}
            required
          />
        </label>
        <p className="status">{t('job.daysNote')}</p>

        <label>
          {t('job.due')}
          <input
            type="date"
            value={serviceDate}
            onChange={(event) => {
              const next = event.target.value
              setServiceDate(next)
              const diff = daysFromToday(next)
              if (diff >= 0 && diff <= 365) {
                setDays(String(diff))
              }
            }}
            required
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? t('form.saving') : t('job.save')}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          {t('form.cancel')}
        </button>
      </form>
    </section>
  )
}
