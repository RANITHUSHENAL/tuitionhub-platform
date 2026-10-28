# TuitionHub API Contract — Sprint Draft

| | |
|---|---|
| **Version** | `v1` draft 0.3 |
| **Date** | 2026-10-28 (0.1: 2026-10-19; 0.2: 2026-10-28) |
| **Status** | **Draft** — the contract for the four-week demo sprint. Endpoints may change before implementation; any change is recorded in §16. |
| **Base prefix** | `/api/v1` (Laravel app under `/api`) |
| **Decisions** | Implements `docs/PRODUCT_DECISIONS.md` C1–C8 and the OTP decision. The role model in §3 follows approved C8 (`docs/DECISION_LOG.md` D-011). Guardianship, invitation codes, guardian billing access and audit rules follow approved D-015. |
| **Schema source** | `docs/reference/TuitionHub_ER_Schema.md` (canonical starting schema), adapted as listed in §2. |

> **Demo-money boundary.** Nothing in this contract moves real money. All payments are `provider: "mock"`, all commission rows are demo accruals, and there are no settlement, payout, refund-processing or live-gateway endpoints. See §11.
>
> **Marketplace boundary.** The public catalogue (§7.5) is a minimal, neutrally ordered list. Boost purchases (§12) only **record** a labelled demo purchase and **never** affect catalogue placement, ordering or ranking. Reviews, ratings, featured slots and paid/advanced ranking are Month 3+ (C1).

---

## 1. Conventions

### 1.1 Security baseline (applies to every endpoint)

- **Every permission is checked on the backend** (Laravel Form Requests + Policies/Gates). Frontend checks are cosmetic only.
- **Every tenant-scoped query is scoped before data is returned** — scope by institute / assignment / active guardian link in the query itself, never by filtering a broader result in memory or in the client.
- The server derives all relationships, actor IDs and financial values (owner, marker, payer, inviter, amounts, commission). Client-supplied values for these are ignored or rejected.
- Public routes use UUIDs only. Internal `bigint` IDs never appear in requests or responses.
- OTP codes and guardian invitation codes are never logged, never written to audit rows, and never stored in plaintext.
- Responses containing a one-time secret (§9.1) send `Cache-Control: no-store`.

### 1.2 Authentication (Sanctum SPA mode)

- First-party SPA cookie/session authentication with Laravel Sanctum. No bearer tokens are issued in the sprint.
- The SPA first calls `GET /sanctum/csrf-cookie` (Sanctum default route, outside `/api/v1`), then sends `X-XSRF-TOKEN` on state-changing requests with credentials included.
- The session ID is regenerated on login (§4.2) and invalidated on logout (§4.3).
- **Permission labels** used below: `public` (no auth), `authenticated` (any logged-in active user), and the scoped roles defined in §3.

### 1.3 Envelope

Success:

```json
{ "data": {}, "meta": {} }
```

`meta` is optional for single resources. Collections always paginate:

```json
{
  "data": [],
  "meta": { "current_page": 1, "per_page": 25, "total": 0, "last_page": 1 }
}
```

Laravel's default `links` object may also be present; clients must not depend on it. Query parameters `page` (default 1) and `per_page` (default 25, **max 100**; values above 100 are rejected with `422`).

Errors (Laravel default shape):

```json
{
  "message": "The given data was invalid.",
  "errors": { "phone": ["The phone field format is invalid."] }
}
```

`errors` is present for `422`; other error codes return at least `message`.

### 1.4 Status codes

| Code | Meaning |
|---|---|
| `200` | OK (read, update, idempotent replay) |
| `201` | Created |
| `204` | No content |
| `401` | Unauthenticated |
| `403` | Authenticated and the resource is visible to the caller, but the action is not permitted |
| `404` | Resource does not exist **or is hidden from the caller** (cross-tenant, unlinked child, private class). Never use `403` in a way that reveals a hidden resource exists. |
| `409` | State conflict (duplicate active enrolment, capacity full, already paid, invitation already used, …) |
| `422` | Validation error |
| `429` | Rate limited / attempts exhausted (`Retry-After` header set) |

### 1.5 Data types

| Type | Format |
|---|---|
| UUID | RFC 4122 v4 string |
| Timestamp | ISO 8601 UTC, e.g. `"2026-10-19T08:30:00Z"`. All stored timestamps are UTC. |
| Date | `"YYYY-MM-DD"`, a calendar date in `Asia/Colombo` |
| Time | `"HH:MM"` (24h), local time in `Asia/Colombo`; class schedules and sessions are interpreted in `Asia/Colombo` |
| Money | String with exactly 2 decimals, e.g. `"2500.00"` (stored as `DECIMAL(15,2)`). Never JSON floats. |
| Currency | `"LKR"` only in sprint; any other value → `422` |
| Phone | E.164 Sri Lankan mobile, `^\+947\d{8}$`, e.g. `"+94771234567"` |
| Taxonomy codes | Codes from the seeded lookup tables (`subjects.code`, `grades.code`, `streams.code`, `exam_targets.code`, `mediums.code`, `syllabi.code`) |

### 1.6 Writes

- Multi-step writes run in a single database transaction.
- Money writes (payment attempts, demo completion, commission accrual, boost purchases) are idempotent (§11, §12).
- Rows that must be unique under concurrency (attendance, active enrolment, commission per payment, invitation consumption) are protected by unique indexes **and** row locks, not only by application checks.
- Audit events are listed in §13.

---

## 2. Sprint data model and schema adaptations

The canonical schema is the starting point. Only the groups below are built in the sprint. Table names follow the canonical schema unless marked **new**.

### 2.1 Sprint table groups

| Group | Tables |
|---|---|
| Lookups (seeded) | `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi`, `roles` |
| Identity & auth | `users`, `user_roles`, `otp_challenges` (adapted from `otp_verifications`), `teacher_profiles`, `student_profiles`, `guardian_profiles`, Laravel `sessions`/`cache`/`jobs` framework tables |
| Organisation | `institutes`, `institute_staff`, `teacher_institute` |
| Guardianship | `student_guardians`, `guardian_invitations` (**new**) |
| Classes | `classes`, `class_fee_versions`, `class_enrollments`, `class_sessions` |
| Attendance | `attendances` |
| Money (demo) | `commission_rules` (**new**), `invoices`, `invoice_items`, `payments`, `payment_transactions`, `platform_commissions` |
| Boost (demo) | `boost_products` (**new**), `boost_purchases` (**new**) |
| Audit | `audit_logs` |

### 2.2 Not built in sprint

Settlements and payouts (`settlements`, `settlement_items`, `institute_bank_accounts`), `refunds`, subscriptions (`subscription_plans`, `institute_subscriptions`), `institute_settings` commission/settlement fields, `institute_branches`, `halls`, `institute_documents`, `institute_owner_profiles`, `class_schedules` (sessions are created explicitly), `attendance_devices`, `schools`, `teacher_qualifications`, `teacher_subjects`, all exams/marks tables, announcements, study materials, past papers, notifications/SMS/push/email logs, all marketplace tables (`class_listings`, `reviews`, `review_responses`, `featured_listings`, `saved_listings`, `search_queries`), `activity_logs`, `webhook_logs`, `permissions`/`role_permissions`, `user_devices`, and all Study/chat/meetup tables (C7).

### 2.3 Adaptations from the canonical schema

