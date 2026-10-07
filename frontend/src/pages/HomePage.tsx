import { useAuth } from '../auth'

function timeGreeting(now = new Date()): string {
  const hour = now.getHours()
  if (hour < 12) {
    return 'Good morning'
  }
  if (hour < 17) {
    return 'Good afternoon'
  }
  return 'Good evening'
}

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
      <h1>
        {timeGreeting()}, {user?.name}
      </h1>
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
