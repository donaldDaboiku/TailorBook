export default function LandingPage({
  onCreate,
  onSignIn,
}: {
  onCreate: () => void
  onSignIn: () => void
}) {
  return (
    <>
      <section className="card landing-hero">
        <p className="status">For fashion designers and tailors</p>
        <h1>Your shop. On your phone.</h1>
        <p className="lede">
          Save customers, measurements, jobs, and payments. See what they still
          owe. Message them on WhatsApp.
        </p>
        <div className="home-actions">
          <button type="button" className="primary" onClick={onCreate}>
            Create an account
          </button>
          <button type="button" className="secondary" onClick={onSignIn}>
            Sign in
          </button>
        </div>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">What you can do</h2>
        <ul className="plain-list">
          <li>Keep each customer’s phone, notes, and measurements</li>
          <li>Add jobs and record payments — outstanding updates itself</li>
          <li>Send a hello, balance reminder, or outfit-ready WhatsApp</li>
          <li>Log shop expenses and see a money summary</li>
        </ul>
        <p className="status">Start free. Add it to your home screen from the browser.</p>
      </section>
    </>
  )
}