| Canonical | Sprint adaptation | Reason |
|---|---|---|
| `users.type` discriminator **and** `user_roles` | `users.type` is **not built**. A custom `user_roles` table (nullable `institute_id`) holds only `super_admin`, `institute_admin`, `institute_staff`. Teacher and guardian access are derived from `teacher_institute` and `student_guardians` (§3) | **C8 approved** |
| `users.email` unique, `users.password` | `email` nullable; `password` nullable (OTP login only) | Phone-only OTP login |
| `users.name` | Nullable until onboarding; `onboarding_required` is true while null | First OTP verify may create a user |
| Partial unique indexes (`WHERE deleted_at IS NULL`, `WHERE withdrawn_on IS NULL`) | MySQL has no partial indexes. Use a nullable generated/flag column in the unique key (e.g. `active_flag` = `1` when active, `NULL` otherwise) | MySQL compatibility |
| `otp_verifications` (`phone, code, purpose, expires_at, consumed_at`) | `otp_challenges`: `uuid`, `phone`, `code_hash`, `purpose`, `attempts`, `max_attempts`, `expires_at`, `consumed_at`, `last_sent_at`, `request_ip_hash` | Attempts, cooldown and public challenge ID |
| `teacher_profiles` (no public ID) | Add `uuid` | Referenced in public routes/requests |
| `teacher_profiles.is_independent`, `public_visibility` | Columns kept with defaults (`false`, `institute_attributed`); not editable via sprint API | Independent-teacher flow not in sprint |
| `student_profiles.user_id` (required), no name | Add `uuid`, `full_name`, `managing_institute_id`, `created_by_user_id`; `user_id` **nullable** | Institute-managed student records (C6) |
| `student_profiles.nic`, `school_id` | **Not built** | No NIC/ID collection for minors (C6); schools lookup out of scope |
| `student_guardians` | Add `linked_by_user_id`, `linked_at`, `revoked_by_user_id`, `revoked_at`, `invitation_id`; never hard-deleted; one active link per (student, guardian); at most one **active primary** link per student (§9.2, §9.4) | C6 history/audit; D-015 |
| — | **New** `guardian_invitations`: `uuid`, `student_id`, `institute_id`, `invited_by_user_id`, `guardian_phone`, `relationship`, `code_hash` (HMAC-SHA256 keyed by the dedicated secret `GUARDIAN_INVITE_HMAC_KEY`, §9.1), `status` (`pending\|accepted\|revoked\|expired`), `expires_at`, `accepted_at`, `accepted_by_user_id`, `revoked_at`, `revoked_by_user_id` | C6 invitation codes |
| Automatic 72-hour child restriction | **Removed** | C6 |
| `classes.hall_id`, `slug` | `hall_id` not built; `slug` optional/not used in routes | Halls out of scope; routes use UUID |
| `class_enrollments.qr_token`, `student_card_no` | Not generated in sprint | Attendance method is `manual` only |
| `attendances` | Unique (`session_id`, `enrollment_id`) kept; `method` restricted to `manual` in sprint | QR/card/geo out of scope |
| `institute_settings.platform_commission_pct = 2.0`, `parent_pays_commission` | **Not copied.** Replaced by **new** `commission_rules`: `uuid`, `scope` (`platform\|institute\|class`), `institute_id`, `class_id`, `rule_type` (`flat\|percentage`), `flat_amount`, `percentage`, `currency`, `effective_from`, `effective_to`, `created_by_user_id`. Seeded platform rule: flat `100.00` LKR | C3: Rs 100 flat default, no universal 2% |
| `invoices` | Add snapshot columns: `fee_amount_snapshot`, `commission_rule_id`, `commission_rule_type_snapshot`, `commission_flat_snapshot`, `commission_percentage_snapshot`; add `is_demo` (always `true` in sprint) | C3 snapshots, demo label |
| `payments.method` (`card\|wallet\|bank\|cash`), statuses incl. `refunded` | Add `provider` (`mock` only), `is_demo`; statuses `pending\|succeeded\|failed\|cancelled` | C2 demo flow; no refunds |
| `payment_transactions.gateway` (`payhere\|…`) | `gateway = "mock"` only | No live gateway |
| `platform_commissions.rate_pct_snapshot`, `settlement_id`, status `accrued\|settled\|refunded` | Replace `rate_pct_snapshot` with rule snapshot columns (as on invoices); **drop** `settlement_id`; status `accrued` only; add `uuid`, `is_demo`; unique `payment_id` | C3/C5: no settlement schema |
| `featured_listings` | **Not built.** Boost uses **new** `boost_products` (`uuid`, `code`, `name`, `description`, `price`, `currency`, `is_active`, `is_demo`) and `boost_purchases` (`uuid`, `class_id`, `institute_id`, `boost_product_id`, `price_snapshot`, `currency`, `status` = `demo_recorded`, `idempotency_key` unique, `purchased_by_user_id`) with **no** placement/start/end fields | C1: record only, no placement |

---

## 3. Roles and permission vocabulary

This section implements approved C8 (`docs/PRODUCT_DECISIONS.md` C8; details in `docs/DECISION_LOG.md` D-011).

- **`user_roles` holds only three roles:** `super_admin`, `institute_admin` and `institute_staff`. It is a custom table (`user_id`, `role_id`, `institute_id` nullable); no permissions package is used.
- **Teacher and guardian access are derived from relationship tables, NOT from `user_roles`.** No `teacher` or `guardian` row is ever written to `user_roles`.
  - **Teacher:** requires an active `teacher_institute` association with the institute **and**, for class data (sessions, rosters, attendance), being that class's assigned teacher (`classes.teacher_id`). An institute association alone does not open the institute's classes.
  - **Guardian:** requires an active `student_guardians` link to that student.
  - **Ended or revoked relationships grant nothing.** An ended assignment (`left_on` set, status `former`) or a revoked link (`revoked_at` set) is ignored, and access is lost on the next request.
- **Institute staff** may create students (§8.1), enrol them (§8.3) and invite parents (§9.1). Revoking a guardian link (§9.4) stays with the institute admin or `super_admin`.
- **`users.type` is not built.** No permission check reads a user-type column.
- **`super_admin` is read-only on invoices and payments.** It may read invoices, payments and commission accruals (§11.3, §11.5, §11.8). It may **not** create invoices (§11.2), create payment attempts (§11.6) or complete demo payments (§11.7); those return `403`.

The permission labels used in this contract mean:

| Label | Definition |
|---|---|
| `super_admin` | `super_admin` role with `institute_id = NULL`. Platform operator. |
| **Institute owner** | The user in `institutes.owner_user_id`. Always also holds `institute_admin` for that institute (server-assigned at creation). |
| **Institute admin** | `institute_admin` role scoped to that institute. |
| **Institute staff** | `institute_staff` role scoped to that institute with an active `institute_staff` row. |
| **Institute manager** | Institute admin, or institute staff whose `institute_staff.position = manager`. |
| **Billing user** | Institute admin of that institute (this includes the owner). Non-admin staff and `super_admin` are not billing users. |
| **Assigned teacher** | User whose `teacher_profiles` row is the class's `classes.teacher_id` **and** has an active `teacher_institute` row for the class's institute. |
| **Linked guardian** | User with a `guardian_profiles` row and an **active** `student_guardians` link (`revoked_at IS NULL`) to the student. Checked on every request; revocation takes effect immediately. |
| **Same-institute** | The role's `institute_id` equals the target resource's institute. A role at Institute A grants nothing at Institute B. |

Suspended users (`users.status = suspended`) are rejected with `403` on all authenticated endpoints except logout. Requests against a suspended institute by non-super-admins return `403` for writes; reads remain available to that institute's admins.

---

## 4. Auth

### 4.1 `POST /api/v1/auth/otp/request`

- **Permission:** `public`, throttled.
- **Request:**

```json
{ "phone": "+94771234567", "purpose": "login" }
```

`purpose`: `login` only in sprint.

- **Success `201`:**

```json
{
  "data": {
    "challenge_id": "6b0e…-uuid",
    "expires_at": "2026-10-19T08:35:00Z",
    "resend_available_at": "2026-10-19T08:31:00Z"
  },
  "meta": { "otp_driver": "log", "dev_mode": true }
}
```

- **Rules:**
  - The response **never** contains the OTP code. The response is identical whether or not the phone belongs to an existing user.
  - Code: random 6 digits, stored as a hash only. Driver `log` writes it to the local Laravel log; it refuses to run (fails closed, `503`) when `APP_ENV=production`. A failing SMS driver returns `503` and never falls back to `log`.
  - `meta.dev_mode` is `true` only when the `log` driver is active; the login UI shows `DEV MODE — no SMS is sent` only in that case.
  - Proposed config defaults: expiry 5 min; resend cooldown 60 s; max 3 requests per phone per 15 min and 10 per IP per hour.
