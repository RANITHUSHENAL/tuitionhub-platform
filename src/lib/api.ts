/**
 * TuitionHub API client (contract v0.9 §1.2–§1.4).
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
  /** Seconds from the `Retry-After` header on a 429 or 503, when the API sent one. */
  readonly retryAfter: number | null;

  constructor(
  status: number,
  message: string,
  reason: string | null = null,
  fieldErrors: Record<string, string[]> = {},
  retryAfter: number | null = null)
  {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.reason = reason;
    this.fieldErrors = fieldErrors;
    this.retryAfter = retryAfter;
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

  const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);

  return new ApiError(
    response.status,
    typeof data?.message === 'string' ? data.message : 'Something went wrong. Please try again.',
    typeof data?.reason === 'string' ? data.reason : null,
    data?.errors && typeof data.errors === 'object' ? (data.errors as Record<string, string[]>) : {},
    Number.isFinite(retryAfter) && retryAfter >= 0 ? retryAfter : null
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

// ---------------------------------------------------------------------------------------------
// F3: institutes, teachers, students and guardian links (contract v0.9 §5, §6, §8, §9)

/** Standard pagination `meta` (§1.3): exactly these four keys. */
export interface PageMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Page<T> {
  data: T[];
  meta: PageMeta;
}

/** Builds `?a=1&b=2`, leaving out empty values. Values are always URL-encoded. */
function query(params: Record<string, string | number | null | undefined>): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
  }

  const text = search.toString();
  return text ? `?${text}` : '';
}

/** Path segments are encoded so a UUID can never change the route. */
const seg = (value: string) => encodeURIComponent(value);

export interface Institute {
  uuid: string;
  name: string;
  description: string | null;
  status: 'active' | 'suspended';
  district: string;
  city: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  business_reg_no: string | null;
  created_at: string;
  updated_at: string;
}

/** §5.1 request; §5.5 accepts any subset (institute admins never send `status`). */
export interface InstituteInput {
  name: string;
  description: string | null;
  district: string;
  city: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  business_reg_no: string | null;
}

export interface Teacher {
  uuid: string;
  display_name: string;
  headline: string | null;
  bio: string | null;
  years_experience: number;
}

export interface TeacherProfileInput {
  display_name?: string;
  headline?: string | null;
  bio?: string | null;
  years_experience?: number;
}

export interface TeacherAssignment {
  teacher: Teacher;
  institute_uuid?: string;
  status: 'active' | 'former';
  joined_on: string;
  left_on: string | null;
}

export interface CodeName {
  code: string;
  name: string;
}

export interface Student {
  uuid: string;
  full_name: string;
  grade: CodeName;
  exam_target: CodeName | null;
  medium: CodeName;
  date_of_birth: string | null;
  has_user_account: boolean;
  active_guardian_count: number;
  created_at: string;
}

export interface StudentInput {
  full_name: string;
  grade_code: string;
  exam_target_code: string | null;
  medium_code: string;
  date_of_birth: string | null;
}

export type GuardianRelationship = 'father' | 'mother' | 'guardian' | 'uncle' | 'aunt';

/**
 * §9.1 response. `code` is the plaintext invitation code, returned only in this one response:
 * keep it in component state for the open dialog and nowhere else.
 */
export interface CreatedInvitation {
  uuid: string;
  student: { uuid: string; full_name: string };
  guardian_phone_masked: string;
  relationship: GuardianRelationship;
  status: 'pending';
  expires_at: string;
  code: string;
}

export interface LinkedChild {
  student: { uuid: string; full_name: string; grade: CodeName };
  relationship: GuardianRelationship;
  linked_at: string;
  active_enrollments: {uuid: string;class: {uuid: string;title: string;};institute: {name: string;};}[];
}

export const instituteApi = {
  /** §5.1 — the caller becomes owner and `institute_admin`. */
  create: async (input: InstituteInput) =>
  (await api.post<{ data: Institute }>('/api/v1/institutes', input)).data,

  /** §5.4 — same-institute admin/staff. */
  get: async (instituteUuid: string) =>
  (await api.get<{ data: Institute }>(`/api/v1/institutes/${seg(instituteUuid)}`)).data,

  /** §5.5 — same-institute admin; never sends `status`. */
  update: async (instituteUuid: string, input: Partial<InstituteInput>) =>
  (await api.patch<{ data: Institute }>(`/api/v1/institutes/${seg(instituteUuid)}`, input)).data
};

export const teacherApi = {
  /** §6.1 — creates (201) or updates (200) the caller's own profile. An empty body changes nothing
   *  and returns the stored profile, which is how an existing profile's UUID is shown. */
  saveMyProfile: async (input: TeacherProfileInput) =>
  (await api.post<{ data: Teacher }>('/api/v1/me/teacher-profile', input)).data,

  /** §6.2 — same-institute admin. */
  assign: async (instituteUuid: string, teacherUuid: string, joinedOn: string) =>
  (await api.post<{ data: TeacherAssignment }>(
    `/api/v1/institutes/${seg(instituteUuid)}/teacher-assignments`,
    { teacher_uuid: teacherUuid, joined_on: joinedOn }
  )).data,

  /** §6.3 — same-institute admin/staff. */
  list: (instituteUuid: string, params: {status?: 'active' | 'former';page?: number;per_page?: number;} = {}) =>
  api.get<Page<TeacherAssignment>>(`/api/v1/institutes/${seg(instituteUuid)}/teachers${query(params)}`)
};

export const studentApi = {
  /** §8.1 — same-institute admin/staff. */
  create: async (instituteUuid: string, input: StudentInput) =>
  (await api.post<{ data: Student }>(`/api/v1/institutes/${seg(instituteUuid)}/students`, input)).data,

  /** §8.2 — same-institute admin/staff; `q` and `grade_code` filters. */
  list: (instituteUuid: string, params: {q?: string;grade_code?: string;page?: number;per_page?: number;} = {}) =>
  api.get<Page<Student>>(`/api/v1/institutes/${seg(instituteUuid)}/students${query(params)}`)
};

export const guardianApi = {
  /** §9.1 — returns the one-time code (see CreatedInvitation). */
  invite: async (studentUuid: string, guardianPhone: string, relationship: GuardianRelationship) =>
  (await api.post<{ data: CreatedInvitation }>(
    `/api/v1/students/${seg(studentUuid)}/guardian-invitations`,
    { guardian_phone: guardianPhone, relationship }
  )).data,

  /** §9.3 — the inviter, or any admin of the same institute. */
  revokeInvitation: async (invitationUuid: string) =>
  (await api.post<{ data: {uuid: string;status: 'revoked';revoked_at: string;} }>(
    `/api/v1/guardian-invitations/${seg(invitationUuid)}/revoke`
  )).data,

  /** §9.4 — institute admin; `guardianUuid` is the guardian's user (account) UUID. */
  revokeLink: (studentUuid: string, guardianUuid: string) =>
  api.delete<void>(`/api/v1/students/${seg(studentUuid)}/guardian-links/${seg(guardianUuid)}`),

  /** §9.2 — any signed-in account whose phone matches the invitation. */
  accept: async (code: string) =>
  (await api.post<{ data: {student: {uuid: string;full_name: string;};relationship: GuardianRelationship;linked_at: string;} }>(
    '/api/v1/guardian-invitations/accept',
    { code }
  )).data,

  /** §9.5 — only the caller's active links, re-read on every request. */
  myChildren: (params: {page?: number;per_page?: number;} = {}) =>
  api.get<Page<LinkedChild>>(`/api/v1/parents/me/children${query(params)}`)
};
