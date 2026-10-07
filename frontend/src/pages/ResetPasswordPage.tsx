import { useState, type FormEvent } from 'react'
import { firstError, resetPassword } from '../api'

export default function ResetPasswordPage({
  email,
  token,
  onDone,
}: {
  email: string
  token: string
  onDone: () => void
}) {
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setMessage('')
    setBusy(true)

    try {
      const next = await resetPassword({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      })
      setMessage(next)
      window.setTimeout(onDone, 1200)
    } catch (err) {
      setError(firstError(err))
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h1>Set a new password</h1>
      <p className="lede">Choose a new password for {email}.</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          New password
          <input
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            minLength={8}
          />
        </label>

        <label>
          Confirm password
          <input
            type="password"
            autoComplete="new-password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            required
            minLength={8}
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}
        {message && <p className="status">{message}</p>}

        <button type="submit" className="primary" disabled={busy || Boolean(message)}>
          {busy ? 'Saving…' : 'Update password'}
        </button>
      </form>
    </section>
  )
}
