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

export type User = {
  id: number
  name: string
  email: string
  business?: Business | null
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

function authHeaders(): HeadersInit {
  const token = getToken()

  return {
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  }
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
