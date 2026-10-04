import { useAuth } from '../auth'

export default function HomePage({
  onAddCustomer,
  onAddExpense,
}: {
  onAddCustomer: () => void
  onAddExpense: () => void
}) {
  const { user } = useAuth()
  const shop = user?.business?.name ?? 'your shop'

  return (
    <section className="card">
      <h1>Good day, {user?.name}</h1>
      <p className="lede">{shop} is ready.</p>
      <div className="home-actions">
        <button type="button" className="primary" onClick={onAddCustomer}>
          New customer
        </button>
        <button type="button" className="secondary" onClick={onAddExpense}>
          Add expense
        </button>
      </div>
    </section>
  )
}
