export type Health = {
  status: string
  app: string
}

export type Business = {
  id: string
  name: string
  phone: string
  whatsapp_phone: string | null
  country: string
  measurement_unit: 'cm' | 'in'
  currency: string
}

export type UserRole = 'tailor' | 'admin'

export type User = {
  id: number
  name: string
  email: string
  role: UserRole
  last_login_at?: string | null
  business?: Business | null
}

export type SubscriptionStatus = 'free' | 'subscribed'
export type SubscriptionAccess = 'free' | 'subscribed' | 'expired' | 'admin'

export type AdminShop = {
  id: number
  owner_name: string
  email: string
  shop_name: string | null
  phone: string | null
  country: string | null
  created_at: string | null
  last_login_at: string | null
  suspended: boolean
  suspended_at: string | null
  subscription_status: SubscriptionStatus
  subscription_access: SubscriptionAccess
  subscribed_until: string | null
}

export type AuthResponse = {
  token: string
  token_type: string
  user: User
}

export type ApiError = {
  message: string
  errors?: Record<string, string[]>
}

export type CustomerFinance = {
  currency: string
  total_agreed: string
  total_paid: string
  outstanding: string
  total_agreed_label: string
  total_paid_label: string
  outstanding_label: string
}

export type Customer = {
  id: string
  name: string
  phone: string
  whatsapp_phone: string
  email: string | null
  gender: 'male' | 'female' | null
  address: string | null
  notes: string | null
  whatsapp_url: string
  finance?: CustomerFinance
  created_at: string | null
  updated_at: string | null
}

export type PaymentMethod = 'cash' | 'bank_transfer' | 'pos' | 'other'

export type CustomerJob = {
  id: string
  customer_id: string
  title: string
  agreed_amount: string
  agreed_amount_label: string
  paid_amount: string
  paid_amount_label: string
  outstanding: string
  outstanding_label: string
  service_date: string
  created_at: string | null
}

export type Payment = {
  id: string
  customer_id: string
  customer_job_id: string | null
  job_title?: string | null
  amount: string
  amount_label: string
  paid_on: string
  method: PaymentMethod
  method_label: string
  reference: string | null
  note: string | null
  created_at: string | null
}

export type CustomerInput = {
  name: string
  phone: string
  whatsapp_phone?: string
  email?: string
  gender?: 'male' | 'female' | ''
  address?: string
  notes?: string
}

export type MeasurementUnit = 'cm' | 'in'

export type MeasurementField = {
  id: string
  key: string
  label: string
  default_label?: string
  enabled?: boolean
  sort_order: number
}

export type MeasurementTemplate = {
  id: string
  slug: string
  name: string
  fields: MeasurementField[]
}

export type MeasurementValue = {
  field_id: string
  key: string
  label: string
  value_cm: string
  value: string
  previous_value?: string
  change?: string
}

export type Measurement = {
  id: string
  customer_id: string
  taken_on: string
  unit: MeasurementUnit
  template: {
    id: string
    slug: string
    name: string
  }
  values: MeasurementValue[]
  created_at: string | null
}

const TOKEN_KEY = 'tailormate_token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

function apiUrl(path: string): string {
  const base = import.meta.env.VITE_API_URL ?? ''
  return `${base}${path}`
}

async function parseJson(response: Response): Promise<unknown> {
  return response.json() as Promise<unknown>
}

