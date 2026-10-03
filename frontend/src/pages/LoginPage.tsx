import { useState, type FormEvent } from 'react'
import { firstError } from '../api'
import { useAuth } from '../auth'

export default function LoginPage({
  onShowRegister,
}: {
  onShowRegister: () => void
}) {
  const { login, appName } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setBusy(true)

    try {
      await login(email.trim(), password)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h1>Sign in</h1>
      <p className="lede">Use your {appName || 'shop'} account.</p>

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

        <label>
          Password
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
          />
        </label>

        {error && <p className="form-error" role="alert">{error}</p>}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </form>

      <p className="switch">
        New shop?{' '}
        <button type="button" className="link" onClick={onShowRegister}>
          Create an account
        </button>
      </p>
    </section>
  )
}