- **Errors:** `422` invalid phone/purpose; `429` cooldown or rate limit (`Retry-After`); `503` OTP driver unavailable.

### 4.2 `POST /api/v1/auth/otp/verify`

- **Permission:** `public`, throttled. Challenge must exist, match the phone, be unexpired, unconsumed and under its attempt limit.
- **Request:**

```json
{ "challenge_id": "uuid", "phone": "+94771234567", "code": "123456" }
```

- **Success `200`:**

```json
{
  "data": {
    "user": {
      "uuid": "uuid",
      "name": "Nimal Perera",
      "phone": "+94771234567",
      "roles": [
        { "role": "institute_admin", "institute_uuid": "uuid" }
      ],
      "teaching_at": [
        { "institute_uuid": "uuid", "institute_name": "Lakmina Academy" }
      ],
      "active_child_link_count": 1
    },
    "onboarding_required": false
  }
}
```

- **Rules:**
  - On success the challenge is consumed atomically, the session is regenerated and the user is logged in.
  - If no user exists for the phone, a user is created with `name = null`, `phone_verified_at` set and no roles (`roles: []`, `teaching_at: []`, `active_child_link_count: 0`); `onboarding_required = true`.
  - `roles`, `teaching_at` and `active_child_link_count` have the same meaning as in §4.4. These fields are display hints only; authorization is re-derived server-side on every request.
  - In `log` mode, `phone_verified_at` means "verified via local dev OTP" and must not be described as real phone verification.
  - Proposed defaults: 5 attempts per challenge; 10 verify calls per IP per 10 min.
- **Errors:** `422` wrong/expired/consumed code or mismatched phone (single generic message); `429` attempts exhausted or throttled.

### 4.3 `POST /api/v1/auth/logout`

- **Permission:** `authenticated`.
- **Request:** none.
- **Success `204`.** Session invalidated and CSRF token regenerated.
- **Errors:** `401`.

### 4.4 `GET /api/v1/auth/session`

- **Permission:** `authenticated`.
- **Success `200`:**

```json
{
  "data": {
    "user": { "uuid": "uuid", "name": "Nimal Perera", "phone": "+94771234567", "status": "active" },
    "roles": [
      { "role": "institute_admin", "institute_uuid": "uuid", "institute_name": "Lakmina Academy" }
    ],
    "teaching_at": [
      { "institute_uuid": "uuid", "institute_name": "Lakmina Academy" }
    ],
    "active_child_link_count": 0,
    "has_teacher_profile": true,
    "has_guardian_profile": false,
    "onboarding_required": false
  }
}
```

- **Fields:**
  - `roles`: the caller's **stored** `user_roles` rows only, so `role` is one of `super_admin`, `institute_admin` or `institute_staff`. `institute_uuid` (and `institute_name`) is `null` for the global `super_admin` role and set for institute roles. `teacher` and `guardian` never appear here.
  - `teaching_at`: one `{ institute_uuid, institute_name }` entry per **active** `teacher_institute` assignment. Ended assignments are excluded. It lists institutes, not classes: class access still requires being the class's assigned teacher (§3).
  - `active_child_link_count`: the number of **active** `student_guardians` links where the caller is the guardian. Revoked links are not counted.
  - These fields are display hints only; authorization is re-derived server-side on every request.
- **Errors:** `401`.

---

## 5. Institutes

Institute shape (`Institute`):

```json
{
  "uuid": "uuid",
  "name": "Lakmina Academy",
  "description": "A/L science classes in Nugegoda.",
  "status": "active",
  "district": "Colombo",
  "city": "Nugegoda",
  "contact_phone": "+94771234567",
  "contact_email": "office@example.test",
  "business_reg_no": null,
  "created_at": "2026-10-19T08:30:00Z",
  "updated_at": "2026-10-19T08:30:00Z"
}
```

### 5.1 `POST /api/v1/institutes`

- **Permission:** `authenticated` and onboarding-eligible: active user with `phone_verified_at` set and a non-null `name`. Further eligibility limits (caps, approval) are not in sprint.
- **Request:**

```json
{
  "name": "Lakmina Academy",
  "description": "A/L science classes in Nugegoda.",
  "district": "Colombo",
  "city": "Nugegoda",
  "contact_phone": "+94771234567",
  "contact_email": "office@example.test",
  "business_reg_no": null
}
```

- **Success `201`:** `{ "data": Institute }`
- **Rules:** The server sets `owner_user_id` to the caller and grants `institute_admin` scoped to the new institute in the same transaction. Any `owner_*`, `status` or ID field in the request is rejected (`422`). New institutes are created `active` for the demo (proposal; production review flow is not in sprint).
- **Errors:** `401`, `403` (not eligible), `422`.

### 5.2 `GET /api/v1/institutes?status=&district=&q=&page=&per_page=`

- **Permission:** `super_admin` only.
- **Success `200`:** paginated `Institute[]`.
- **Errors:** `401`, `403`.

### 5.3 `GET /api/v1/me/institutes`

- **Permission:** `authenticated`. Returns only institutes where the caller holds a stored institute role (`institute_admin` or `institute_staff`) **or** has an active `teacher_institute` assignment. A global `super_admin` role does not list institutes here (see §5.2).
- **Success `200`:** paginated

```json
{ "data": [ { "institute": "Institute", "my_access": ["institute_admin", "teacher"] } ], "meta": {} }
```

- **Rules:** `my_access` lists the caller's applicable access labels for that institute: each stored institute role, plus `teacher` when the caller has an active assignment there. `teacher` does not grant general access to the institute's classes; class data still requires being the class's assigned teacher (§3). These fields are display hints only; authorization is re-derived server-side on every request.

- **Errors:** `401`.

### 5.4 `GET /api/v1/institutes/{instituteUuid}`

- **Permission:** same-institute manager/staff/admin, or `super_admin`. Public institute data is exposed only through the public catalogue (§7.5).
- **Success `200`:** `{ "data": Institute }`
- **Errors:** `401`, `404` (missing, or the caller is not same-institute staff/admin).

### 5.5 `PATCH /api/v1/institutes/{instituteUuid}`

- **Permission:** same-institute owner/admin, or `super_admin`.
- **Request (all optional):**

```json
{
  "name": "Lakmina Academy",
  "description": "…",
  "district": "Colombo",
  "city": "Nugegoda",
  "contact_phone": "+94771234567",
  "contact_email": "office@example.test",
  "business_reg_no": null,
  "status": "suspended"
}
```

- **Success `200`:** `{ "data": Institute }`
- **Rules:** Only the fields above are accepted. `status` (`active|suspended`) may be changed **only** by `super_admin`; an institute admin sending `status` gets `403`. Owner cannot be changed via this endpoint. Audited.
- **Errors:** `401`, `403`, `404`, `422`.

---

## 6. Teachers

Teacher shape (`Teacher`):

```json
{
  "uuid": "uuid",
  "display_name": "Mr. Kasun Silva",
  "headline": "A/L Combined Maths",
  "bio": "…",
  "years_experience": 8
}
```

### 6.1 `POST /api/v1/me/teacher-profile`

- **Permission:** `authenticated`; acts only on the caller's own profile.
- **Request:**

```json
{ "display_name": "Mr. Kasun Silva", "headline": "A/L Combined Maths", "bio": "…", "years_experience": 8 }
```

- **Success:** `201` when created, `200` when updated — `{ "data": Teacher }`.
- **Rules:** Creating a profile grants no institute access. `user_id` is taken from the session.
- **Errors:** `401`, `422`.

### 6.2 `POST /api/v1/institutes/{instituteUuid}/teacher-assignments`

- **Permission:** same-institute owner/admin, or `super_admin`.
- **Request:**

```json
{ "teacher_uuid": "uuid", "joined_on": "2026-10-19" }
```

