import { useEffect, useState } from 'react'
import { useI18n } from './i18n'
import {
  listOutbox,
  retryAttention,
  subscribeOutbox,
  type OutboxItem,
} from './outbox'

export function useOutbox(): { items: OutboxItem[]; pendingIds: Set<string> } {
  const [items, setItems] = useState<OutboxItem[]>([])

  useEffect(() => {
    let cancelled = false
    const refresh = () => {
      void listOutbox().then((rows) => {
        if (!cancelled) {
          setItems(rows)
        }
      })
    }
    refresh()
    const unsubscribe = subscribeOutbox(refresh)
    window.addEventListener('online', refresh)
    window.addEventListener('offline', refresh)
    return () => {
      cancelled = true
      unsubscribe()
      window.removeEventListener('online', refresh)
      window.removeEventListener('offline', refresh)
    }
  }, [])

  return {
    items,
    pendingIds: new Set(items.map((item) => item.recordId)),
  }
}

export function PendingMark({ id }: { id: string }) {
  const { t } = useI18n()
  const { pendingIds } = useOutbox()

  if (!pendingIds.has(id)) {
    return null
  }

  return <span className="badge warn pending-mark">{t('sync.pending')}</span>
}

export default function SyncBanner() {
  const { t } = useI18n()
  const { items } = useOutbox()
  const [open, setOpen] = useState(false)
  const online = typeof navigator === 'undefined' ? true : navigator.onLine
  const waiting = items.filter((item) => item.status === 'pending')
  const attention = items.filter((item) => item.status === 'attention')

  let label = online ? t('sync.online') : t('sync.offline')
  if (waiting.length > 0) {
    label = t('sync.waiting', { count: String(waiting.length) })
  }

  return (
    <div className="sync-banner">
      <p>
        <strong>{label}</strong>
        {attention.length > 0 && (
          <>
            {' '}
            <button type="button" className="link" onClick={() => setOpen((value) => !value)}>
              {t('sync.attention')} ({attention.length})
            </button>
          </>
        )}
      </p>
      {(waiting.length > 0 || attention.length > 0) && (
        <button type="button" className="secondary compact" onClick={() => void retryAttention()}>
          {t('sync.now')}
        </button>
      )}
      {open && attention.length > 0 && (
        <ul className="sync-attention">
          {attention.map((item) => (
            <li key={item.id}>{item.error}</li>
          ))}
        </ul>
      )}
    </div>
  )
}
