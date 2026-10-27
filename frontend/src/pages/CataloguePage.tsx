import { useEffect, useState, type FormEvent } from 'react'
import {
  createCatalogueStyle,
  deleteCatalogueStyle,
  fetchCatalogue,
  firstError,
  startCatalogueCheckout,
  type CatalogueAllowance,
  type CatalogueStyle,
} from '../api'
import StylePhoto from '../StylePhoto'

export default function CataloguePage({ onBack }: { onBack: () => void }) {
  const [styles, setStyles] = useState<CatalogueStyle[]>([])
  const [allowance, setAllowance] = useState<CatalogueAllowance | null>(null)
  const [name, setName] = useState('')
  const [price, setPrice] = useState('')
  const [front, setFront] = useState<File | null>(null)
  const [back, setBack] = useState<File | null>(null)
  const [side, setSide] = useState<File | null>(null)
  const [imageKey, setImageKey] = useState(0)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    fetchCatalogue()
      .then((list) => {
        if (!cancelled) {
          setStyles(list.styles)
          setAllowance(list.allowance)
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
  }, [])

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    try {
      const [photo, photoBack, photoSide] = await Promise.all([
        shrinkOrNull(front),
        shrinkOrNull(back),
        shrinkOrNull(side),
      ])
      const saved = await createCatalogueStyle({
        name,
        price,
        image: photo,
        imageBack: photoBack,
        imageSide: photoSide,
      })
      setStyles((current) =>
        [...current, saved].sort((a, b) => a.name.localeCompare(b.name)),
      )
      setAllowance((current) =>
        current ? { ...current, used: current.used + 1 } : current,
      )
      setName('')
      setPrice('')
      setFront(null)
      setBack(null)
      setSide(null)
      setImageKey((current) => current + 1)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onBuyPack() {
    setBusy(true)
    setError('')

    try {
      const checkout = await startCatalogueCheckout()
      window.location.assign(checkout.authorization_url)
    } catch (err) {
      setError(firstError(err))
      setBusy(false)
    }
  }

  async function onRemove(style: CatalogueStyle) {
    setError('')

    try {
      await deleteCatalogueStyle(style.id)
      setStyles((current) => current.filter((row) => row.id !== style.id))
      setAllowance((current) =>
        current ? { ...current, used: Math.max(0, current.used - 1) } : current,
      )
    } catch (err) {
      setError(firstError(err))
    }
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onBack}>
        Back
      </button>
      <h1>Design catalogue</h1>
      <p className="lede">
        Styles for this shop. Take a front, back, and side photo, plus a name
        and the usual price. On a phone, each photo opens the camera. On a new
        job, tap a style to fill the outfit and the amount. The first 40 are
        free. After that, 10 more cost ₦1,000.
      </p>

      {allowance && allowance.used >= allowance.limit && (
        <div className="form">
          <p className="status">
            This shop is full at {allowance.limit} styles. {allowance.pack} more
            cost {allowance.pack_label}.
          </p>
          {allowance.pack_enabled ? (
            <button
              type="button"
              className="primary"
              disabled={busy}
              onClick={() => void onBuyPack()}
            >
              {busy ? 'Opening Paystack…' : `Pay ${allowance.pack_label} for ${allowance.pack} styles`}
            </button>
          ) : (
            <p className="status">Paystack is not turned on yet, so more styles cannot be bought.</p>
          )}
        </div>
      )}

      <form className="form" onSubmit={onSubmit}>
        <label>
          Style
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Senator, gown, kaftan…"
            required
          />
        </label>
        <label>
          Usual price (₦)
          <input
            type="number"
            inputMode="decimal"
            min="1"
            step="0.01"
            value={price}
            onChange={(event) => setPrice(event.target.value)}
            required
          />
        </label>
        <label>
          Front
          <input
            key={`front-${imageKey}`}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            capture="environment"
            onChange={(event) => setFront(event.target.files?.[0] ?? null)}
          />
        </label>
        <label>
          Back
          <input
            key={`back-${imageKey}`}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            capture="environment"
            onChange={(event) => setBack(event.target.files?.[0] ?? null)}
          />
        </label>
        <label>
          Side
          <input
            key={`side-${imageKey}`}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            capture="environment"
            onChange={(event) => setSide(event.target.files?.[0] ?? null)}
          />
        </label>
        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Saving…' : 'Add style'}
        </button>
      </form>

      {loading && <p className="status">Loading styles…</p>}

      {!loading && styles.length === 0 && (
        <p className="status">No styles yet.</p>
      )}

      <ul className="style-list">
        {styles.map((style) => (
          <li key={style.id} className="style-card">
            {(style.has_image || style.has_back || style.has_side) && (
              <div className="style-angles">
                {style.has_image && (
                  <figure>
                    <StylePhoto id={style.id} />
                    <figcaption>Front</figcaption>
                  </figure>
                )}
                {style.has_back && (
                  <figure>
                    <StylePhoto id={style.id} view="back" />
                    <figcaption>Back</figcaption>
                  </figure>
                )}
                {style.has_side && (
                  <figure>
                    <StylePhoto id={style.id} view="side" />
                    <figcaption>Side</figcaption>
                  </figure>
                )}
              </div>
            )}
            <div>
              <strong>{style.name}</strong>
              <span className="status">{style.price_label}</span>
            </div>
            <button
              type="button"
              className="secondary"
              onClick={() => void onRemove(style)}
            >
              Remove
            </button>
          </li>
        ))}
      </ul>
    </section>
  )
}

async function shrinkOrNull(file: File | null): Promise<File | null> {
  if (!file) {
    return null
  }

  try {
    return await shrinkStylePhoto(file)
  } catch {
    if (file.type === 'image/jpeg' || file.type === 'image/png' || file.type === 'image/webp') {
      return file
    }

    throw new Error('Use a JPEG, PNG, or WebP photo.')
  }
}

async function shrinkStylePhoto(file: File): Promise<File> {
  const bitmap = await createImageBitmap(file)
  const max = 640
  const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(bitmap.width * scale))
  canvas.height = Math.max(1, Math.round(bitmap.height * scale))
  const context = canvas.getContext('2d')

  if (!context) {
    throw new Error('Use a JPEG, PNG, or WebP photo.')
  }

  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', 0.72)
  })

  if (!blob) {
    throw new Error('Use a JPEG, PNG, or WebP photo.')
  }

  return new File([blob], 'style.jpg', { type: 'image/jpeg' })
}