`teacher_uuid` is the teacher profile UUID, shared by the teacher from their own profile screen (no teacher directory/search endpoint in sprint).

- **Success `201`:**

```json
{
  "data": {
    "teacher": "Teacher",
    "institute_uuid": "uuid",
    "status": "active",
    "joined_on": "2026-10-19",
    "left_on": null
  }
}
```

- **Rules:** Creates **only** an active `teacher_institute` row. No `user_roles` row is created; the teacher's access is derived from this active assignment (§3). The assignment takes effect immediately; there is no teacher invitation or consent step in the sprint (C8). Assignments are never deleted (ending one sets `left_on`/`former`; no end endpoint in sprint).
- **Errors:** `401`, `403`, `404` (institute), `409` (already actively assigned), `422` (unknown teacher UUID).

### 6.3 `GET /api/v1/institutes/{instituteUuid}/teachers?status=&page=&per_page=`

- **Permission:** same-institute staff/admin, or `super_admin`.
- **Success `200`:** paginated `[{ "teacher": Teacher, "status": "active", "joined_on": "…", "left_on": null }]`.
- **Errors:** `401`, `404`.

### 6.4 `GET /api/v1/me/teacher/classes?status=&page=&per_page=`

- **Permission:** `authenticated` with a teacher profile. Returns only classes where the caller is the assigned teacher with an active assignment.
- **Success `200`:** paginated `Class[]` (§7).
- **Errors:** `401`, `403` (no teacher profile).

---

## 7. Classes and catalogue

Class shape (`Class`, authorised views):

```json
{
  "uuid": "uuid",
  "title": "A/L Combined Maths 2027 — Theory",
  "description": "…",
  "status": "active",
  "mode": "in_person",
  "max_students": 60,
  "active_enrollment_count": 42,
  "starts_on": "2026-11-01",
  "ends_on": null,
  "public_listing": true,
  "institute": { "uuid": "uuid", "name": "Lakmina Academy", "district": "Colombo", "city": "Nugegoda" },
  "teacher": { "uuid": "uuid", "display_name": "Mr. Kasun Silva" },
  "subject": { "code": "MATH", "name": "Combined Mathematics" },
  "grade": { "code": "AL2", "name": "A/L Year 2" },
  "stream": { "code": "SCI_MATH", "name": "Physical Science" },
  "exam_target": { "code": "AL_2027", "name": "A/L 2027" },
  "medium": { "code": "sin", "name": "Sinhala" },
  "syllabus": { "code": "local", "name": "Local" },
  "current_fee": { "amount": "2500.00", "currency": "LKR", "billing_cycle": "monthly", "effective_from": "2026-11-01" },
  "created_at": "…",
  "updated_at": "…"
}
```

`PublicClass` is the minimal subset: `uuid`, `title`, `description`, `mode`, `starts_on`, `subject`, `grade`, `stream`, `exam_target`, `medium`, `institute` (`name`, `district`, `city` only), `teacher` (`display_name`, omitted when `public_visibility = hidden`), `current_fee`. No enrolment counts, ratings, reviews, rank or boost indicators.

### 7.1 `POST /api/v1/institutes/{instituteUuid}/classes`

- **Permission:** same-institute owner/admin.
- **Request:**

```json
{
  "title": "A/L Combined Maths 2027 — Theory",
  "description": "…",
  "teacher_uuid": "uuid",
  "subject_code": "MATH",
  "grade_code": "AL2",
  "stream_code": "SCI_MATH",
  "exam_target_code": "AL_2027",
  "medium_code": "sin",
  "syllabus_code": "local",
  "mode": "in_person",
  "max_students": 60,
  "starts_on": "2026-11-01",
  "ends_on": null,
  "public_listing": true,
  "status": "draft",
  "fee": { "amount": "2500.00", "currency": "LKR", "billing_cycle": "monthly", "effective_from": "2026-11-01" }
}
```

`stream_code` and `ends_on` nullable; `status` `draft|active` (default `draft`); `mode` `in_person|online|hybrid`; `billing_cycle` `monthly|term|one_time`; `max_students` ≥ 1; `fee.amount` > 0.

- **Success `201`:** `{ "data": Class }`
- **Rules:** Creates the class and its first `class_fee_versions` row (with `created_by_user_id` from session) in one transaction. `teacher_uuid` must have an active assignment to this institute. Audited (`class.created`, `fee.version_created`).
- **Errors:** `401`, `403`, `404`, `422`.

### 7.2 `GET /api/v1/institutes/{instituteUuid}/classes?status=&subject_code=&grade_code=&teacher_uuid=&page=&per_page=`

- **Permission:** same-institute manager/staff/admin, or `super_admin`.
- **Success `200`:** paginated `Class[]`.
- **Errors:** `401`, `404`.

### 7.3 `GET /api/v1/classes/{classUuid}`

- **Permission:** `public` **only** when `status = active` and `public_listing = true` (returns `PublicClass`). Otherwise restricted to the assigned teacher, same-institute staff/admin or `super_admin` (returns `Class`).
- **Success `200`:** `{ "data": PublicClass | Class }`
- **Errors:** `404` for draft/archived/private classes when the caller is not authorised.

### 7.4 `PATCH /api/v1/classes/{classUuid}`

- **Permission:** owning-institute admin, or `super_admin`.
- **Request (all optional):** any of `title`, `description`, `teacher_uuid`, taxonomy codes, `mode`, `max_students`, `starts_on`, `ends_on`, `public_listing`, `status` (`draft|active|archived`), and

```json
{ "fee": { "amount": "2750.00", "currency": "LKR", "billing_cycle": "monthly", "effective_from": "2026-12-01" } }
```

