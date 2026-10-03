import { useEffect, useMemo, useState } from 'react'
import {
  archiveExpense,
  currentMonth,
  fetchFinanceSummary,
  firstError,
  listExpenses,
  type Expense,
  type FinancePeriod,
  type FinanceSummary,
} from '../api'

export default function ExpensesPage({
  onCreate,
}: {
  onCreate: () => void
}) {
  const [month, setMonth] = useState(currentMonth())
  const [expenses, setExpenses] = useState<Expense[]>([])
  const [summary, setSummary] = useState<FinanceSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  async function reload(nextMonth = month) {
    setLoading(true)
    try {
      const [rows, finance] = await Promise.all([
        listExpenses(nextMonth),
        fetchFinanceSummary(),
      ])
      setExpenses(rows)
      setSummary(finance)
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

  const periods: FinancePeriod[] = summary
    ? [summary.periods.today, summary.periods.week, summary.periods.month]
    : []

  return (
    <>
      <section className="card">
        <div className="row-between">
          <h1>Finance</h1>
          <button type="button" className="primary compact" onClick={onCreate}>
            Add expense
          </button>
        </div>
        <p className="lede">Money in, money out, and what customers still owe.</p>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}

      {loading && !summary && <p className="status">Loading finance…</p>}

      {summary && (
        <>
          <section className="card muted-card">
            <h2 className="section-title">Still owed</h2>
            <p className="outstanding-total">{summary.outstanding_label}</p>
          </section>

          {periods.map((period) => (
            <section key={period.label} className="card muted-card">
              <h2 className="section-title">{period.label}</h2>
              <dl className="finance-grid period-grid">
                <div>
                  <dt>Income</dt>
                  <dd>{period.income_label}</dd>
                </div>
                <div>
                  <dt>Expenses</dt>
                  <dd>{period.expenses_label}</dd>
                </div>
                <div>
                  <dt>Net</dt>
                  <dd className={period.net.startsWith('-') ? 'outstanding' : undefined}>
                    {period.net_label}
                  </dd>
                </div>
              </dl>
            </section>
          ))}
        </>
      )}

      <section className="card">
        <h2 className="section-title">Expenses</h2>
        <label className="search-label">
          Month
          <input
            type="month"
            value={month}
            onChange={(event) => setMonth(event.target.value)}
          />
        </label>
      </section>

      {loading && summary && <p className="status">Loading expenses…</p>}

      {!loading && expenses.length === 0 && (
        <section className="card muted-card">
          <p>No expenses this month. Tap Add expense to record one.</p>
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
