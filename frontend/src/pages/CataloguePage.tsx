import { useEffect, useState, type FormEvent } from 'react'
import {
  createCatalogueStyle,
  deleteCatalogueStyle,
  fetchCatalogue,
  firstError,
  type CatalogueStyle,
} from '../api'

export default function CataloguePage({ onBack }: { onBack: () => void }) {
  const [styles, setStyles] = useState<CatalogueStyle[]>([])
  const [name, setName] = useState('')
  const [price, setPrice] = useState('')
  const [image, setImage] = useState<File | null>(null)
  const [imageKey, setImageKey] = useState(0)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    fetchCatalogue()
      .then((rows) => {
        if (!cancelled) {
          setStyles(rows)
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
      const photo = image ? await shrinkStylePhoto(image).catch(() => image) : null
      const saved = await createCatalogueStyle({ name, price, image: photo })
      setStyles((current) =>
        [...current, saved].sort((a, b) => a.name.localeCompare(b.name)),
      )
      setName('')
      setPrice('')
      setImage(null)
      setImageKey((current) => current + 1)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  async function onRemove(style: CatalogueStyle) {
    setError('')

    try {
      await deleteCatalogueStyle(style.id)
      setStyles((current) => current.filter((row) => row.id !== style.id))
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
        Styles for this shop. A photo, a name, and the usual price. On a new
        job, tap a style to fill the outfit and the amount.
      </p>

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
          Photo
          <input
            key={imageKey}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(event) => setImage(event.target.files?.[0] ?? null)}
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
            {style.image_url && (
              <img src={style.image_url} alt="" />
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

async function shrinkStylePhoto(file: File): Promise<File> {
  const bitmap = await createImageBitmap(file)
  const max = 640
  const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(bitmap.width * scale))
  canvas.height = Math.max(1, Math.round(bitmap.height * scale))
  const context = canvas.getContext('2d')

  if (!context) {
    return file
  }

  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', 0.72)
  })

  return blob ? new File([blob], 'style.jpg', { type: 'image/jpeg' }) : file
}
