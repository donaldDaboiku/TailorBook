import { useEffect, useState } from 'react'
import { firstError, listCustomers, type Customer } from '../api'

export default function PickCustomerPage({
  purpose,
  onCancel,
  onPick,
  onCreate,
}: {
  purpose: 'job' | 'payment'
  onCancel: () => void
  onPick: (customer: Customer) => void
  onCreate: () => void
}) {
  const [customers, setCustomers] = useState<Customer[]>([])
  const [q, setQ] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    listCustomers()
      .then((rows) => {
        if (!cancelled) {
          setCustomers(rows)
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

  const query = q.trim().toLowerCase()
  const shown = query
    ? customers.filter(
        (customer) =>
          customer.name.toLowerCase().includes(query) ||
          customer.phone.includes(query),
      )
    : customers

  return (
    <section className="card">
      <button type="button" className="link" onClick={onCancel}>
        Back
      </button>
      <h1>{purpose === 'job' ? 'New job' : 'Record payment'}</h1>
      <p className="lede">Choose the client this is for.</p>

      <label className="search-label">
        Search
        <input
          value={q}
          onChange={(event) => setQ(event.target.value)}
          placeholder="Name or phone"
        />
      </label>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}
      {loading && <p className="status">Loading clients…</p>}

      {!loading && shown.length === 0 && (
        <p className="status">No clients yet.</p>
      )}

      <ul className="admin-list">
        {shown.map((customer) => (
          <li key={customer.id}>
            <button
              type="button"
              className="admin-list-row"
              onClick={() => onPick(customer)}
            >
              <span className="admin-list-main">
                <strong>{customer.name}</strong>
                <span className="status">{customer.phone}</span>
              </span>
            </button>
          </li>
        ))}
      </ul>

      <button type="button" className="secondary" onClick={onCreate}>
        New customer
      </button>
    </section>
  )
}
