import { useEffect, useState } from 'react'
import { fetchCatalogueImage } from './api'

export default function StylePhoto({ id }: { id: string }) {
  const [src, setSrc] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    let objectUrl: string | null = null

    fetchCatalogueImage(id)
      .then((url) => {
        if (!active) {
          if (url) {
            URL.revokeObjectURL(url)
          }
          return
        }

        objectUrl = url
        setSrc(url)
      })
      .catch(() => {
        if (active) {
          setSrc(null)
        }
      })

    return () => {
      active = false
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [id])

  if (!src) {
    return null
  }

  return <img src={src} alt="" />
}
