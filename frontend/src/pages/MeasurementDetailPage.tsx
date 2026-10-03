import { useEffect, useState } from 'react'
import {
  firstError,
  getMeasurement,
  type Customer,
  type Measurement,
} from '../api'
import { useAuth } from '../auth'

export default function MeasurementDetailPage({
  customer,
  measurementId,
  onBack,
  onDuplicate,
}: {
  customer: Customer
  measurementId: string
  onBack: () => void
  onDuplicate: (measurement: Measurement) => void
}) {
  const { user } = useAuth()
  const unit = user?.business?.measurement_unit ?? 'in'
  const [measurement, setMeasurement] = useState<Measurement | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false

    getMeasurement(customer.id, measurementId, unit)
      .then((row) => {
        if (!cancelled) {
          setMeasurement(row)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })

    return () => {
      cancelled = true
    }
  }, [customer.id, measurementId, unit])

  if (error && !measurement) {
    return (
      <section className="card">
        <p className="form-error" role="alert">
          {error}
        </p>
        <button type="button" className="secondary" onClick={onBack}>
          Back
        </button>
      </section>
    )
  }

  if (!measurement) {
    return <p className="status">Loading measurements…</p>
  }

  const unitLabel = measurement.unit === 'in' ? '"' : ' cm'

  return (
    <>
      <section className="card">
        <button type="button" className="link back-link" onClick={onBack}>
          Back to {customer.name}
        </button>
        <h1>{measurement.template.name}</h1>
        <p className="lede">Taken {measurement.taken_on}</p>
        <button
          type="button"
          className="primary"
          onClick={() => onDuplicate(measurement)}
        >
          Use as starting point
        </button>
      </section>

      <section className="card muted-card">
        <ul className="measure-list">
          {measurement.values.map((value) => (
            <li key={value.field_id}>
              <div>
                <strong>{value.label}</strong>
                <span>
                  {value.value}
                  {unitLabel}
                </span>
              </div>
              {value.previous_value && (
                <p className="measure-change">
                  Was {value.previous_value}
                  {unitLabel}
                  {value.change ? ` · ${value.change}${unitLabel}` : ''}
                </p>
              )}
            </li>
          ))}
        </ul>
      </section>
    </>
  )
}
