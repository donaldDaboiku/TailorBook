import { useState, type FormEvent } from 'react'
import { firstError } from '../api'
import { useAuth } from '../auth'

export default function RegisterPage({
  onShowLogin,
}: {
  onShowLogin: () => void
}) {
  const { register } = useAuth()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [businessName, setBusinessName] = useState('')
  const [phone, setPhone] = useState('')
  const [unit, setUnit] = useState<'in' | 'cm'>('in')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    setBusy(true)

    try {
      await register({
        name: name.trim(),
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
        business_name: businessName.trim(),
        phone: phone.trim(),
        country: 'NG',
        measurement_unit: unit,
        currency: 'NGN',
      })
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h1>Open your shop</h1>
      <p className="lede">One short form. Then you can add customers.</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          Your name
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            autoComplete="name"
            required
          />
        </label>

        <label>
          Shop name
          <input
            value={businessName}
            onChange={(event) => setBusinessName(event.target.value)}
            required
          />
        </label>

        <label>
          Shop phone
          <input
            type="tel"
            inputMode="tel"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            autoComplete="tel"
            required
          />
        </label>

        <label>
          Email
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
            required
          />
        </label>

        <label>
          Password
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="new-password"
            required
            minLength={8}
          />
        </label>

        <label>
          Confirm password
          <input
            type="password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            autoComplete="new-password"
            required
            minLength={8}
          />
        </label>

        <fieldset className="unit-field">
          <legend>Measurement unit</legend>
          <label className="choice">
            <input
              type="radio"
              name="unit"
              checked={unit === 'in'}
              onChange={() => setUnit('in')}
            />
            Inches
          </label>
          <label className="choice">
            <input
              type="radio"
              name="unit"
              checked={unit === 'cm'}
              onChange={() => setUnit('cm')}
            />
            Centimeters
          </label>
        </fieldset>

        {error && <p className="form-error" role="alert">{error}</p>}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Creating shop…' : 'Create shop'}
        </button>
      </form>

      <p className="switch">
        Already have an account?{' '}
        <button type="button" className="link" onClick={onShowLogin}>
          Sign in
        </button>
      </p>
    </section>
  )
}