export async function fetchHealth(): Promise<Health> {
  const response = await fetch(apiUrl('/api/health'), {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error('unavailable')
  }

  const data = await parseJson(response)

  if (!isHealth(data)) {
    throw new Error('unavailable')
  }

  return data
}

export async function register(payload: {
  name: string
  email: string
  password: string
  password_confirmation: string
  business_name: string
  phone: string
  country: string
  measurement_unit: 'cm' | 'in'
  currency: string
}): Promise<AuthResponse> {
  return authRequest('/api/auth/register', payload)
}

export async function login(payload: {
  email: string
  password: string
}): Promise<AuthResponse> {
  return authRequest('/api/auth/login', payload)
}

export async function fetchMe(): Promise<User> {
  const response = await fetch(apiUrl('/api/auth/me'), {
    headers: authHeaders(),
  })

  if (response.status === 401) {
    setToken(null)
    throw new Error('unauthorized')
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  const data = await parseJson(response)

  if (!isWrappedUser(data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function listAdminShops(q = ''): Promise<AdminShop[]> {
  const query = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
  const data = await apiJson<{ data: AdminShop[] }>(`/api/admin/shops${query}`)

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function suspendAdminShop(userId: number): Promise<AdminShop> {
  const data = await apiJson<{ data: AdminShop; message: string }>(
    `/api/admin/shops/${userId}/suspend`,
    { method: 'POST' },
  )

  return data.data
}

export async function unsuspendAdminShop(userId: number): Promise<AdminShop> {
  const data = await apiJson<{ data: AdminShop; message: string }>(
    `/api/admin/shops/${userId}/unsuspend`,
    { method: 'POST' },
  )

  return data.data
}

export async function updateAdminShopSubscription(
  userId: number,
  payload: {
    subscription_status: SubscriptionStatus
    subscribed_until?: string | null
  },
): Promise<AdminShop> {
  const body: Record<string, string> = {
    subscription_status: payload.subscription_status,
  }

  if (
    payload.subscription_status === 'subscribed' &&
    payload.subscribed_until
  ) {
    body.subscribed_until = payload.subscribed_until
  }

  const data = await apiJson<{ data: AdminShop; message: string }>(
    `/api/admin/shops/${userId}/subscription`,
    {
      method: 'PUT',
      body: JSON.stringify(body),
    },
  )

  return data.data
}

export async function updateProfile(payload: {
  name: string
  email: string
}): Promise<User> {
  const data = await apiJson<{ data: User }>('/api/auth/profile', {
    method: 'PUT',
    body: JSON.stringify(payload),
  })

  if (!isWrappedUser(data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function updateBusiness(payload: {
  name: string
  phone: string
  whatsapp_phone?: string
  country: string
  measurement_unit: 'cm' | 'in'
  currency: string
}): Promise<User> {
  const body: Record<string, string> = {
    name: payload.name,
    phone: payload.phone,
    country: payload.country,
    measurement_unit: payload.measurement_unit,
    currency: payload.currency,
  }

  if (payload.whatsapp_phone?.trim()) {
    body.whatsapp_phone = payload.whatsapp_phone.trim()
  }

  const data = await apiJson<{ data: User }>('/api/business', {
    method: 'PUT',
    body: JSON.stringify(body),
  })

  if (!isWrappedUser(data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function deleteAccount(password: string): Promise<void> {
  await apiJson<{ message: string }>('/api/auth/account', {
    method: 'DELETE',
    body: JSON.stringify({ password }),
  })

  setToken(null)
}

export async function logout(): Promise<void> {
  const token = getToken()

  if (!token) {
    return
  }

  await fetch(apiUrl('/api/auth/logout'), {
    method: 'POST',
    headers: authHeaders(),
  })

  setToken(null)
}

export async function listCustomers(q = ''): Promise<Customer[]> {
  const query = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
  const data = await apiJson<{ data: Customer[] }>(`/api/customers${query}`)

  if (!Array.isArray(data.data) || !data.data.every(isCustomer)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function getCustomer(id: string): Promise<Customer> {
  const data = await apiJson<{ data: Customer }>(`/api/customers/${id}`)

  if (!isCustomer(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export type WhatsAppTemplateKey = 'hello' | 'balance_reminder' | 'outfit_ready'

export type WhatsAppTemplate = {
  key: WhatsAppTemplateKey
  label: string
  body: string
  url: string
}

export type WhatsAppTemplates = {
  phone: string
  whatsapp_number: string
  whatsapp_url: string
  templates: WhatsAppTemplate[]
}

export async function fetchWhatsAppTemplates(
  customerId: string,
): Promise<WhatsAppTemplates> {
  const data = await apiJson<{ data: WhatsAppTemplates }>(
    `/api/customers/${customerId}/whatsapp-templates`,
  )

  if (!data.data?.whatsapp_number || !Array.isArray(data.data.templates)) {
    throw new Error('bad response')
  }

  return data.data
}

export function whatsappUrlWithText(number: string, text: string): string {
  const base = `https://wa.me/${number}`
  const trimmed = text.trim()

  return trimmed === ''
    ? base
    : `${base}?text=${encodeURIComponent(trimmed)}`
}

export async function createCustomer(payload: CustomerInput): Promise<Customer> {
  const data = await apiJson<{ data: Customer }>('/api/customers', {
    method: 'POST',
    body: JSON.stringify(cleanCustomerPayload(payload)),
  })

  if (!isCustomer(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function updateCustomer(
  id: string,
  payload: CustomerInput,
): Promise<Customer> {
  const data = await apiJson<{ data: Customer }>(`/api/customers/${id}`, {
    method: 'PUT',
    body: JSON.stringify(cleanCustomerPayload(payload)),
  })

  if (!isCustomer(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function archiveCustomer(id: string): Promise<void> {
  await apiJson<{ message: string }>(`/api/customers/${id}`, {
    method: 'DELETE',
  })
}

export async function listMeasurementTemplates(
  enabledOnly = false,
): Promise<MeasurementTemplate[]> {
  const query = enabledOnly ? '?enabled_only=1' : ''
  const data = await apiJson<{ data: MeasurementTemplate[] }>(
    `/api/measurement-templates${query}`,
  )

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function updateMeasurementTemplateFields(
  slug: string,
  fields: Array<{ key: string; label: string; enabled: boolean }>,
): Promise<MeasurementTemplate> {
  const data = await apiJson<{ data: MeasurementTemplate }>(
    `/api/measurement-templates/${slug}/fields`,
    {
      method: 'PUT',
      body: JSON.stringify({ fields }),
    },
  )

  if (!data.data?.slug) {
    throw new Error('bad response')
  }

  return data.data
}

export async function listMeasurements(
  customerId: string,
  unit?: MeasurementUnit,
): Promise<Measurement[]> {
  const query = unit ? `?unit=${unit}` : ''
  const data = await apiJson<{ data: Measurement[] }>(
    `/api/customers/${customerId}/measurements${query}`,
  )

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function getMeasurement(
  customerId: string,
  measurementId: string,
  unit?: MeasurementUnit,
): Promise<Measurement> {
  const query = unit ? `?unit=${unit}` : ''
  const data = await apiJson<{ data: Measurement }>(
    `/api/customers/${customerId}/measurements/${measurementId}${query}`,
  )

  if (!isMeasurement(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function createMeasurement(
  customerId: string,
  payload: {
    template_slug: string
    taken_on: string
    unit: MeasurementUnit
    values: Record<string, number>
  },
): Promise<Measurement> {
  const data = await apiJson<{ data: Measurement }>(
    `/api/customers/${customerId}/measurements`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  if (!isMeasurement(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function listJobs(customerId: string): Promise<CustomerJob[]> {
  const data = await apiJson<{ data: CustomerJob[] }>(
    `/api/customers/${customerId}/jobs`,
  )

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function createJob(
  customerId: string,
  payload: {
    title: string
    agreed_amount: string
    service_date: string
  },
): Promise<CustomerJob> {
  const data = await apiJson<{ data: CustomerJob }>(
    `/api/customers/${customerId}/jobs`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return data.data
}

export async function archiveJob(
  customerId: string,
  jobId: string,
): Promise<void> {
  await apiJson<{ message: string }>(
    `/api/customers/${customerId}/jobs/${jobId}`,
    { method: 'DELETE' },
  )
}

export async function listPayments(customerId: string): Promise<Payment[]> {
  const data = await apiJson<{ data: Payment[] }>(
    `/api/customers/${customerId}/payments`,
  )

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function createPayment(
  customerId: string,
  payload: {
    amount: string
    paid_on: string
    method: PaymentMethod
    customer_job_id?: string
    reference?: string
    note?: string
  },
): Promise<Payment> {
  const body: Record<string, string> = {
    amount: payload.amount,
    paid_on: payload.paid_on,
    method: payload.method,
  }

  if (payload.customer_job_id) {
    body.customer_job_id = payload.customer_job_id
  }

  if (payload.reference?.trim()) {
    body.reference = payload.reference.trim()
  }

  if (payload.note?.trim()) {
    body.note = payload.note.trim()
  }

  const data = await apiJson<{ data: Payment }>(
    `/api/customers/${customerId}/payments`,
    {
      method: 'POST',
      body: JSON.stringify(body),
    },
  )

  return data.data
}

export async function archivePayment(
  customerId: string,
  paymentId: string,
): Promise<void> {
  await apiJson<{ message: string }>(
    `/api/customers/${customerId}/payments/${paymentId}`,
    { method: 'DELETE' },
  )
}

export type ExpenseCategory = {
  id: string
  slug: string
  name: string
  sort_order: number
}

export type Expense = {
  id: string
  amount: string
  amount_label: string
  spent_on: string
  description: string | null
  method: PaymentMethod
  method_label: string
  note: string | null
  category: {
    id: string
    slug: string
    name: string
  }
  created_at: string | null
}

export async function listExpenseCategories(): Promise<ExpenseCategory[]> {
  const data = await apiJson<{ data: ExpenseCategory[] }>(
    '/api/expense-categories',
  )

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function listExpenses(month?: string): Promise<Expense[]> {
  const query = month ? `?month=${encodeURIComponent(month)}` : ''
  const data = await apiJson<{ data: Expense[] }>(`/api/expenses${query}`)

  if (!Array.isArray(data.data)) {
    throw new Error('bad response')
  }

  return data.data
}

export async function createExpense(payload: {
  amount: string
  spent_on: string
  expense_category_id: string
  method: PaymentMethod
  description?: string
  note?: string
}): Promise<Expense> {
  const body: Record<string, string> = {
    amount: payload.amount,
    spent_on: payload.spent_on,
    expense_category_id: payload.expense_category_id,
    method: payload.method,
  }

  if (payload.description?.trim()) {
    body.description = payload.description.trim()
  }

  if (payload.note?.trim()) {
    body.note = payload.note.trim()
  }

  const data = await apiJson<{ data: Expense }>('/api/expenses', {
    method: 'POST',
    body: JSON.stringify(body),
  })

  return data.data
}

export async function archiveExpense(id: string): Promise<void> {
  await apiJson<{ message: string }>(`/api/expenses/${id}`, {
    method: 'DELETE',
  })
}

export type FinancePeriod = {
  label: string
  from: string
  to: string
  income: string
  income_label: string
  expenses: string
  expenses_label: string
  net: string
  net_label: string
}

export type FinanceSummary = {
  currency: string
  outstanding: string
  outstanding_label: string
  periods: {
    today: FinancePeriod
    week: FinancePeriod
    month: FinancePeriod
  }
}

export async function fetchFinanceSummary(): Promise<FinanceSummary> {
  const data = await apiJson<{ data: FinanceSummary }>('/api/finance/summary')

  if (!data.data?.periods?.today) {
    throw new Error('bad response')
  }

  return data.data
}

export function currentMonth(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')

  return `${now.getFullYear()}-${month}`
}

async function authRequest(
  path: string,
  body: Record<string, string>,
): Promise<AuthResponse> {
  const response = await fetch(apiUrl(path), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  })

  if (!response.ok) {
    throw await toApiError(response)
  }

  const data = await parseJson(response)

  if (!isAuthResponse(data)) {
    throw new Error('bad response')
  }

  setToken(data.token)
  return data
}

function authHeaders(json = false): HeadersInit {
  const token = getToken()

  return {
    Accept: 'application/json',
    ...(json ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  }
}

async function apiJson<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(apiUrl(path), {
    ...init,
    headers: {
      ...authHeaders(Boolean(init.body)),
      ...(init.headers ?? {}),
    },
  })

  if (response.status === 401) {
    setToken(null)
    throw new Error('unauthorized')
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  return (await parseJson(response)) as T
}

function cleanCustomerPayload(payload: CustomerInput): Record<string, string> {
  const body: Record<string, string> = {
    name: payload.name.trim(),
    phone: payload.phone.trim(),
  }

  if (payload.whatsapp_phone?.trim()) {
    body.whatsapp_phone = payload.whatsapp_phone.trim()
  }

  if (payload.email?.trim()) {
    body.email = payload.email.trim()
  }

  if (payload.gender) {
    body.gender = payload.gender
  }

  if (payload.address?.trim()) {
    body.address = payload.address.trim()
  }

  if (payload.notes?.trim()) {
    body.notes = payload.notes.trim()
  }

  return body
}

async function toApiError(response: Response): Promise<ApiError> {
  try {
    const data = await parseJson(response)

    if (typeof data === 'object' && data !== null && 'message' in data) {
      const message =
        typeof data.message === 'string' ? data.message : 'Something went wrong.'
      const errors =
        'errors' in data && typeof data.errors === 'object' && data.errors !== null
          ? (data.errors as Record<string, string[]>)
          : undefined

      return { message, errors }
    }
  } catch {
    // fall through
  }

  return { message: 'Something went wrong.' }
}

function isHealth(data: unknown): data is Health {
  return (
    typeof data === 'object' &&
    data !== null &&
    'status' in data &&
    'app' in data &&
    data.status === 'ok' &&
    typeof data.app === 'string' &&
    data.app.length > 0
  )
}

function isAuthResponse(data: unknown): data is AuthResponse {
  return (
    typeof data === 'object' &&
    data !== null &&
    'token' in data &&
    typeof data.token === 'string' &&
    'user' in data &&
    isUser(data.user)
  )
}

function isWrappedUser(data: unknown): data is { data: User } {
  return (
    typeof data === 'object' &&
    data !== null &&
    'data' in data &&
    isUser(data.data)
  )
}

function isUser(data: unknown): data is User {
  return (
    typeof data === 'object' &&
    data !== null &&
    'id' in data &&
    'name' in data &&
    'email' in data &&
    typeof data.name === 'string' &&
    typeof data.email === 'string'
  )
}

function isCustomer(data: unknown): data is Customer {
  return (
    typeof data === 'object' &&
    data !== null &&
    'id' in data &&
    'name' in data &&
    'phone' in data &&
    'whatsapp_url' in data &&
    typeof data.id === 'string' &&
    typeof data.name === 'string' &&
    typeof data.phone === 'string' &&
    typeof data.whatsapp_url === 'string'
  )
}

function isMeasurement(data: unknown): data is Measurement {
  return (
    typeof data === 'object' &&
    data !== null &&
    'id' in data &&
    'customer_id' in data &&
    'taken_on' in data &&
    'values' in data &&
    typeof data.id === 'string' &&
    Array.isArray(data.values)
  )
}

export function todayDate(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

export function stepForUnit(unit: MeasurementUnit): number {
  return unit === 'in' ? 0.25 : 0.5
}

export function firstError(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'errors' in error) {
    const errors = error.errors as Record<string, string[]> | undefined
    if (errors) {
      const first = Object.values(errors)[0]?.[0]
      if (first) {
        return first
      }
    }
  }

  if (typeof error === 'object' && error !== null && 'message' in error) {
    const message = error.message
    if (typeof message === 'string' && message.length > 0) {
      return message
    }
  }

  return 'Something went wrong.'
}
