import { useState, type FormEvent } from 'react'
import {
  deleteAccount,
  firstError,
  updateBusiness,
  updateProfile,
} from '../api'
import { useAuth } from '../auth'

export default function MorePage() {
  const { user, setUser, clearUser, logout, appName } = useAuth()
  const business = user?.business

  const [name, setName] = useState(user?.name ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [shopName, setShopName] = useState(business?.name ?? '')
  const [phone, setPhone] = useState(business?.phone ?? '')
  const [whatsappPhone, setWhatsappPhone] = useState(
    business?.whatsapp_phone && business.whatsapp_phone !== business.phone
      ? business.whatsapp_phone
      : '',
  )
  const [unit, setUnit] = useState<'cm' | 'in'>(
    business?.measurement_unit ?? 'in',
  )
  const [currency, setCurrency] = useState(business?.currency ?? 'NGN')
  const [accountError, setAccountError] = useState('')
  const [shopError, setShopError] = useState('')
  const [accountSaved, setAccountSaved] = useState(false)
  const [shopSaved, setShopSaved] = useState(false)
  const [accountBusy, setAccountBusy] = useState(false)
  const [shopBusy, setShopBusy] = useState(false)
  const [logoutBusy, setLogoutBusy] = useState(false)
  const [deletePassword, setDeletePassword] = useState('')
  const [deleteError, setDeleteError] = useState('')
  const [deleteBusy, setDeleteBusy] = useState(false)

  async function onSaveAccount(event: FormEvent) {
    event.preventDefault()
    setAccountError('')
    setAccountSaved(false)
    setAccountBusy(true)

    try {
      const next = await updateProfile({
        name: name.trim(),
        email: email.trim(),
      })
      setUser(next)
      setAccountSaved(true)
    } catch (err) {
      setAccountError(firstError(err))
    } finally {
      setAccountBusy(false)
    }
  }

  async function onSaveShop(event: FormEvent) {
    event.preventDefault()
    setShopError('')
    setShopSaved(false)
    setShopBusy(true)

    try {
      const next = await updateBusiness({
        name: shopName.trim(),
        phone: phone.trim(),
        whatsapp_phone: whatsappPhone.trim() || undefined,
        country: business?.country ?? 'NG',
        measurement_unit: unit,
        currency: currency.trim().toUpperCase() || 'NGN',
      })
      setUser(next)
      setShopSaved(true)
    } catch (err) {
      setShopError(firstError(err))
    } finally {
      setShopBusy(false)
    }
  }

  async function onLogout() {
    setLogoutBusy(true)
    try {
      await logout()
    } finally {
      setLogoutBusy(false)
    }
  }

  async function onDeleteAccount(event: FormEvent) {
    event.preventDefault()
    setDeleteError('')

    const ok = window.confirm(
      'Delete your account and all shop data forever? This cannot be undone.',
    )

    if (!ok) {
      return
    }

    setDeleteBusy(true)

    try {
      await deleteAccount(deletePassword)
      clearUser()
    } catch (err) {
      setDeleteError(firstError(err))
      setDeleteBusy(false)
    }
  }

  return (
    <>
      <section className="card">
        <h1>More</h1>
        <p className="lede">Shop settings, your account, and sign out.</p>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">Shop settings</h2>
        <form className="form" onSubmit={onSaveShop}>
          <label>
            Shop name
            <input
              value={shopName}
              onChange={(event) => setShopName(event.target.value)}
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
              required
            />
          </label>

          <label>
            WhatsApp (optional)
            <input
              type="tel"
              inputMode="tel"
              value={whatsappPhone}
              onChange={(event) => setWhatsappPhone(event.target.value)}
              placeholder="Same as shop phone if empty"
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
              Centimetres
            </label>
          </fieldset>

          <label>
            Currency
            <input
              value={currency}
              onChange={(event) => setCurrency(event.target.value.toUpperCase())}
              maxLength={3}
              required
            />
          </label>

          {shopError && (
            <p className="form-error" role="alert">
              {shopError}
            </p>
          )}
          {shopSaved && <p className="status">Shop settings saved.</p>}

          <button type="submit" className="primary" disabled={shopBusy}>
            {shopBusy ? 'Saving…' : 'Save shop'}
          </button>
        </form>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">Account</h2>
        <form className="form" onSubmit={onSaveAccount}>
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
            Email
            <input
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="email"
              required
            />
          </label>

          {accountError && (
            <p className="form-error" role="alert">
              {accountError}
            </p>
          )}
          {accountSaved && <p className="status">Account saved.</p>}

          <button type="submit" className="primary" disabled={accountBusy}>
            {accountBusy ? 'Saving…' : 'Save account'}
          </button>
        </form>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">About</h2>
        <p className="status">
          {appName}. On Android Chrome, use the browser menu → Install app for a
          home-screen icon.
        </p>
      </section>

      <section className="card muted-card">
        <button
          type="button"
          className="secondary danger"
          onClick={onLogout}
          disabled={logoutBusy || deleteBusy}
        >
          {logoutBusy ? 'Signing out…' : 'Sign out'}
        </button>
      </section>

      <section className="card muted-card">
        <h2 className="section-title">Delete account</h2>
        <p className="lede">
          This permanently removes your login, shop, customers, measurements,
          jobs, payments, and expenses.
        </p>
        <form className="form" onSubmit={onDeleteAccount}>
          <label>
            Confirm with your password
            <input
              type="password"
              value={deletePassword}
              onChange={(event) => setDeletePassword(event.target.value)}
              autoComplete="current-password"
              required
            />
          </label>

          {deleteError && (
            <p className="form-error" role="alert">
              {deleteError}
            </p>
          )}

          <button
            type="submit"
            className="secondary danger"
            disabled={deleteBusy || !deletePassword}
          >
            {deleteBusy ? 'Deleting…' : 'Delete my account'}
          </button>
        </form>
      </section>
    </>
  )
}
