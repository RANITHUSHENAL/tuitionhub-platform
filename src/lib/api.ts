/**
 * TuitionHub API client (contract v0.6 §1.2–§1.4).
 *
 * - Sanctum SPA cookie sessions: every request is sent with credentials, and bearer tokens are
 *   never used.
 * - CSRF: before a state-changing request (POST, PATCH, DELETE) the XSRF-TOKEN cookie is fetched
 *   from /sanctum/csrf-cookie if it is missing, and its value is sent as X-XSRF-TOKEN.
 * - A 419 (CSRF token mismatch) refreshes the cookie and retries the request exactly once.
 *
 * The SPA and the API must both be addressed as `localhost` (not 127.0.0.1), or the browser keeps
 * their cookies apart and the session never sticks.
 */

export const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000').replace(/\/+$/, '');

const XSRF_COOKIE = 'XSRF-TOKEN';

type Method = 'GET' | 'POST' | 'PATCH' | 'DELETE';

/** An error response from the API, or `status: 0` when the API could not be reached. */
export class ApiError extends Error {
  readonly status: number;
  /** Stable machine-readable code for business-rule errors (contract §1.3), e.g. `not_eligible`. */
  readonly reason: string | null;
  /** Laravel's `errors` object on a 422: field → messages. */
  readonly fieldErrors: Record<string, string[]>;

  constructor(status: number, message: string, reason: string | null = null, fieldErrors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.reason = reason;
    this.fieldErrors = fieldErrors;
  }

  fieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0];
  }
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`;

  for (const part of document.cookie.split(';')) {
    const cookie = part.trim();

    if (cookie.startsWith(prefix)) {
      return decodeURIComponent(cookie.slice(prefix.length));
    }
  }

  return null;
}

async function send(method: Method, path: string, body?: unknown): Promise<Response> {
  // Accept alone makes Laravel answer with JSON. No other custom header on GET, so reads stay
  // CORS "simple" requests without a preflight.
  const headers: Record<string, string> = { Accept: 'application/json' };

  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const token = method === 'GET' ? null : readCookie(XSRF_COOKIE);
  if (token) headers['X-XSRF-TOKEN'] = token;

  try {
    return await fetch(`${API_BASE_URL}${path}`, {
      method,
      credentials: 'include',
      headers,
      body: body === undefined ? undefined : JSON.stringify(body)
    });
  } catch {
    throw new ApiError(0, 'We couldn’t reach TuitionHub. Check your connection and try again.');
  }
}

async function errorFrom(response: Response): Promise<ApiError> {
  const data = (await response.json().catch(() => null)) as {
    message?: unknown;
    reason?: unknown;
    errors?: unknown;
  } | null;

  return new ApiError(
    response.status,
    typeof data?.message === 'string' ? data.message : 'Something went wrong. Please try again.',
    typeof data?.reason === 'string' ? data.reason : null,
    data?.errors && typeof data.errors === 'object' ? (data.errors as Record<string, string[]>) : {}
  );
}

/** One request at a time refreshes the CSRF cookie; concurrent writes share it. */
let csrfRequest: Promise<void> | null = null;

function refreshCsrfCookie(): Promise<void> {
  csrfRequest ??= send('GET', '/sanctum/csrf-cookie')
    .then(async (response) => {
      if (!response.ok) throw await errorFrom(response);
    })
    .finally(() => {
      csrfRequest = null;
    });

  return csrfRequest;
}

async function request<T>(method: Method, path: string, body?: unknown): Promise<T> {
  const changesState = method !== 'GET';

  if (changesState && !readCookie(XSRF_COOKIE)) {
    await refreshCsrfCookie();
  }

  let response = await send(method, path, body);

  // 419: the CSRF token expired or the session was replaced. Refresh it and retry once only.
  if (response.status === 419 && changesState) {
    await refreshCsrfCookie();
    response = await send(method, path, body);
  }

  if (response.status === 204) return undefined as T;
  if (!response.ok) throw await errorFrom(response);

  return (await response.json()) as T;
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path)
};

// ---------------------------------------------------------------------------------------------
// Typed endpoints (contract v0.6 §4)

export interface SessionRole {
  role: 'super_admin' | 'institute_admin' | 'institute_staff';
  institute_uuid: string | null;
  institute_name: string | null;
}

export interface TeachingInstitute {
  institute_uuid: string;
  institute_name: string;
}

/** GET /auth/session and PATCH /me (§4.4, §4.5). Display hints only; the API re-checks access. */
export interface AccountSession {
  user: { uuid: string; name: string | null; phone: string; status: 'active' | 'suspended' };
  roles: SessionRole[];
  teaching_at: TeachingInstitute[];
  active_child_link_count: number;
  has_teacher_profile: boolean;
  has_guardian_profile: boolean;
  onboarding_required: boolean;
}

export interface OtpChallenge {
  data: { challenge_id: string; expires_at: string; resend_available_at: string };
  meta: { otp_driver: string; dev_mode: boolean };
}

export interface LookupEntry {
  code: string;
  name: string;
}

export interface Lookups {
  subjects: (LookupEntry & { name_si: string | null; name_ta: string | null; category: string })[];
  grades: (LookupEntry & { level: string; sort_order: number })[];
  streams: (LookupEntry & { level: string })[];
  exam_targets: (LookupEntry & { exam: 'ol' | 'al'; year: number; approximate_exam_date: string | null })[];
  mediums: LookupEntry[];
  syllabi: LookupEntry[];
  districts: string[];
}

export const authApi = {
  requestOtp: (phone: string) =>
    api.post<OtpChallenge>('/api/v1/auth/otp/request', { phone, purpose: 'login' }),

  verifyOtp: (challengeId: string, phone: string, code: string) =>
    api.post<unknown>('/api/v1/auth/otp/verify', { challenge_id: challengeId, phone, code }),

  session: async () => (await api.get<{ data: AccountSession }>('/api/v1/auth/session')).data,

  logout: () => api.post<void>('/api/v1/auth/logout'),

  updateName: async (name: string) => (await api.patch<{ data: AccountSession }>('/api/v1/me', { name })).data
};

export const lookupsApi = {
  all: async () => (await api.get<{ data: Lookups }>('/api/v1/lookups')).data
};
