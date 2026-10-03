import { useEffect, useState } from 'react'
import { firstError, listCustomers, type Customer } from '../api'

export default function CustomersPage({
  onOpen,
  onCreate,
}: {
  onOpen: (id: string) => void
  onCreate: () => void
}) {
  const [query, setQuery] = useState('')
  const [customers, setCustomers] = useState<Customer[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    const handle = window.setTimeout(() => {
      setLoading(true)
      listCustomers(query)
        .then((rows) => {
          if (!cancelled) {
            setCustomers(rows)
            setError('')
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
    }, 200)

    return () => {
      cancelled = true
      window.clearTimeout(handle)
    }
  }, [query])

  return (
    <>
      <section className="card">
        <div className="row-between">
          <h1>Customers</h1>
          <button type="button" className="primary compact" onClick={onCreate}>
            Add
          </button>
        </div>
        <label className="search-label">
          Search
          <input
            type="search"
            placeholder="Name or phone"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      {loading && <p className="status">Loading customers…</p>}

      {!loading && customers.length === 0 && (
        <section className="card muted-card">
          <p>No customers yet. Tap Add to save the first one.</p>
        </section>
      )}

      <ul className="list">
        {customers.map((customer) => (
          <li key={customer.id}>
            <button
              type="button"
              className="list-item"
              onClick={() => onOpen(customer.id)}
            >
              <span className="list-title">{customer.name}</span>
              <span className="list-meta">{customer.phone}</span>
            </button>
          </li>
        ))}
      </ul>
    </>
  )
}
