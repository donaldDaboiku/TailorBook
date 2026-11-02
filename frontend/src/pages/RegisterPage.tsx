import { useEffect, useState, type FormEvent } from 'react'
import { fetchPresence, firstError } from '../api'
import { useAuth } from '../auth'
import PrivacyNotice from './PrivacyNotice'

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
  const [stateName, setStateName] = useState('')
  const [stateOptions, setStateOptions] = useState<string[]>([])
  const [inviteCode, setInviteCode] = useState(() => {
    const params = new URLSearchParams(window.location.search)
    return (params.get('invite') ?? '').trim().toUpperCase()
  })
  const [adult, setAdult] = useState(false)
  const [privacy, setPrivacy] = useState(false)
  const [showPrivacy, setShowPrivacy] = useState(false)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    fetchPresence()
      .then((presence) => {
        if (!cancelled) {
          setStateOptions(presence.state_options)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Could not load the state list. Check your connection and try again.')
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

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
        state: stateName,
        measurement_unit: unit,
        currency: 'NGN',
        invite_code: inviteCode.trim().toUpperCase() || undefined,
        adult,
        privacy,
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
          State
          <select
            value={stateName}
            onChange={(event) => setStateName(event.target.value)}
            required
          >
            <option value="">Choose a state</option>
            {stateOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
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

        <label>
          Invite code (optional)
          <input
            value={inviteCode}
            onChange={(event) => setInviteCode(event.target.value.toUpperCase())}
            maxLength={12}
            autoCapitalize="characters"
            placeholder="From the shop or salesperson who invited you"
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

        <button
          type="button"
          className="link"
          onClick={() => setShowPrivacy((open) => !open)}
        >
          {showPrivacy ? 'Hide the privacy notice' : 'Read the privacy notice'}
        </button>
        {showPrivacy && <PrivacyNotice />}

        <label className="choice">
          <input
            type="checkbox"
            checked={adult}
            onChange={(event) => setAdult(event.target.checked)}
            required
          />
          I am 18 or older
        </label>
        <label className="choice">
          <input
            type="checkbox"
            checked={privacy}
            onChange={(event) => setPrivacy(event.target.checked)}
            required
          />
          I agree to the privacy notice, including storage outside Nigeria
        </label>

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
