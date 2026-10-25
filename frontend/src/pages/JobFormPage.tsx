import { useEffect, useState, type FormEvent } from 'react'
import { createJob, fetchCatalogue, firstError, todayDate, type CatalogueStyle, type Customer } from '../api'
import { useI18n } from '../i18n'
import StylePhoto from '../StylePhoto'

export default function JobFormPage({
  customer,
  onCancel,
  onSaved,
}: {
  customer: Customer
  onCancel: () => void
  onSaved: () => void
}) {
  const { t } = useI18n()
  const [title, setTitle] = useState('')
  const [agreedAmount, setAgreedAmount] = useState('')
  const [serviceDate, setServiceDate] = useState(todayDate())
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
        {t('form.back', { name: customer.name })}
      </button>
      <h1>{t('job.title')}</h1>
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
          {t('form.date')}
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
          {busy ? t('form.saving') : t('job.save')}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          {t('form.cancel')}
        </button>
      </form>
    </section>
  )
}
