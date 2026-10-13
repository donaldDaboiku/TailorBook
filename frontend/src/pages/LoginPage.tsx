import { useState, type FormEvent } from 'react'
import { firstError } from '../api'
import { useAuth } from '../auth'
import { LanguageSelect, useI18n } from '../i18n'

export default function LoginPage({
  onShowRegister,
  onShowForgot,
}: {
  onShowRegister: () => void
  onShowForgot: () => void
}) {
  const { login, appName } = useAuth()
  const { t } = useI18n()
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
      <h1>{t('login.title')}</h1>
      <p className="lede">{t('login.lede', { shop: appName || 'shop' })}</p>

      <form className="form" onSubmit={onSubmit}>
        <LanguageSelect />
        <label>
          {t('login.email')}
          <input
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </label>

        <label>
          {t('login.password')}
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
          {busy ? t('login.busy') : t('login.submit')}
        </button>
      </form>

      <p className="switch">
        <button type="button" className="link" onClick={onShowForgot}>
          {t('login.forgot')}
        </button>
      </p>

      <p className="switch">
        {t('login.new')}{' '}
        <button type="button" className="link" onClick={onShowRegister}>
          {t('login.create')}
        </button>
      </p>
    </section>
  )
}
