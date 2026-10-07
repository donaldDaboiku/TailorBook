import { useState, type FormEvent } from 'react'
import { firstError, forgotPassword } from '../api'
import { useAuth } from '../auth'

export default function ForgotPasswordPage({
  onShowLogin,
}: {
  onShowLogin: () => void
}) {
  const { appName } = useAuth()
  const [email, setEmail] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setMessage('')
    setBusy(true)

    try {
      const next = await forgotPassword(email.trim())
      setMessage(next)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h1>Forgot password</h1>
      <p className="lede">
        Enter the email for your {appName || 'shop'} account. We will send a
        reset link if it is registered.
      </p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          Email
          <input
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        {message && <p className="status">{message}</p>}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Sending…' : 'Send reset link'}
        </button>
      </form>

      <p className="switch">
        Remembered it?{' '}
        <button type="button" className="link" onClick={onShowLogin}>
          Sign in
        </button>
      </p>
    </section>
  )
}
