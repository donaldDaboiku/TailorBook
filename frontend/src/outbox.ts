const TOKEN_KEY = 'tailormate_token'
const DB_NAME = 'tailormate-sync'

export type OutboxStatus = 'pending' | 'attention'

export type OutboxItem = {
  id: string
  recordId: string
  kind: string
  path: string
  method: 'POST' | 'PUT'
  body: Record<string, unknown>
  snapshot: unknown
  cacheKey?: string
  recordKey?: string
  at: number
  status: OutboxStatus
  error: string
}

const listeners = new Set<() => void>()

export function subscribeOutbox(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

function notify(): void {
  listeners.forEach((listener) => listener())
}

let reachable = true

export function linkOnline(): boolean {
  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return false
  }

  return reachable
}

export function noteReached(ok: boolean): void {
  if (reachable === ok) {
    return
  }

  reachable = ok
  notify()
}

export function isNetworkFailure(error: unknown): boolean {
  if (error instanceof TypeError) {
    return true
  }

  return error instanceof Error && (error.name === 'TimeoutError' || error.name === 'AbortError')
}

export function shouldQueue(error: unknown): boolean {
  return isNetworkFailure(error) || (error instanceof Error && error.message === 'unauthorized')
}

function database(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1)
    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains('outbox')) {
        db.createObjectStore('outbox', { keyPath: 'id' })
      }
      if (!db.objectStoreNames.contains('cache')) {
        db.createObjectStore('cache')
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

function requestToPromise<T>(request: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

export async function cacheGet<T>(key: string): Promise<T | null> {
  const db = await database()
  const value = await requestToPromise(
    db.transaction('cache').objectStore('cache').get(key),
  )
  return (value as T | undefined) ?? null
}

export async function cachePut(key: string, value: unknown): Promise<void> {
  const db = await database()
  await requestToPromise(
    db.transaction('cache', 'readwrite').objectStore('cache').put(value, key),
  )
}

export async function enqueue(item: OutboxItem): Promise<void> {
  const db = await database()
  await requestToPromise(
    db.transaction('outbox', 'readwrite').objectStore('outbox').put(item),
  )
  notify()
}

export async function listOutbox(): Promise<OutboxItem[]> {
  const db = await database()
  const rows = await requestToPromise(
    db.transaction('outbox').objectStore('outbox').getAll(),
  )
  return (rows as OutboxItem[]).sort((left, right) => left.at - right.at)
}

async function removeOutbox(id: string): Promise<void> {
  const db = await database()
  await requestToPromise(
    db.transaction('outbox', 'readwrite').objectStore('outbox').delete(id),
  )
}

async function saveOutbox(item: OutboxItem): Promise<void> {
  const db = await database()
  await requestToPromise(
    db.transaction('outbox', 'readwrite').objectStore('outbox').put(item),
  )
}

function apiUrl(path: string): string {
  const base = import.meta.env.VITE_API_URL ?? ''
  return `${base}${path}`
}

let syncing = false
let attempt = 0
let timer = 0

export async function retryAttention(): Promise<void> {
  const items = await listOutbox()

  for (const item of items) {
    if (item.status === 'attention') {
      await saveOutbox({ ...item, status: 'pending', error: '' })
    }
  }

  notify()
  await syncNow()
}

export async function syncNow(): Promise<void> {
  if (syncing || !linkOnline()) {
    return
  }

  const token = localStorage.getItem(TOKEN_KEY)
  if (!token) {
    return
  }

  syncing = true

  try {
    const items = (await listOutbox()).filter((item) => item.status === 'pending')

    for (const item of items) {
      let response: Response

      try {
        response = await fetch(apiUrl(item.path), {
          method: item.method,
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
            'Idempotency-Key': item.id,
          },
          body: JSON.stringify(item.body),
          signal: AbortSignal.timeout(8000),
        })
      } catch (error) {
        if (isNetworkFailure(error)) {
          noteReached(false)
        }
        break
      }

      noteReached(true)

      if (response.status === 401) {
        break
      }

      if (response.ok) {
        const row = await syncedRow(response)
        if (row) {
          await adoptSyncedRow(item, row)
        }
        await removeOutbox(item.id)
        attempt = 0
        continue
      }

      if ([403, 404, 409, 422].includes(response.status)) {
        const message = await rejectionMessage(response)
        await saveOutbox({ ...item, status: 'attention', error: message })
        continue
      }

      break
    }
  } finally {
    syncing = false
    notify()
    schedule()
  }
}

async function syncedRow(response: Response): Promise<{ id: string } | null> {
  try {
    const body = (await response.json()) as { data?: unknown }
    if (
      body.data &&
      typeof body.data === 'object' &&
      'id' in body.data &&
      typeof body.data.id === 'string'
    ) {
      return body.data as { id: string }
    }
  } catch {
    return null
  }

  return null
}

async function adoptSyncedRow(item: OutboxItem, row: { id: string }): Promise<void> {
  if (item.cacheKey) {
    const rows = await cacheGet<Array<{ id: string }>>(item.cacheKey)
    if (Array.isArray(rows)) {
      const next = rows.some((entry) => entry?.id === row.id)
        ? rows.map((entry) => (entry?.id === row.id ? row : entry))
        : [row, ...rows]
      await cachePut(item.cacheKey, next)
    }
  }

  if (item.recordKey) {
    await cachePut(item.recordKey, row)
  }

  if (!hasJobShape(row)) {
    return
  }

  const shopJobs = await cacheGet<{ upcoming: Array<{ id: string }>; overdue: Array<{ id: string }> }>(
    'shop-jobs',
  )
  if (!shopJobs) {
    return
  }

  const patch = (jobs: Array<{ id: string }>) =>
    jobs.map((job) => (job.id === row.id ? { ...job, ...row } : job))

  await cachePut('shop-jobs', {
    upcoming: patch(shopJobs.upcoming ?? []),
    overdue: patch(shopJobs.overdue ?? []),
  })
}

function hasJobShape(row: { id: string }): boolean {
  return 'title' in row && 'service_date' in row && 'customer_id' in row
}

async function rejectionMessage(response: Response): Promise<string> {
  try {
    const data = (await response.json()) as { message?: string; errors?: Record<string, string[]> }
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : undefined
    return first || data.message || 'The server rejected this change.'
  } catch {
    return 'The server rejected this change.'
  }
}

function schedule(): void {
  window.clearTimeout(timer)
  void listOutbox().then((items) => {
    if (!items.some((item) => item.status === 'pending')) {
      attempt = 0
      return
    }

    const delays = [5000, 15000, 60000, 300000]
    const delay = delays[Math.min(attempt, delays.length - 1)]
    attempt += 1
    timer = window.setTimeout(() => {
      void syncNow()
    }, delay)
  })
}

async function probe(): Promise<void> {
  try {
    const response = await fetch(apiUrl('/api/health'), {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(8000),
    })
    if (!response.ok) {
      return
    }
    noteReached(true)
    void syncNow()
  } catch (error) {
    if (isNetworkFailure(error)) {
      noteReached(false)
    }
  }
}

export function watchConnection(): void {
  window.addEventListener('online', () => {
    reachable = true
    notify()
    attempt = 0
    void syncNow()
  })
  window.addEventListener('offline', () => {
    reachable = false
    notify()
  })
  window.setInterval(() => {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      if (reachable) {
        reachable = false
        notify()
      }
      return
    }

    if (!reachable) {
      void probe()
    }
  }, 4000)
  void syncNow()
}
