import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  createCustomer,
  createMeasurement,
  firstError,
  getCustomer,
  listMeasurementTemplates,
  scanSlipPhoto,
  todayDate,
  updateCustomer,
  type Customer,
  type MeasurementTemplate,
  type MeasurementUnit,
  type SlipScanDraft,
} from '../api'
import { useAuth } from '../auth'
import { translatedFieldLabel, translatedTemplateName, useI18n } from '../i18n'
import { linkOnline } from '../outbox'

export default function ScanSlipPage({
  onCancel,
  onSaved,
}: {
  onCancel: () => void
  onSaved: (customer: Customer) => void
}) {
  const { user } = useAuth()
  const { t } = useI18n()
  const defaultUnit = user?.business?.measurement_unit ?? 'in'

  const [step, setStep] = useState<'pick' | 'review'>('pick')
  const [templates, setTemplates] = useState<MeasurementTemplate[]>([])
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [gender, setGender] = useState<'male' | 'female' | ''>('')
  const [style, setStyle] = useState('')
  const [note, setNote] = useState('')
  const [templateSlug, setTemplateSlug] = useState('female')
  const [unit, setUnit] = useState<MeasurementUnit>(defaultUnit)
  const [values, setValues] = useState<Record<string, string>>({})
  const [matchedId, setMatchedId] = useState<string | null>(null)
  const [matchedLabel, setMatchedLabel] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const template = useMemo(
    () => templates.find((item) => item.slug === templateSlug) ?? null,
    [templates, templateSlug],
  )

  useEffect(() => {
    let cancelled = false
    listMeasurementTemplates(true)
      .then((rows) => {
        if (!cancelled) {
          setTemplates(rows)
        }
      })
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    return () => {
      if (preview) {
        URL.revokeObjectURL(preview)
      }
    }
  }, [preview])

  function applyDraft(draft: SlipScanDraft) {
    setName(draft.name ?? '')
    setPhone(draft.phone ?? '')
    setGender(draft.gender ?? '')
    setStyle(draft.style ?? '')
    setNote(draft.note ?? '')
    setTemplateSlug(draft.template_slug || 'female')
    setUnit(draft.unit || defaultUnit)
    const next: Record<string, string> = {}
    for (const [key, amount] of Object.entries(draft.measurements ?? {})) {
      next[key] = String(amount)
    }
    setValues(next)
    if (draft.matched_customer) {
      setMatchedId(draft.matched_customer.id)
      setMatchedLabel(
        `${draft.matched_customer.name} · ${draft.matched_customer.phone}`,
      )
    } else {
      setMatchedId(null)
      setMatchedLabel('')
    }
  }

  async function onRead() {
    setError('')

    if (!linkOnline()) {
      setError('Connect to the internet to scan a slip.')
      return
    }

    if (!file) {
      setError('Choose a photo of the slip first.')
      return
    }

    setBusy(true)

    try {
      const draft = await scanSlipPhoto(file)
      applyDraft(draft)
      setStep('review')
    } catch (err) {
      if (err instanceof TypeError && err.message === 'offline') {
        setError('Connect to the internet to scan a slip.')
      } else {
        setError(firstError(err))
      }
    } finally {
      setBusy(false)
    }
  }

  function setValue(key: string, next: string) {
    setValues((current) => ({ ...current, [key]: next }))
  }

  function buildNotes(existing: string | null | undefined): string {
    const parts: string[] = []
    if (style.trim()) {
      parts.push(`Style: ${style.trim()}`)
    }
    if (note.trim()) {
      parts.push(note.trim())
    }
    const scanned = parts.join('\n')
    const prior = (existing ?? '').trim()
    if (!scanned) {
      return prior
    }
    if (!prior) {
      return scanned
    }
    if (prior.includes(scanned)) {
      return prior
    }
    return `${prior}\n${scanned}`
  }

  async function onSave(event: FormEvent) {
    event.preventDefault()
    setError('')

    const trimmedName = name.trim()
    const trimmedPhone = phone.trim()

    if (!trimmedName || !trimmedPhone) {
      setError('Name and mobile number are required.')
      return
    }

    if (!template) {
      setError('Choose a measurement template.')
      return
    }

    const payloadValues: Record<string, number> = {}
    for (const field of template.fields) {
      const raw = values[field.key]?.trim()
      if (!raw) {
        continue
      }
      const number = Number(raw)
      if (!Number.isFinite(number) || number <= 0) {
        setError(
          t('measure.positive', {
            field: translatedFieldLabel(t, field.key, field.label),
          }),
        )
        return
      }
      payloadValues[field.key] = number
    }

    if (Object.keys(payloadValues).length === 0) {
      setError(t('measure.needOne'))
      return
    }

    setBusy(true)

    try {
      let customer: Customer

      if (matchedId) {
        const existing = await getCustomer(matchedId)
        customer = await updateCustomer(matchedId, {
          name: trimmedName,
          phone: trimmedPhone,
          gender: gender || '',
          notes: buildNotes(existing.notes),
        })
      } else {
        customer = await createCustomer({
          name: trimmedName,
          phone: trimmedPhone,
          gender: gender || '',
          notes: buildNotes(null),
        })
      }

      await createMeasurement(customer.id, {
        template_slug: template.slug,
        template_id: template.id,
        template_name: template.name,
        taken_on: todayDate(),
        unit,
        values: payloadValues,
      })

      onSaved(customer)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onCancel}>
        Back to clients
      </button>
      <h1>Scan slip</h1>
      <p className="lede">
        Photo a handwritten slip. We read name, phone, style, note, and
        measurements. You check them, then we save the client and measurements
        only. Needs internet.
      </p>

      {step === 'pick' && (
        <div className="form">
          <label>
            Slip photo
            <input
              type="file"
              accept="image/*"
              capture="environment"
              onChange={(event) => {
                const next = event.target.files?.[0] ?? null
                setFile(next)
                setPreview((current) => {
                  if (current) {
                    URL.revokeObjectURL(current)
                  }
                  return next ? URL.createObjectURL(next) : null
                })
              }}
            />
          </label>

          {preview && (
            <img
              src={preview}
              alt="Slip preview"
              style={{
                width: '100%',
                maxHeight: '16rem',
                objectFit: 'contain',
                borderRadius: 12,
                border: '1px solid var(--line)',
                background: '#fff',
              }}
            />
          )}

          {error && (
            <p className="form-error" role="alert">
              {error}
            </p>
          )}

          <button
            type="button"
            className="primary"
            disabled={busy || !file}
            onClick={() => void onRead()}
          >
            {busy ? 'Reading slip…' : 'Read slip'}
          </button>
        </div>
      )}

      {step === 'review' && (
        <form className="form" onSubmit={onSave}>
          {matchedLabel && (
            <p className="status">
              Matched existing client: {matchedLabel}. Saving will update that
              client and add a new measurement set.
            </p>
          )}

          <label>
            Name
            <input
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
            />
          </label>

          <label>
            Mobile number
            <input
              type="tel"
              inputMode="tel"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
              required
            />
          </label>

          <fieldset className="unit-field">
            <legend>Gender</legend>
            <label className="choice">
              <input
                type="radio"
                name="scan-gender"
                checked={gender === 'female'}
                onChange={() => setGender('female')}
              />
              Female
            </label>
            <label className="choice">
              <input
                type="radio"
                name="scan-gender"
                checked={gender === 'male'}
                onChange={() => setGender('male')}
              />
              Male
            </label>
          </fieldset>

          <label>
            Style (saved in notes)
            <input
              value={style}
              onChange={(event) => setStyle(event.target.value)}
              placeholder="Ceremony dress"
            />
          </label>

          <label>
            Note
            <textarea
              value={note}
              onChange={(event) => setNote(event.target.value)}
              rows={3}
            />
          </label>

          <label>
            {t('measure.template')}
            <select
              value={templateSlug}
              onChange={(event) => setTemplateSlug(event.target.value)}
            >
              {templates.map((item) => (
                <option key={item.id} value={item.slug}>
                  {translatedTemplateName(t, item.slug, item.name)}
                </option>
              ))}
            </select>
          </label>

          <fieldset className="unit-field">
            <legend>{t('measure.unit')}</legend>
            <label className="choice">
              <input
                type="radio"
                name="scan-unit"
                checked={unit === 'in'}
                onChange={() => setUnit('in')}
              />
              Inches
            </label>
            <label className="choice">
              <input
                type="radio"
                name="scan-unit"
                checked={unit === 'cm'}
                onChange={() => setUnit('cm')}
              />
              Centimetres
            </label>
          </fieldset>

          {template?.fields.map((field) => (
            <label key={field.key}>
              {translatedFieldLabel(t, field.key, field.label)}
              <input
                inputMode="decimal"
                value={values[field.key] ?? ''}
                onChange={(event) => setValue(field.key, event.target.value)}
              />
            </label>
          ))}

          {error && (
            <p className="form-error" role="alert">
              {error}
            </p>
          )}

          <button type="submit" className="primary" disabled={busy}>
            {busy ? 'Saving…' : 'Save client & measurements'}
          </button>
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={() => {
              setStep('pick')
              setError('')
            }}
          >
            Choose another photo
          </button>
        </form>
      )}
    </section>
  )
}
