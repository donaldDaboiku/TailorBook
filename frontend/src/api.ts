export type Health = {
  status: string
  app: string
}

export async function fetchHealth(): Promise<Health> {
  const base = import.meta.env.VITE_API_URL ?? ''
  const response = await fetch(`${base}/api/health`, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error('unavailable')
  }

  const data: unknown = await response.json()

  if (!isHealth(data)) {
    throw new Error('unavailable')
  }

  return data
}

function isHealth(data: unknown): data is Health {
  if (typeof data !== 'object' || data === null) {
    return false
  }

  return (
    'status' in data &&
    'app' in data &&
    typeof data.status === 'string' &&
    typeof data.app === 'string' &&
    data.status === 'ok' &&
    data.app.length > 0
  )
}
