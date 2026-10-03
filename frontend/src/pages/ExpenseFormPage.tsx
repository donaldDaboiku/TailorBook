import { useEffect, useState, type FormEvent } from 'react'
import {
  createExpense,
  firstError,
  listExpenseCategories,
  todayDate,
  type ExpenseCategory,
  type PaymentMethod,
} from '../api'

export default function ExpenseFormPage({
  onCancel,
  onSaved,
}: {
  onCancel: () => void
  onSaved: () => void
}) {
  const [categories, setCategories] = useState<ExpenseCategory[]>([])
  const [amount, setAmount] = useState('')
  const [spentOn, setSpentOn] = useState(todayDate())
  const [categoryId, setCategoryId] = useState('')
  const [method, setMethod] = useState<PaymentMethod>('cash')
  const [description, setDescription] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    listExpenseCategories()
      .then((rows) => {
        if (cancelled) {
          return
        }

        setCategories(rows)
        if (rows[0]) {
          setCategoryId(rows[0].id)
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
      await createExpense({
        amount,
        spent_on: spentOn,
        expense_category_id: categoryId,
        method,
        description,
        note,
      })
      onSaved()
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <p className="status">Loading expense form…</p>
  }

  return (
    <section className="card">
      <button type="button" className="link back-link" onClick={onCancel}>
        Back to expenses
      </button>
      <h1>Add expense</h1>
      <p className="lede">Quick record for shop spending.</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          Amount (₦)
          <input
            type="number"
            inputMode="decimal"
            min="1"
            step="0.01"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            required
          />
        </label>

        <label>
          Category
          <select
            value={categoryId}
            onChange={(event) => setCategoryId(event.target.value)}
            required
          >
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </select>
        </label>

        <label>
          Method
          <select
            value={method}
            onChange={(event) => setMethod(event.target.value as PaymentMethod)}
          >
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="pos">POS</option>
            <option value="other">Other</option>
          </select>
        </label>

        <label>
          Date
          <input
            type="date"
            value={spentOn}
            onChange={(event) => setSpentOn(event.target.value)}
            required
          />
        </label>

        <label>
          Description
          <input
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            placeholder="Ankara, fuel…"
          />
        </label>

        <label>
          Note
          <textarea
            value={note}
            onChange={(event) => setNote(event.target.value)}
            rows={2}
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Saving…' : 'Save expense'}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          Cancel
        </button>
      </form>
    </section>
  )
}
