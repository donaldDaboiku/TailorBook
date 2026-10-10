import { useEffect, useState } from 'react'
import { firstError, listShopJobs, type ShopJob } from '../api'

export default function JobsPage({
  onOpen,
  onBack,
}: {
  onOpen: (job: ShopJob) => void
  onBack: () => void
}) {
  const [upcoming, setUpcoming] = useState<ShopJob[]>([])
  const [overdue, setOverdue] = useState<ShopJob[]>([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    listShopJobs()
      .then((next) => {
        if (cancelled) {
          return
        }
        setUpcoming(next.upcoming)
        setOverdue(next.overdue)
      })
      .catch((err) => {
        if (!cancelled) {
          setError(firstError(err))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <>
      <section className="card">
        <button type="button" className="link" onClick={onBack}>
          Back
        </button>
        <h1>Jobs</h1>
        <p className="lede">Delivery dates across your shop.</p>
      </section>

      {error && (
        <p className="form-error" role="alert">
          {error}
        </p>
      )}
      {loading && <p className="status">Loading jobs…</p>}

      {!loading && (
        <JobGroup title="Due and upcoming" jobs={upcoming} onOpen={onOpen} empty="No jobs scheduled." />
      )}
      {!loading && overdue.length > 0 && (
        <JobGroup title="Past due" jobs={overdue} onOpen={onOpen} empty="" />
      )}
    </>
  )
}

function JobGroup({
  title,
  jobs,
  empty,
  onOpen,
}: {
  title: string
  jobs: ShopJob[]
  empty: string
  onOpen: (job: ShopJob) => void
}) {
  return (
    <section className="card muted-card">
      <h2 className="section-title">{title}</h2>
      {jobs.length === 0 ? (
        <p className="status">{empty}</p>
      ) : (
        <ul className="admin-list">
          {jobs.map((job) => (
            <li key={job.id}>
              <button
                type="button"
                className="admin-list-row"
                onClick={() => onOpen(job)}
              >
                <span className="admin-list-main">
                  <strong>{job.title}</strong>
                  <span className="status">
                    {job.customer_name ?? 'Customer'} · {job.service_date}
                  </span>
                </span>
                <span className="status">{job.outstanding_label}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
