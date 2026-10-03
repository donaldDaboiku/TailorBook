import { useState, type FormEvent } from 'react'
import {
  createCustomer,
  firstError,
  updateCustomer,
  type Customer,
  type CustomerInput,
} from '../api'

export default function CustomerFormPage({
  customer,
  onCancel,
  onSaved,
}: {
  customer?: Customer
  onCancel: () => void
  onSaved: (customer: Customer) => void
}) {
  const [name, setName] = useState(customer?.name ?? '')
  const [phone, setPhone] = useState(customer?.phone ?? '')
  const [whatsappPhone, setWhatsappPhone] = useState(
    customer?.whatsapp_phone && customer.whatsapp_phone !== customer.phone
      ? customer.whatsapp_phone
      : '',
  )
  const [email, setEmail] = useState(customer?.email ?? '')
  const [gender, setGender] = useState<'male' | 'female' | ''>(
    customer?.gender ?? '',
  )
  const [address, setAddress] = useState(customer?.address ?? '')
  const [notes, setNotes] = useState(customer?.notes ?? '')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError('')

    const payload: CustomerInput = {
      name,
      phone,
      whatsapp_phone: whatsappPhone,
      email,
      gender,
      address,
      notes,
    }

    try {
      const saved = customer
        ? await updateCustomer(customer.id, payload)
        : await createCustomer(payload)
      onSaved(saved)
    } catch (err) {
      setError(firstError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="card">
      <h1>{customer ? 'Edit customer' : 'New customer'}</h1>
      <p className="lede">Name and phone are enough to start.</p>

      <form className="form" onSubmit={onSubmit}>
        <label>
          Name
          <input
            value={name}
            onChange={(event) => setName(event.target.value)}
            required
            autoComplete="name"
          />
        </label>

        <label>
          Phone
          <input
            type="tel"
            inputMode="tel"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            required
            autoComplete="tel"
          />
        </label>

        <label>
          WhatsApp phone
          <input
            type="tel"
            inputMode="tel"
            value={whatsappPhone}
            onChange={(event) => setWhatsappPhone(event.target.value)}
            placeholder="Same as phone if empty"
          />
        </label>

        <label>
          Email
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
          />
        </label>

        <label>
          Gender
          <select
            value={gender}
            onChange={(event) =>
              setGender(event.target.value as 'male' | 'female' | '')
            }
          >
            <option value="">Not set</option>
            <option value="female">Female</option>
            <option value="male">Male</option>
          </select>
        </label>

        <label>
          Address
          <input
            value={address}
            onChange={(event) => setAddress(event.target.value)}
          />
        </label>

        <label>
          Notes
          <textarea
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            rows={3}
          />
        </label>

        {error && (
          <p className="form-error" role="alert">
            {error}
          </p>
        )}

        <button type="submit" className="primary" disabled={busy}>
          {busy ? 'Saving…' : 'Save customer'}
        </button>
        <button type="button" className="secondary" onClick={onCancel}>
          Cancel
        </button>
      </form>
    </section>
  )
}
