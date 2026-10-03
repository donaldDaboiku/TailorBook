import { useEffect, useMemo, useState } from 'react'
import {
  fetchWhatsAppTemplates,
  firstError,
  whatsappUrlWithText,
  type Customer,
  type WhatsAppTemplateKey,
  type WhatsAppTemplates,
} from '../api'

export default function WhatsAppComposePage({
  customer,
  onBack,
}: {
  customer: Customer
  onBack: () => void
}) {
  const [bundle, setBundle] = useState<WhatsAppTemplates | null>(null)
  const [templateKey, setTemplateKey] = useState<WhatsAppTemplateKey>('hello')
  const [text, setText] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    fetchWhatsAppTemplates(customer.id)
      .then((data) => {
        if (cancelled) {
          return
        }

        setBundle(data)
        setTemplateKey(data.templates[0]?.key ?? 'hello')
        setText(data.templates[0]?.body ?? '')
        setError('')
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
  }, [customer.id])

  const openUrl = useMemo(() => {
    if (!bundle) {
      return ''
    }

    return whatsappUrlWithText(bundle.whatsapp_number, text)
  }, [bundle, text])

  function onPick(key: WhatsAppTemplateKey) {
    const template = bundle?.templates.find((row) => row.key === key)
    setTemplateKey(key)
    if (template) {
      setText(template.body)
    }
  }

  if (loading) {
    return <p className="status">Loading WhatsApp…</p>
  }

  if (error && !bundle) {
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

  return (
    <>
      <section className="card">
        <button type="button" className="link back-link" onClick={onBack}>
          Back to {customer.name}
        </button>
        <h1>WhatsApp</h1>
        <p className="lede">
          Message {customer.name} on {bundle?.phone ?? customer.whatsapp_phone}.
        </p>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">Template</h2>
        <div className="template-picks" role="group" aria-label="Message template">
          {bundle?.templates.map((template) => (
            <button
              key={template.key}
              type="button"
              className={
                template.key === templateKey
                  ? 'secondary template-pick active'
                  : 'secondary template-pick'
              }
              onClick={() => onPick(template.key)}
            >
              {template.label}
            </button>
          ))}
        </div>
      </section>

      <section className="card muted-card">
        <form
          className="form"
          onSubmit={(event) => {
            event.preventDefault()
            if (openUrl) {
              window.open(openUrl, '_blank', 'noopener,noreferrer')
            }
          }}
        >
          <label>
            Message
            <textarea
              value={text}
              onChange={(event) => setText(event.target.value)}
              rows={6}
              required
            />
          </label>

          {error && (
            <p className="form-error" role="alert">
              {error}
            </p>
          )}

          <button type="submit" className="primary" disabled={!openUrl || !text.trim()}>
            Open WhatsApp
          </button>
          <button type="button" className="secondary" onClick={onBack}>
            Cancel
          </button>
        </form>
      </section>
    </>
  )
}