- **Success `200`:** `{ "data": Class }`
- **Rules:** A fee change **creates a new fee version**; existing version amounts are never edited (only the previous version's `effective_to` is closed to the day before the new `effective_from`). `effective_from` must be after the current version's `effective_from`. Existing invoices keep their snapshots. `max_students` cannot be set below the active enrolment count (`409`). Audited.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 7.5 `GET /api/v1/public/classes?subject_code=&grade_code=&district=&q=&page=&per_page=`

- **Permission:** `public`, throttled.
- **Success `200`:** paginated `PublicClass[]`.
- **Rules:**
  - Only classes with `status = active`, `public_listing = true`, whose institute is `active`.
  - `district` matches the institute's district; `q` is a simple text match on class title/description.
  - **Neutral ordering:** `starts_on` ascending, then `title`, then `uuid`. No rankings, ratings, reviews, featured placement, or boost effect.
- **Errors:** `422` (bad filter), `429`.

---

## 8. Students and enrolments

Student shape (`Student`, institute view):

```json
{
  "uuid": "uuid",
  "full_name": "Sanduni Fernando",
  "grade": { "code": "G11", "name": "Grade 11" },
  "exam_target": { "code": "OL_2027", "name": "O/L 2027" },
  "medium": { "code": "sin", "name": "Sinhala" },
  "date_of_birth": null,
  "has_user_account": false,
  "active_guardian_count": 1,
  "created_at": "…"
}
```

### 8.1 `POST /api/v1/institutes/{instituteUuid}/students`

- **Permission:** same-institute staff/admin.
- **Request:**

```json
{
  "full_name": "Sanduni Fernando",
  "grade_code": "G11",
  "exam_target_code": "OL_2027",
  "medium_code": "sin",
  "date_of_birth": null
}
```

`exam_target_code` and `date_of_birth` optional.

- **Success `201`:** `{ "data": Student }`
- **Rules:** Creates an institute-managed `student_profiles` row (`managing_institute_id` and `created_by_user_id` from server; `user_id = NULL`). No NIC, ID documents or photos are accepted. Demo/seed data must be fictional. Audited (`student.created`, without the student's name or date of birth; §13).
- **Errors:** `401`, `403`, `404`, `422`.

### 8.2 `GET /api/v1/institutes/{instituteUuid}/students?q=&grade_code=&page=&per_page=`

- **Permission:** same-institute staff/admin. Teachers do **not** use this endpoint; they see only rosters of their assigned classes via §8.4.
- **Success `200`:** paginated `Student[]`.
- **Errors:** `401`, `403` (teacher-only role), `404`.

### 8.3 `POST /api/v1/classes/{classUuid}/enrollments`

- **Permission:** same-institute staff/admin of the class's institute.
- **Request:**

```json
{ "student_uuid": "uuid", "enrolled_on": "2026-11-01" }
```

- **Success `201`:**

```json
{
  "data": {
    "uuid": "uuid",
    "status": "active",
    "enrolled_on": "2026-11-01",
    "withdrawn_on": null,
    "student": { "uuid": "uuid", "full_name": "Sanduni Fernando" },
    "class": { "uuid": "uuid", "title": "…" }
  }
}
```

- **Rules:** Student must be managed by the same institute (`404` otherwise). Class must be `active`. In one transaction with the class row locked: reject if active enrolments ≥ `max_students` (`409`) or an active enrolment already exists for this student and class (`409`; also enforced by unique index). Audited.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 8.4 `GET /api/v1/classes/{classUuid}/enrollments?status=&page=&per_page=`

- **Permission:** assigned teacher, same-institute staff/admin, or `super_admin`. Guardians never see a class roster.
- **Success `200`:** paginated list of the enrolment shape in §8.3 (student `uuid` and `full_name` only).
- **Errors:** `401`, `404`.

---

## 9. Guardian links

### 9.1 `POST /api/v1/students/{studentUuid}/guardian-invitations`

- **Permission:** staff/admin of the student's managing institute.
- **Request:**

```json
{ "guardian_phone": "+94771234567", "relationship": "mother" }
```

`relationship`: `father|mother|guardian|uncle|aunt`.

- **Success `201`** (`Cache-Control: no-store`):

```json
{
  "data": {
    "uuid": "uuid",
    "student": { "uuid": "uuid", "full_name": "Sanduni Fernando" },
    "guardian_phone_masked": "+9477*****67",
    "relationship": "mother",
    "status": "pending",
    "expires_at": "2026-10-21T08:30:00Z",
    "code": "7KQ2-M9XD"
  },
  "meta": { "code_shown_once": true }
}
```

- **Rules:** `code` is returned **only in this response**, shown once in the protected institute UI, and is never logged or stored in plaintext (stored as `code_hash`). Single-use; proposed expiry 48 h (configurable). A pending invitation for the same student and phone → `409`. Audited without the code.
- **Code generation and format:**
  - 8 characters drawn from a cryptographically secure random source, using the unambiguous alphabet `23456789ABCDEFGHJKMNPQRSTVWXYZ` (no `0 O 1 I L U`).
  - The code is displayed and returned as `XXXX-XXXX`.
- **Dedicated key:**
  - `code_hash` = HMAC-SHA256 of the **normalised** code (§9.2), keyed by the dedicated secret `GUARDIAN_INVITE_HMAC_KEY`. That key is never committed, printed or logged.
  - If the key is not configured, invitation creation and acceptance fail closed with `503`. Other endpoints and Artisan commands are unaffected.
- **Lazy expiry:**
  - An invitation whose `expires_at` has passed is treated as **expired** on every read and action, whatever its stored `status`. A stored `pending` never overrides an expired timestamp.
  - No scheduler is required. The stored status is moved to `expired` when a write touches the row.
- **Errors:** `401`, `403`, `404`, `409`, `422`, `503` (invitation key not configured).

### 9.2 `POST /api/v1/guardian-invitations/accept`

- **Permission:** `authenticated`, throttled (proposed 5 attempts per user per 15 min).
- **Request:**

```json
{ "code": "7KQ2-M9XD" }
```

- **Success `201`:**

```json
{
  "data": {
    "student": { "uuid": "uuid", "full_name": "Sanduni Fernando" },
    "relationship": "mother",
    "linked_at": "2026-10-19T09:00:00Z"
  }
}
```

- **Rules:** In one transaction with the invitation row locked: invitation must be `pending` and unexpired; the caller's phone must have `phone_verified_at` set and equal `guardian_phone`. The invitation is marked `accepted` and an active `student_guardians` link is created (`linked_by_user_id` = inviter, `linked_at` = now, `invitation_id`). A `guardian_profiles` row is created if missing. No role is created; guardian access is derived from the active `student_guardians` link (§3). Audited (`guardian_invitation.accepted`, `guardian_link.created`).
- **Code normalisation:**
  - Before hashing, the submitted code is upper-cased and **spaces and hyphens are removed**.
  - Anything that is not then exactly 8 characters from the §9.1 alphabet gets the same generic `422`.
- **Phone matching:** the caller must be authenticated, with `phone_verified_at` set, and the account phone must equal `guardian_phone` exactly (E.164). A mismatch gets the generic `422`.
- **Initial primary guardian:**
  - In the same transaction, with the **student row locked**, the new link becomes primary (`is_primary = true`) only if the student has no active primary guardian; otherwise it is not primary.
  - When the new link becomes primary, `guardian_link.created` records the previous primary link ID (`null`) and the new primary link ID (§13).
  - There is no endpoint to choose or change the primary manually.
- **Errors:**
  - `401`.
  - `422` for an unknown, expired, used or malformed code, **or** a phone mismatch (single generic message).
  - `409` if an active link already exists.
  - `429`.
  - `503` if the invitation key is not configured.

### 9.3 `POST /api/v1/guardian-invitations/{invitationUuid}/revoke`

- **Permission:** the inviter (while still staff/admin of the same institute), or `super_admin`.
- **Request:** none.
- **Success `200`:** `{ "data": { "uuid": "uuid", "status": "revoked", "revoked_at": "…" } }`
- **Rules:** Only `pending` invitations can be revoked (`409` otherwise). An invitation past `expires_at` counts as expired (§9.1), so it also gets `409`. Records `revoked_by_user_id`. Audited.
- **Errors:** `401`, `403`, `404`, `409`.

### 9.4 `DELETE /api/v1/students/{studentUuid}/guardian-links/{guardianUuid}`

- **Permission:** admin of the student's managing institute, or `super_admin`. `guardianUuid` is the guardian's **user** UUID.
- **Success `204`.**
- **Rules:** Soft-revokes the active link: sets `revoked_at` and `revoked_by_user_id`. The row is **never** hard-deleted and revocation history is preserved. Access through §9.5, §10.5 and §11 is lost immediately. Audited (`guardian_link.revoked`).
- **Atomic primary promotion:**
  - The **student row is locked** first.
  - The link is revoked, which frees the primary slot.
  - If it was the primary, the **oldest remaining active link** (by `linked_at`, then by internal id) becomes primary, in the same transaction.
  - If no active link remains, the student has no primary guardian.
  - The `guardian_link.revoked` audit row records the old primary link ID and the new primary link ID (`null` if none) (§13).
  - Invoices already issued keep their `billed_guardian_id` (§11.2).
- **Errors:** `401`, `403`, `404` (no active link).

### 9.5 `GET /api/v1/parents/me/children`

- **Permission:** `authenticated`; returns only students with an **active** link to the caller.
- **Success `200`:** paginated

```json
{
  "data": [
    {
      "student": { "uuid": "uuid", "full_name": "Sanduni Fernando", "grade": { "code": "G11", "name": "Grade 11" } },
      "relationship": "mother",
      "linked_at": "…",
      "active_enrollments": [
        { "uuid": "uuid", "class": { "uuid": "uuid", "title": "…" }, "institute": { "name": "Lakmina Academy" } }
      ]
    }
  ],
  "meta": {}
}
```

- **Errors:** `401`.

---

## 10. Sessions and attendance

Session shape (`Session`):

```json
{
  "uuid": "uuid",
  "class_uuid": "uuid",
  "session_date": "2026-11-03",
  "start_time": "15:30",
  "end_time": "17:30",
  "status": "scheduled",
  "created_at": "…"
}
```

### 10.1 `POST /api/v1/classes/{classUuid}/sessions`

- **Permission:** assigned teacher, or manager of the class's institute.
- **Request:**

```json
{ "session_date": "2026-11-03", "start_time": "15:30", "end_time": "17:30" }
```

- **Success `201`:** `{ "data": Session }` (status `scheduled`).
- **Rules:** Times are `Asia/Colombo`; `end_time` > `start_time`. Duplicate (class, date, start_time) → `409`. Recurring schedule generation is not in sprint.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 10.2 `GET /api/v1/classes/{classUuid}/sessions?from=&to=&status=&page=&per_page=`

- **Permission:** assigned teacher, same-institute staff/admin, or `super_admin`. Guardians/students do not use this endpoint; their views are scoped through §10.5.
- **Success `200`:** paginated `Session[]`, ordered by `session_date`, `start_time`.
- **Errors:** `401`, `404`, `422`.

### 10.3 `POST /api/v1/sessions/{sessionUuid}/attendance`

- **Permission:** assigned teacher of the session's class, or same-institute staff/admin.
- **Request:**

```json
{
  "records": [
    { "enrollment_uuid": "uuid", "status": "present", "method": "manual", "notes": null }
  ]
}
```

`status`: `present|absent|late|excused`; `method`: `manual` only in sprint; 1–500 records; no duplicate `enrollment_uuid` in one request.

- **Success `200`:**

```json
{
  "data": [
    { "enrollment_uuid": "uuid", "status": "present", "method": "manual", "notes": null, "marked_at": "…", "marked_by": { "uuid": "uuid", "name": "…" } }
  ]
}
```

- **Rules:**
  - `marked_by_user_id` comes from the session auth, never the request.
  - Each enrolment must belong to the session's class and be active on `session_date` (`422` otherwise).
  - One row per (session, enrolment), enforced by a unique index; the request upserts in one transaction.
  - A change to an existing row is a **correction** and writes an audit event with old and new values.
  - Cancelled sessions → `409`. Proposed: sessions dated after today (`Asia/Colombo`) → `409`.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 10.4 `GET /api/v1/sessions/{sessionUuid}/attendance`

- **Permission:** assigned teacher, or same-institute staff/admin.
- **Success `200`:**

```json
{
  "data": [
    { "enrollment_uuid": "uuid", "student": { "uuid": "uuid", "full_name": "…" }, "attendance": { "status": "present", "method": "manual", "notes": null, "marked_at": "…" } }
  ],
  "meta": { "session": "Session", "marked_count": 41, "unmarked_count": 1 }
}
```

`attendance` is `null` for active enrolments not yet marked.

- **Errors:** `401`, `404`.

### 10.5 `GET /api/v1/parents/me/children/{studentUuid}/attendance?from=&to=&class_uuid=&page=&per_page=`

- **Permission:** `authenticated` with an **active** guardian link to `studentUuid`. No link → `404`.
- **Success `200`:** paginated

```json
{
  "data": [
    {
      "session": { "uuid": "uuid", "session_date": "2026-11-03", "start_time": "15:30", "end_time": "17:30" },
      "class": { "uuid": "uuid", "title": "…" },
      "status": "present",
      "marked_at": "…"
    }
  ],
  "meta": {}
}
```

- **Rules:** Only the linked child's own attendance; no other students' data. Defaults: last 30 days; max range 366 days (`422` beyond).
- **Errors:** `401`, `404`, `422`.

---

## 11. Invoices, demo payments and commission

### 11.1 Money definitions

| Field | Meaning |
|---|---|
| `invoice.subtotal` | Institute tuition due (fee version amount snapshot) |
| `invoice.platform_fee` | TuitionHub commission, added on top |
| `invoice.total` | `subtotal + platform_fee` — what the parent pays |
| `payment.amount` | `= invoice.total` |
| `payment.platform_fee` | `= commission` |
| `payment.net_to_institute` | `= subtotal` — amount **owed** to the institute, **not settled or paid out** |

**Commission resolution** (at invoice creation): active `class` rule → active `institute` rule → `platform` rule (seeded: flat `100.00` LKR). `flat` = fixed amount; `percentage` = `subtotal × percentage / 100`, rounded half-up to 2 decimals. **No universal 2% default.** The resolved rule and values are snapshotted on the invoice; payments and commission accruals copy the invoice snapshot. Later rule or fee changes never change existing rows.

Worked example: fee `2500.00` + platform fee `100.00` = total `2600.00`; owed to institute `2500.00`.

There are **no** settlement, payout, refund-processing or live-payment endpoints, and no payout/settlement dates anywhere in responses.

Invoice shape (`Invoice`):

```json
{
  "uuid": "uuid",
  "invoice_no": "INV-2026-11-00001",
  "status": "open",
  "billing_period": "2026-11",
  "issued_on": "2026-11-01",
  "due_on": "2026-11-10",
  "currency": "LKR",
  "subtotal": "2500.00",
  "platform_fee": "100.00",
  "total": "2600.00",
  "commission": { "rule_type": "flat", "flat_amount": "100.00", "percentage": null, "scope": "platform" },
  "items": [ { "description": "Tuition — November 2026", "type": "tuition", "amount": "2500.00" } ],
  "student": { "uuid": "uuid", "full_name": "Sanduni Fernando" },
  "class": { "uuid": "uuid", "title": "…" },
  "institute": { "uuid": "uuid", "name": "Lakmina Academy" },
  "enrollment_uuid": "uuid",
  "demo": true,
  "demo_label": "DEMO — no real money is collected",
  "created_at": "…"
}
```

Invoice statuses in sprint: `open|paid|cancelled`.

### 11.2 `POST /api/v1/enrollments/{enrollmentUuid}/invoices`

- **Permission:** billing user (§3) of the enrolment's institute. `super_admin` may not create invoices (`403`).
- **Request:**

```json
{ "billing_period": "2026-11", "issued_on": "2026-11-01", "due_on": "2026-11-10" }
```

- **Success `201`:** `{ "data": Invoice }`
- **Rules:** Enrolment must be active. The server selects the fee version effective on `issued_on` (`422` if none), resolves the commission rule, writes the invoice, one `tuition` item and all snapshots in one transaction, and sets `billed_guardian_id` to the student's primary active guardian (or `NULL` if none is linked yet). `billed_guardian_id` is an **immutable addressee snapshot**: the primary is chosen only by §9.2 and §9.4; later primary changes or revocations never alter an existing invoice; and it does not limit who may view or pay (§11.5, §11.6). `due_on` ≥ `issued_on`. One non-cancelled invoice per (enrolment, billing period) → else `409`. Amounts in the request are rejected. Audited.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 11.3 `GET /api/v1/institutes/{instituteUuid}/invoices?status=&billing_period=&class_uuid=&student_uuid=&page=&per_page=`

- **Permission:** billing user of that institute, or `super_admin` (read-only).
- **Success `200`:** paginated `Invoice[]` (without `items`).
- **Errors:** `401`, `403`, `404`.

### 11.4 `GET /api/v1/parents/me/invoices?student_uuid=&status=&page=&per_page=`

- **Permission:** `authenticated`; only invoices for students with an **active** link to the caller.
- **Success `200`:** paginated `Invoice[]` (without `items`).
- **Errors:** `401`.

### 11.5 `GET /api/v1/invoices/{invoiceUuid}`

- **Permission:**
  - **Any linked guardian:** any guardian with an **active** link to the invoice's student, regardless of `billed_guardian_id`, which is only the addressee (D-015).
  - The billing user of the invoice's institute.
  - `super_admin` (read-only).
  - A revoked link grants nothing.
- **Success `200`:** `{ "data": Invoice & { "payments": [Payment] } }`
- **Errors:** `401`, `404`.

### 11.6 `POST /api/v1/invoices/{invoiceUuid}/payment-attempts`

- **Permission:** any linked guardian (as §11.5): any guardian with an **active** link to the invoice's student, regardless of `billed_guardian_id`. `super_admin` and institute users may not create payment attempts (`403`).
- **Request:**

```json
{ "provider": "mock", "idempotency_key": "uuid" }
```

- **Success `201`** (new) or **`200`** (idempotent replay):

```json
{
  "data": {
    "uuid": "uuid",
    "payment_no": "PAY-2026-11-00001",
    "invoice_uuid": "uuid",
    "status": "pending",
    "provider": "mock",
    "currency": "LKR",
    "amount": "2600.00",
    "platform_fee": "100.00",
    "net_to_institute": "2500.00",
    "demo": true,
    "demo_label": "DEMO — no real money is collected",
    "mock_outcomes": ["succeeded", "failed", "cancelled"],
    "created_at": "…"
  }
}
```

- **Rules:**
  - `provider` must be `mock`. Amounts are calculated from the invoice snapshot; any client amount is rejected.
  - `payer_user_id` from session. A `payment_transactions` row with `gateway = "mock"` is written.
  - **Idempotency:** same `idempotency_key` + same invoice + same payer → returns the original attempt (`200`). Same key with a different invoice/payer → `409`.
  - Invoice must be `open` (`409` if paid/cancelled). Only one `pending` attempt per invoice at a time (`409`).
- **Errors:** `401`, `404`, `409`, `422`.

### 11.7 `POST /api/v1/demo/payment-attempts/{paymentUuid}/complete`

- **Availability:** registered **only** when an explicit server flag (proposed `DEMO_PAYMENTS_ENABLED=true`) is set **and** `APP_ENV` is not `production`. Otherwise the route does not exist (`404`).
- **Permission:** the attempt's payer, while still an active linked guardian of the invoice's student.
- **Request:**

```json
{ "outcome": "succeeded" }
```

`outcome`: `succeeded|failed|cancelled`.

- **Success `200`:**

```json
{
  "data": {
    "payment": { "uuid": "uuid", "status": "succeeded", "paid_at": "…", "amount": "2600.00", "demo": true },
    "invoice": { "uuid": "uuid", "status": "paid" }
  }
}
```

- **Rules:**
  - Only `pending` attempts transition. Repeating the same outcome on an already-completed attempt returns the same result (`200`); a different outcome → `409`.
  - On `succeeded`, in one transaction with the invoice and payment rows locked: payment → `succeeded` with `paid_at`; invoice → `paid`; exactly **one** `platform_commissions` accrual (`status = accrued`, `is_demo = true`, unique per payment) is created. A second success for an already-paid invoice → `409`.
  - **Completion guarantees** (demo only; no settlement or payout exists):
    - Completing `succeeded` is **atomic**. The invoice row and then the payment row are locked, and the invoice status is re-checked under the lock.
    - An invoice that is already `paid` (or `cancelled`) is **rejected** with `409`, and that attempt is not marked `succeeded`.
    - At most **one** payment per invoice can ever be `succeeded`, so an invoice can never be paid twice or accrue commission twice. The single accrual is also enforced by the unique `payment_id` on `platform_commissions`.
    - This holds even though several linked guardians may start attempts (§11.6: one `pending` attempt per invoice at a time).
  - No real money moves. Audited (`payment.succeeded|failed|cancelled`, `commission.accrued`).
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 11.8 `GET /api/v1/admin/commission-accruals?from=&to=&institute_uuid=&status=&page=&per_page=`

- **Permission:** `super_admin` only.
- **Success `200`:**

```json
{
  "data": [
    {
      "uuid": "uuid",
      "status": "accrued",
      "amount": "100.00",
      "currency": "LKR",
      "commission": { "rule_type": "flat", "flat_amount": "100.00", "percentage": null, "scope": "platform" },
      "payment": { "uuid": "uuid", "payment_no": "PAY-2026-11-00001", "amount": "2600.00", "net_to_institute": "2500.00" },
      "invoice_no": "INV-2026-11-00001",
      "institute": { "uuid": "uuid", "name": "Lakmina Academy" },
      "accrued_at": "…",
      "demo": true,
      "demo_label": "DEMO / TEST — accrued, not collected"
    }
  ],
  "meta": {
    "current_page": 1, "per_page": 25, "total": 1, "last_page": 1,
    "summary": {
      "demo_accrued_total": "100.00",
      "currency": "LKR",
      "note": "Demo accruals only. Not collected cash, revenue or profit. No payouts or settlements exist."
    }
  }
}
```

- **Rules:** `status` filter accepts `accrued` only in sprint. No payout/settlement dates or "collected" figures.
- **Errors:** `401`, `403`, `422`.

---

## 12. Boost purchases (demo record only)

> **Scope caveat.** These endpoints only record clearly labelled demo purchase records. They create **no** featured placement, paid ranking or catalogue-ordering effect in this sprint, because C1 defers those features to Month 3+. The public catalogue (§7.5) never reads `boost_purchases`. No payment is taken.

### 12.1 `GET /api/v1/boost-products`

- **Permission:** `public`.
- **Success `200`:** paginated

```json
{
  "data": [
    { "uuid": "uuid", "code": "DEMO_BOOST_BASIC", "name": "Demo boost", "description": "…", "price": "1500.00", "currency": "LKR", "demo": true }
  ],
  "meta": {}
}
```

Only `is_active = true` products.

### 12.2 `POST /api/v1/classes/{classUuid}/boost-purchases`

- **Permission:** admin of the class's owning institute.
- **Request:**

```json
{ "boost_product_uuid": "uuid", "idempotency_key": "uuid" }
```

- **Success `201`** (new) or **`200`** (idempotent replay):

```json
{
  "data": {
    "uuid": "uuid",
    "status": "demo_recorded",
    "class": { "uuid": "uuid", "title": "…" },
    "product": { "uuid": "uuid", "name": "Demo boost" },
    "price": "1500.00",
    "currency": "LKR",
    "demo": true,
    "demo_label": "DEMO — no payment taken, no placement effect",
    "created_at": "…"
  }
}
```

- **Rules:** Price is derived from the product and snapshotted; client prices are rejected. Product must be active. Same key + same class + same user → original record; same key otherwise → `409`. Audited.
- **Errors:** `401`, `403`, `404`, `409`, `422`.

### 12.3 `GET /api/v1/institutes/{instituteUuid}/boost-purchases?page=&per_page=`

- **Permission:** same-institute owner/admin, or `super_admin`.
- **Success `200`:** paginated list of the §12.2 shape.
- **Errors:** `401`, `403`, `404`.

---

## 13. Audit events

Written to `audit_logs` (immutable, no soft deletes) with actor, target, old/new values, IP and user agent. Never include OTP codes, invitation codes or session secrets.

- **Same transaction:** the audit row is written in the same database transaction as the business operation.
- **Snapshots** contain only allowlisted fields. Never included: OTP or invitation codes or their hashes, passwords, tokens, the student's name or date of birth, or **full phone numbers**. A masked phone (`+9477*****67`, as in §9.1) is allowed.
- **IP addresses** are stored raw in the sprint. They are readable only by direct database access, because no sprint endpoint exposes `audit_logs`. The retention period is **pending a pre-launch privacy review**.
- **Targets** are recorded as stable short aliases (e.g. `student_guardian`), never class names. Platform-level events may have no institute.
- **Primary-guardian changes** are recorded inside the triggering event, with no new event name and no personal data:
  - `guardian_link.created`, when the new link becomes the initial primary (§9.2).
  - `guardian_link.revoked`, when a revocation promotes another link (§9.4).
  - Both carry the old primary link ID and the new primary link ID (either may be `null`).

`institute.created`, `institute.updated`, `institute.status_changed`, `teacher.assigned`, `teacher_assignment.ended`, `role.granted`, `role.revoked`, `staff.added`, `staff.ended`, `student.created`, `class.created`, `class.updated`, `fee.version_created`, `enrollment.created`, `guardian_invitation.created`, `guardian_invitation.accepted`, `guardian_invitation.revoked`, `guardian_link.created`, `guardian_link.revoked`, `attendance.recorded`, `attendance.corrected`, `invoice.created`, `payment.attempt_created`, `payment.succeeded`, `payment.failed`, `payment.cancelled`, `commission.accrued`, `boost_purchase.recorded_demo`.

`role.granted`, `role.revoked`, `staff.added`, `staff.ended` and `teacher_assignment.ended` are written by server-side services. There is no staff-management or assignment-end endpoint in the sprint. The owner's automatic `institute_admin` grant writes `role.granted` in the same transaction as `institute.created`.

---

## 14. Out of scope for this sprint

No endpoints for: Study, meetups, map-based social discovery or chat (C7); exams, marks or MCQ; reviews, ratings, featured slots, paid or advanced ranking; settlements, payouts, refund processing or live payment gateways/webhooks; real SMS (optional stretch only); announcements and notifications; file/ID/NIC/selfie uploads; Flutter/mobile bearer tokens.

### 14.1 Gaps noted — not approved

- **Onboarding name capture.** `onboarding_required` can be `true`, but no listed endpoint sets `users.name`. A proposed `PATCH /api/v1/me` (`{ "name": "…" }`, `authenticated`) needs approval before it is added.
- **Commission rule management.** No endpoint manages `commission_rules`; rules are seeded/configured in sprint.
---

## 15. Endpoint index

| # | Method | Path | Permission |
|---|---|---|---|
| 4.1 | POST | `/api/v1/auth/otp/request` | public, throttled |
| 4.2 | POST | `/api/v1/auth/otp/verify` | public, throttled |
| 4.3 | POST | `/api/v1/auth/logout` | authenticated |
| 4.4 | GET | `/api/v1/auth/session` | authenticated |
| 5.1 | POST | `/api/v1/institutes` | authenticated, onboarding-eligible |
| 5.2 | GET | `/api/v1/institutes` | super_admin |
| 5.3 | GET | `/api/v1/me/institutes` | authenticated |
| 5.4 | GET | `/api/v1/institutes/{instituteUuid}` | same-institute staff/admin, super_admin |
| 5.5 | PATCH | `/api/v1/institutes/{instituteUuid}` | same-institute owner/admin, super_admin |
| 6.1 | POST | `/api/v1/me/teacher-profile` | authenticated (self) |
| 6.2 | POST | `/api/v1/institutes/{instituteUuid}/teacher-assignments` | same-institute owner/admin, super_admin |
| 6.3 | GET | `/api/v1/institutes/{instituteUuid}/teachers` | same-institute staff/admin, super_admin |
| 6.4 | GET | `/api/v1/me/teacher/classes` | authenticated teacher |
| 7.1 | POST | `/api/v1/institutes/{instituteUuid}/classes` | same-institute owner/admin |
| 7.2 | GET | `/api/v1/institutes/{instituteUuid}/classes` | same-institute staff/admin, super_admin |
| 7.3 | GET | `/api/v1/classes/{classUuid}` | public if active+public; else restricted |
| 7.4 | PATCH | `/api/v1/classes/{classUuid}` | owning-institute admin, super_admin |
| 7.5 | GET | `/api/v1/public/classes` | public |
| 8.1 | POST | `/api/v1/institutes/{instituteUuid}/students` | same-institute staff/admin |
| 8.2 | GET | `/api/v1/institutes/{instituteUuid}/students` | same-institute staff/admin |
| 8.3 | POST | `/api/v1/classes/{classUuid}/enrollments` | same-institute staff/admin |
| 8.4 | GET | `/api/v1/classes/{classUuid}/enrollments` | assigned teacher, same-institute staff/admin |
| 9.1 | POST | `/api/v1/students/{studentUuid}/guardian-invitations` | managing-institute staff/admin |
| 9.2 | POST | `/api/v1/guardian-invitations/accept` | authenticated, phone match |
| 9.3 | POST | `/api/v1/guardian-invitations/{invitationUuid}/revoke` | inviter (same institute), super_admin |
| 9.4 | DELETE | `/api/v1/students/{studentUuid}/guardian-links/{guardianUuid}` | managing-institute admin, super_admin |
| 9.5 | GET | `/api/v1/parents/me/children` | authenticated (active links) |
| 10.1 | POST | `/api/v1/classes/{classUuid}/sessions` | assigned teacher, institute manager |
| 10.2 | GET | `/api/v1/classes/{classUuid}/sessions` | assigned teacher, same-institute staff/admin |
| 10.3 | POST | `/api/v1/sessions/{sessionUuid}/attendance` | assigned teacher, same-institute staff/admin |
| 10.4 | GET | `/api/v1/sessions/{sessionUuid}/attendance` | assigned teacher, same-institute staff/admin |
| 10.5 | GET | `/api/v1/parents/me/children/{studentUuid}/attendance` | active guardian link |
| 11.2 | POST | `/api/v1/enrollments/{enrollmentUuid}/invoices` | institute billing user |
| 11.3 | GET | `/api/v1/institutes/{instituteUuid}/invoices` | institute billing user, super_admin (read-only) |
| 11.4 | GET | `/api/v1/parents/me/invoices` | authenticated (active links) |
| 11.5 | GET | `/api/v1/invoices/{invoiceUuid}` | any active linked guardian, billing user, super_admin (read-only) |
| 11.6 | POST | `/api/v1/invoices/{invoiceUuid}/payment-attempts` | any active linked guardian |
| 11.7 | POST | `/api/v1/demo/payment-attempts/{paymentUuid}/complete` | payer; demo flag + non-production only |
| 11.8 | GET | `/api/v1/admin/commission-accruals` | super_admin |
| 12.1 | GET | `/api/v1/boost-products` | public |
| 12.2 | POST | `/api/v1/classes/{classUuid}/boost-purchases` | owning-institute admin |
| 12.3 | GET | `/api/v1/institutes/{instituteUuid}/boost-purchases` | same-institute owner/admin, super_admin |

---

## 16. Changelog

| Version | Date | Change |
|---|---|---|
| 0.1 draft | 2026-10-19 | Initial sprint contract from approved decisions C1–C7 and OTP decision. Roles pending C8. |
| 0.2 draft | 2026-10-28 | C8 approved: hybrid role model; derived teacher/guardian access; super_admin billing read-only. §3: teacher class access needs the class assignment; ended/revoked relationships grant nothing. §4.2/§4.4: `roles` holds stored roles only (`institute_uuid` null for `super_admin`); added `teaching_at` and `active_child_link_count` as display hints. §5.3: includes teaching institutes; `my_roles` renamed `my_access`. §6.2/§9.2: no teacher/guardian role rows. Index §5.4/§7.2: "member" replaced by "staff/admin" to match the endpoint sections. |
| 0.3 draft | 2026-10-28 | **D-015 approved** (Ranithu Shenal, 28/10/2026). §2.3: dedicated `GUARDIAN_INVITE_HMAC_KEY` for `code_hash`; at most one active primary link per student. §8.1: `student.created` audited. §9.1: code generation (8 characters, unambiguous alphabet, secure random source, `XXXX-XXXX`), dedicated HMAC key (fail closed, `503`), lazy expiry checked on every read. §9.2: normalisation (upper-case, remove spaces and hyphens), authenticated phone match, initial primary under a student-row lock; no manual primary selection. §9.3: an expired invitation cannot be revoked (`409`). §9.4: atomic primary promotion after revocation, with old/new link IDs audited. §11.2: `billed_guardian_id` is an immutable addressee snapshot. §11.5/§11.6: any actively linked guardian may view an invoice and start a demo payment, regardless of `billed_guardian_id` (§15 labels updated). §11.7: atomic demo completion; already-paid invoices rejected; no double success or double accrual. §13: events `role.granted`, `role.revoked`, `staff.added`, `staff.ended`, `teacher_assignment.ended`, `student.created`; primary changes recorded in `guardian_link.created`/`guardian_link.revoked`; masked phones only; raw IP with retention pending pre-launch privacy review; stable target aliases. |
