import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  firstError,
  listMeasurementTemplates,
  updateMeasurementTemplateFields,
  type MeasurementField,
  type MeasurementTemplate,
} from '../api'

type FieldDraft = {
  key: string
  label: string
  enabled: boolean
}

export default function MeasurementFieldsSettings() {
  const [templates, setTemplates] = useState<MeasurementTemplate[]>([])
  const [slug, setSlug] = useState('female')
  const [drafts, setDrafts] = useState<FieldDraft[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)
  const [open, setOpen] = useState(false)

  const active = useMemo(
    () => templates.find((item) => item.slug === slug) ?? null,
    [templates, slug],
  )

  useEffect(() => {
    let cancelled = false

    listMeasurementTemplates(false)
      .then((rows) => {
        if (cancelled) {
          return
        }

        setTemplates(rows)
        const first = rows[0]?.slug ?? 'female'
        setSlug(first)
        setDrafts(toDrafts(rows.find((row) => row.slug === first)?.fields ?? []))
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
  }, [])

  useEffect(() => {
    if (!open) {
      return
    }

    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }

    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  function onPickTemplate(nextSlug: string) {
    setSlug(nextSlug)
    setSaved(false)
    setError('')
    const fields = templates.find((row) => row.slug === nextSlug)?.fields ?? []
    setDrafts(toDrafts(fields))
  }

  function updateDraft(key: string, patch: Partial<FieldDraft>) {
    setDrafts((current) =>
      current.map((row) => (row.key === key ? { ...row, ...patch } : row)),
    )
    setSaved(false)
  }

  async function onSave(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setSaved(false)

    try {
      const updated = await updateMeasurementTemplateFields(
        slug,
        drafts.map((row) => ({
          key: row.key,
          label: row.label.trim(),
          enabled: row.enabled,
        })),
      )

      setTemplates((current) =>
        current.map((row) => (row.slug === updated.slug ? updated : row)),
      )
      setDrafts(toDrafts(updated.fields))
      setSaved(true)
      setOpen(false)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <section className="card muted-card">
        <h2 className="section-title">Measurement fields</h2>
        <p className="lede">
          Rename terms for your shop, and turn off fields you do not use.
        </p>
        <button
          type="button"
          className="secondary"
          onClick={() => setOpen(true)}
          disabled={loading}
        >
          {loading ? 'Loading fields…' : 'Edit measurement fields'}
        </button>
        {saved && <p className="status">Measurement fields saved.</p>}
        {error && !open && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
      </section>

      {open && (
        <div
          className="modal-backdrop"
          role="presentation"
          onClick={(event) => {
            if (event.target === event.currentTarget) {
              setOpen(false)
            }
          }}
        >
          <div
            className="modal-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="measurement-fields-title"
          >
            <div className="row-between">
              <h2 className="section-title" id="measurement-fields-title">
                Measurement fields
              </h2>
              <button
                type="button"
                className="secondary"
                onClick={() => setOpen(false)}
              >
                Close
              </button>
            </div>

            <div className="template-picks" role="group" aria-label="Template">
              {templates.map((template) => (
                <button
                  key={template.id}
                  type="button"
                  className={
                    template.slug === slug
                      ? 'secondary template-pick active'
                      : 'secondary template-pick'
                  }
                  onClick={() => onPickTemplate(template.slug)}
                >
                  {template.name}
                </button>
              ))}
            </div>

            <form className="form" onSubmit={onSave}>
              <div className="field-pref-scroll">
                {drafts.map((field) => (
                  <div key={field.key} className="field-pref">
                    <label className="choice">
                      <input
                        type="checkbox"
                        checked={field.enabled}
                        onChange={(event) =>
                          updateDraft(field.key, { enabled: event.target.checked })
                        }
                      />
                      Use this field
                    </label>
                    <label>
                      Name
                      <input
                        value={field.label}
                        onChange={(event) =>
                          updateDraft(field.key, { label: event.target.value })
                        }
                        required={field.enabled}
                        disabled={!field.enabled}
                      />
                    </label>
                  </div>
                ))}
                {active && drafts.length === 0 && (
                  <p className="status">No fields on this template.</p>
                )}
              </div>

              {error && (
                <p className="form-error" role="alert">
                  {error}
                </p>
              )}

              <button type="submit" className="primary" disabled={busy}>
                {busy ? 'Saving…' : 'Save fields'}
              </button>
            </form>
          </div>
        </div>
      )}
    </>
  )
}

function toDrafts(fields: MeasurementField[]): FieldDraft[] {
  return fields.map((field) => ({
    key: field.key,
    label: field.label,
    enabled: field.enabled !== false,
  }))
}
