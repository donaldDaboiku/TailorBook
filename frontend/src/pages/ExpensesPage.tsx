import { useEffect, useMemo, useState } from 'react'
import {
  archiveExpense,
  currentMonth,
  firstError,
  listExpenses,
  type Expense,
} from '../api'

export default function ExpensesPage({
  onCreate,
}: {
  onCreate: () => void
}) {
  const [month, setMonth] = useState(currentMonth())
  const [expenses, setExpenses] = useState<Expense[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  async function reload(nextMonth = month) {
    setLoading(true)
    try {
      const rows = await listExpenses(nextMonth)
      setExpenses(rows)
      setError('')
    } catch (err) {
      setError(firstError(err))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void reload(month)
  }, [month])

  const groups = useMemo(() => {
    const map = new Map<string, Expense[]>()

    for (const expense of expenses) {
      const list = map.get(expense.spent_on) ?? []
      list.push(expense)
      map.set(expense.spent_on, list)
    }

    return [...map.entries()]
  }, [expenses])

  async function onArchive(expense: Expense) {
    if (!window.confirm(`Archive expense ${expense.amount_label}?`)) {
      return
    }

    try {
      await archiveExpense(expense.id)
      await reload()
    } catch (err) {
      setError(firstError(err))
    }
  }

  return (
    <>
      <section className="card">
        <div className="row-between">
          <h1>Expenses</h1>
          <button type="button" className="primary compact" onClick={onCreate}>
            Add
          </button>
        </div>
        <label className="search-label">
          Month
          <input
            type="month"
            value={month}
            onChange={(event) => setMonth(event.target.value)}
          />
        </label>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      {loading && <p className="status">Loading expenses…</p>}

      {!loading && expenses.length === 0 && (
        <section className="card muted-card">
          <p>No expenses this month. Tap Add to record one.</p>
        </section>
      )}

      {groups.map(([date, rows]) => (
        <section key={date} className="card muted-card">
          <h2 className="section-title">{date}</h2>
          <ul className="list compact-list">
            {rows.map((expense) => (
              <li key={expense.id} className="stack-item">
                <div className="list-item static">
                  <span className="list-title">{expense.amount_label}</span>
                  <span className="list-meta">
                    {expense.category.name}
                    {expense.description ? ` · ${expense.description}` : ''}
                    {` · ${expense.method_label}`}
                  </span>
                </div>
                <button
                  type="button"
                  className="secondary danger"
                  onClick={() => onArchive(expense)}
                >
                  Archive
                </button>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </>
  )
}
