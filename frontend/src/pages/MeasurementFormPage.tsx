import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  createMeasurement,
  firstError,
  listMeasurementTemplates,
  stepForUnit,
  todayDate,
  type Customer,
  type Measurement,
  type MeasurementTemplate,
  type MeasurementUnit,
} from '../api'
import { useAuth } from '../auth'
import { translatedFieldLabel, translatedTemplateName, useI18n } from '../i18n'

export default function MeasurementFormPage({
  customer,
  seed,
  onCancel,
  onSaved,
}: {
  customer: Customer
  seed?: Measurement | null
  onCancel: () => void
  onSaved: (measurement: Measurement) => void
}) {
  const { user } = useAuth()
  const { t } = useI18n()
  const defaultUnit = user?.business?.measurement_unit ?? 'in'
  const [templates, setTemplates] = useState<MeasurementTemplate[]>([])
  const [templateSlug, setTemplateSlug] = useState(seed?.template.slug ?? 'female')
  const [unit, setUnit] = useState<MeasurementUnit>(seed?.unit ?? defaultUnit)
  const [takenOn, setTakenOn] = useState(todayDate())
  const [values, setValues] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  const template = useMemo(
    () => templates.find((item) => item.slug === templateSlug) ?? null,
    [templates, templateSlug],
  )

  useEffect(() => {
    let cancelled = false

    listMeasurementTemplates(true)
      .then((rows) => {
        if (cancelled) {
          return
        }

        setTemplates(rows)

        const preferred =
          seed?.template.slug ??
          (customer.gender === 'male'
            ? 'male'
            : customer.gender === 'female'
              ? 'female'
              : rows[0]?.slug)

        if (preferred) {
          setTemplateSlug(preferred)
        }

        if (seed) {
          const next: Record<string, string> = {}
          for (const value of seed.values) {
            next[value.key] = value.value
          }
          setValues(next)
          setUnit(seed.unit)
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
  }, [customer.gender, seed])

  function setValue(key: string, next: string) {
    setValues((current) => ({ ...current, [key]: next }))
  }

  function nudge(key: string, direction: 1 | -1) {
    const step = stepForUnit(unit)
    const current = Number(values[key] || 0)
    const next = Math.max(step, Math.round((current + direction * step) / step) * step)
    setValue(key, unit === 'in' ? next.toFixed(2) : next.toFixed(1))
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()

    if (!template) {
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
        setError(t('measure.positive', { field: translatedFieldLabel(t, field.key, field.label) }))
        return
      }

      payloadValues[field.key] = number
    }

    if (Object.keys(payloadValues).length === 0) {
      setError(t('measure.needOne'))
      return
    }

    setBusy(true)
    setError('')

    try {
      const saved = await createMeasurement(customer.id, {
        template_slug: template.slug,
        taken_on: takenOn,
        unit,
        values: payloadValues,
      })
      onSaved(saved)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <p className="status">{t('measure.loading')}</p>
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onCancel}>
        {t('form.back', { name: customer.name })}
      </button>
      <h1>{t('measure.title')}</h1>
      <p className="lede">
        {t('measure.lede', { name: customer.name })}
        {seed ? t('measure.seed') : ''}
      </p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          {t('measure.template')}
          <select
            value={templateSlug}
            onChange={(event) => {
              setTemplateSlug(event.target.value)
              if (!seed) {
                setValues({})
              }
            }}
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
              name="unit"
              checked={unit === 'in'}
              onChange={() => setUnit('in')}
            />
            {t('measure.inches')}
          </label>
          <label className="choice">
            <input
              type="radio"
              name="unit"
              checked={unit === 'cm'}
              onChange={() => setUnit('cm')}
            />
            {t('measure.cm')}
          </label>
        </fieldset>

        <label>
          {t('form.date')}
          <input
            type="date"
            value={takenOn}
            onChange={(event) => setTakenOn(event.target.value)}
            required
          />
        </label>

        {template?.fields.map((field) => (
          <label key={field.id} className="measure-field">
            {translatedFieldLabel(t, field.key, field.label)}
            <div className="stepper">
              <button
                type="button"
                aria-label={t('measure.decrease', {
                  field: translatedFieldLabel(t, field.key, field.label),
                })}
                onClick={() => nudge(field.key, -1)}
              >
                −
              </button>
              <input
                type="number"
                inputMode="decimal"
                step={stepForUnit(unit)}
                min={stepForUnit(unit)}
                value={values[field.key] ?? ''}
                onChange={(event) => setValue(field.key, event.target.value)}
              />
              <button
                type="button"
                aria-label={t('measure.increase', {
                  field: translatedFieldLabel(t, field.key, field.label),
                })}
                onClick={() => nudge(field.key, 1)}
              >
                +
              </button>
            </div>
          </label>
        ))}

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? t('form.saving') : t('measure.save')}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          {t('form.cancel')}
        </button>
      </form>
    </section>
  )
}
