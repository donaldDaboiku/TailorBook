import { useState } from 'react'
import { useAuth } from '../auth'

const STEPS = [
  {
    title: 'Welcome to your shop',
    body: 'TailorMate helps you save customers, measurements, jobs, payments, and expenses. You need internet when you save.',
  },
  {
    title: 'The main tabs',
    body: 'Home is for quick actions. Customers holds your client list. Finance shows money and expenses. More is shop settings and your account.',
  },
  {
    title: 'Customers',
    body: 'Add a customer with name and phone. Open them to call, WhatsApp, take measurements, add jobs, and record payments.',
  },
  {
    title: 'Measurements',
    body: 'Open a customer → Take. Pick Female, Male, or Child, enter sizes, then save. Duplicate an old set when the same person returns.',
  },
  {
    title: 'Jobs and payments',
    body: 'Add a job with the agreed amount. Record each payment. Outstanding is always agreed minus paid — check Money on the customer.',
  },
  {
    title: 'WhatsApp and Finance',
    body: 'Send Hello, balance reminder, or outfit ready from a customer. Use Finance for a money summary and to log shop expenses.',
  },
] as const

export default function HowToUseTutorial({
  onDone,
}: {
  onDone: () => void
}) {
  const { appName, user } = useAuth()
  const [step, setStep] = useState(0)
  const current = STEPS[step]
  const last = step === STEPS.length - 1

  function next() {
    if (last) {
      onDone()
      return
    }
    setStep((value) => value + 1)
  }

  return (
    <section className="card">
      <p className="status">
        How to use {appName || 'TailorMate'}
        {user?.name ? ` · ${user.name}` : ''}
      </p>
      <h1>{current.title}</h1>
      <p className="lede">{current.body}</p>

      <p className="status">
        Step {step + 1} of {STEPS.length}
      </p>

      <div className="home-actions">
        <button type="button" className="primary" onClick={next}>
          {last ? 'Start using the shop' : 'Next'}
        </button>
        {!last && (
          <button type="button" className="secondary" onClick={onDone}>
            Skip tutorial
          </button>
        )}
      </div>
    </section>
  )
}
