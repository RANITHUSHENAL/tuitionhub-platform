# TuitionHub — Four-Week Sprint Schema Draft

**Status:** Migration-ready proposal for review; do not run migrations until role semantics (audit C8) and this draft are approved.  
**Canonical source:** `docs/reference/TuitionHub_ER_Schema.md`. The canonical source defines names, high-level columns and relationships, but it does **not** specify a nullability/default for every field. This draft fills those gaps explicitly as sprint design choices.  
**Database:** MySQL 8.0+ / InnoDB; `utf8mb4`; use `DECIMAL(15,2)` for all LKR money; store timestamps in UTC and interpret class session date/time in `Asia/Colombo`.  
**Primary keys:** `BIGINT UNSIGNED AUTO_INCREMENT`; public-facing UUIDs are `CHAR(36)` with unique indexes.  
**Convention:** Unless stated otherwise, `created_at` and `updated_at` are `TIMESTAMP NULL DEFAULT NULL`. `deleted_at` is `TIMESTAMP NULL DEFAULT NULL` only on soft-deletable profile/business tables. Do not add soft deletes to immutable financial/audit history.

## 1. Scope and deliberate adaptations

Tables in this draft are limited to the four-week operational loop, local/dev OTP, invoice/commission demo, a basic public class catalogue and purchase-record-only boost demo. The schema intentionally omits Flutter-specific devices, bank accounts, settlements/payouts, refunds processing, exams, Study/chat, reviews, full listing/featured placement/ranking, notifications infrastructure and unrelated content tables.

Source-defined names used where practical: `users`, `roles`, `user_roles`, `otp_verifications`, `institutes`, `institute_settings`, `teacher_profiles`, `teacher_institute`, `student_profiles`, `guardian_profiles`, `student_guardians`, `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi`, `classes`, `class_schedules`, `class_sessions`, `class_enrollments`, `class_fee_versions`, `attendances`, `invoices`, `invoice_items`, `payments`, `payment_transactions`, `platform_commissions`, `audit_logs`, `webhook_logs`.

Sprint-specific tables/columns added to support approved decisions: `guardian_link_invitations`, `commission_rules`, `boost_products`, `boost_purchases`, `uuid` columns on profiles/resources used in public/API routes, guardian link history columns, commission calculation snapshots, and optional student account linkage.

### Important exceptions

- **C8 is not in the explicit C1–C7 approvals.** This draft proposes that `user_roles` is authoritative for access control and permits multiple roles per user. Keep `users.type` as a broad onboarding/profile discriminator only; never use it as the sole permission check. Confirm before implementing role migrations.
- `student_profiles.user_id` is nullable because an institute must be able to register an institute-managed student who does not yet have a student login. This is a deliberate adaptation of the source ER design.
- Do not collect/store student NIC, guardian ID scans, selfie verification, bank-account data or real child records in the sprint.
- The source `institute_settings.settlement_frequency`, `settlement_lag_days` and fixed `platform_commission_pct=2.0` are omitted: payouts are deferred and C3 rejects a universal 2% rule.
- `settlements`, `settlement_items` and bank accounts are omitted because real payouts are disabled.
- Boost purchases are demo records only. They do not activate paid placement or a ranking algorithm during this sprint because C1 defers full featured slots/ranking.

## 2. Field type conventions

- `varchar(n)` maps to Laravel `string(..., n)`; `text` to `text`; `json` to `json`; `boolean` to `boolean`; `date` to `date`; `time` to `time`; `timestamp` to `timestamp`; `decimal(15,2)` to `decimal(..., 15, 2)`; FK IDs to `unsignedBigInteger` with matching FK constraint.
- Statuses are `VARCHAR(32)` (not native MySQL ENUM). Enforce allowed states with Form Requests/domain services and tests.
- For amounts, API strings should use two decimals. Store currency as `CHAR(3) DEFAULT 'LKR'`.
- `uuid` values are generated server-side. Never accept them as authority over ownership or relationships.

## 3. Table definitions

### 3.1 `users` (canonical, adapted for phone-first OTP)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | server-generated; unique |
| `name` | VARCHAR(255) | No | none |
| `email` | VARCHAR(255) | Yes | NULL; unique when present (MySQL permits multiple NULL values) |
| `phone` | VARCHAR(20) | No | canonical E.164; unique |
| `password` | VARCHAR(255) | Yes | NULL; OTP-first, password login not in sprint |
| `type` | VARCHAR(32) | No | `person`; onboarding/profile discriminator only, pending C8 confirmation |
| `status` | VARCHAR(32) | No | `active`; allowed: active, suspended, pending |
| `email_verified_at` | TIMESTAMP | Yes | NULL |
| `phone_verified_at` | TIMESTAMP | Yes | NULL until OTP succeeds |
| `avatar_path` | VARCHAR(2048) | Yes | NULL |
| `remember_token` | VARCHAR(100) | Yes | NULL; Laravel compatibility |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Indexes:** PK `id`; unique `uuid`; unique `phone`; unique `email`; index `(type,status)`; index `status`. Keep phone unique even on soft-deleted rows in the sprint to avoid phone reuse ambiguity. If deleted-account phone reuse becomes a requirement, design it explicitly rather than silently changing this index.

**Foreign keys:** none.

### 3.2 `roles` (canonical, seeded role names)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `name` | VARCHAR(100) | No | unique; seed `super_admin`, `institute_admin`, `institute_staff`, `teacher`, `student`, `guardian` |
| `guard_name` | VARCHAR(100) | No | `web` |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** PK `id`; unique `(name, guard_name)`. Seed via migration/seeders, not frontend input.

### 3.3 `user_roles` (canonical pivot, scoped role assignments)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK; sprint addition to ease audit/history |
| `user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `role_id` | BIGINT UNSIGNED | No | FK → `roles.id` ON DELETE RESTRICT |
| `institute_id` | BIGINT UNSIGNED | Yes | FK → `institutes.id` ON DELETE RESTRICT; NULL for platform/global roles |
| `scope_key` | VARCHAR(64) | No | `global`; global or canonical `institute:{numeric-id}` scope, maintained by server |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `(user_id, role_id, scope_key)`; index `(institute_id, role_id)`; index `user_id`. `scope_key` is added because MySQL unique indexes permit multiple NULLs and would not reliably prevent duplicate global role rows if the unique key used nullable `institute_id` directly. Keep `scope_key` derivation server-side.

**C8 rule proposal:** this pivot is the permission source. A role assignment is institute-scoped unless `scope_key='global'` and `institute_id IS NULL`.

### 3.4 `otp_verifications` (canonical, security fields adapted)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique challenge ID returned by API |
| `phone` | VARCHAR(20) | No | normalised E.164; index |
| `code_hash` | VARCHAR(255) | No | hashed OTP only; never plaintext |
| `purpose` | VARCHAR(32) | No | `login` during sprint |
| `expires_at` | TIMESTAMP | No | generated server-side |
| `consumed_at` | TIMESTAMP | Yes | NULL until successful single use |
| `attempts` | UNSIGNED TINYINT | No | `0`; cap configured server-side |
| `request_ip_hash` | CHAR(64) | Yes | NULL; optional privacy-conscious rate/audit correlation |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; index `(phone,purpose,consumed_at)`; index `expires_at`. Expired challenges should be purged by a scheduled task later; no purge job is required for Day 1.

### 3.5 `teacher_profiles` (canonical + UUID)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | server-generated; unique |
| `user_id` | BIGINT UNSIGNED | No | unique FK → `users.id` ON DELETE RESTRICT |
| `display_name` | VARCHAR(255) | No | none |
| `bio` | TEXT | Yes | NULL |
| `years_experience` | UNSIGNED SMALLINT | No | `0` |
| `headline` | VARCHAR(255) | Yes | NULL |
| `is_independent` | BOOLEAN | No | `false` |
| `public_visibility` | VARCHAR(32) | No | `hidden`; allowed: full, institute_attributed, hidden |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Indexes:** PK; unique `uuid`; unique `user_id`; index `(is_independent,public_visibility)`.

### 3.6 `student_profiles` (canonical + UUID; account may be absent)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | server-generated; unique |
| `user_id` | BIGINT UNSIGNED | Yes | NULL until a student account is linked; FK → `users.id` ON DELETE RESTRICT; unique when non-NULL |
| `date_of_birth` | DATE | Yes | NULL; collect only when needed for safeguarding/grade workflows |
| `gender` | VARCHAR(32) | Yes | NULL; do not require for sprint |
| `current_grade_id` | BIGINT UNSIGNED | Yes | NULL; FK → `grades.id` ON DELETE RESTRICT |
| `exam_target_id` | BIGINT UNSIGNED | Yes | NULL; FK → `exam_targets.id` ON DELETE RESTRICT |
| `medium` | VARCHAR(16) | Yes | NULL; allowed codes sin, tam, eng |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Omitted from canonical source for this sprint:** NIC and `school_id`; no ID collection and no school directory is needed for the end-to-end demo. **Indexes:** unique `uuid`; unique nullable `user_id`; index `(current_grade_id,exam_target_id)`.

### 3.7 `guardian_profiles` (canonical + UUID)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | server-generated; unique |
| `user_id` | BIGINT UNSIGNED | No | unique FK → `users.id` ON DELETE RESTRICT |
| `occupation` | VARCHAR(255) | Yes | NULL |
| `preferred_contact` | VARCHAR(32) | No | `app`; allowed sms, whatsapp, app, email |
| `has_smartphone` | BOOLEAN | No | `true` |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Indexes:** unique `uuid`; unique `user_id`.

### 3.8 `institutes` (canonical)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `owner_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `name` | VARCHAR(255) | No | none |
| `slug` | VARCHAR(180) | No | unique, lower-case URL-safe |
| `business_reg_no` | VARCHAR(100) | Yes | NULL; not collected for demo |
| `logo_path` | VARCHAR(2048) | Yes | NULL |
| `description` | TEXT | Yes | NULL |
| `status` | VARCHAR(32) | No | `active`; allowed draft, active, suspended |
| `district` | VARCHAR(100) | No | none; indexed for basic catalogue filter |
| `city` | VARCHAR(100) | Yes | NULL |
| `lat` | DECIMAL(10,7) | Yes | NULL; no map required in sprint |
| `lng` | DECIMAL(10,7) | Yes | NULL; no map required in sprint |
| `contact_phone` | VARCHAR(20) | Yes | NULL |
| `contact_email` | VARCHAR(255) | Yes | NULL |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Omitted from canonical source:** `vat_no` not needed for fictional demo. **Indexes:** unique `uuid`; unique `slug`; `(owner_user_id,status)`; `(district,status)`; `status`.

### 3.9 `institute_settings` (canonical, settlement/2% fields removed)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `institute_id` | BIGINT UNSIGNED | No | unique FK → `institutes.id` ON DELETE CASCADE |
| `parent_pays_commission` | BOOLEAN | No | `true`; approved C2/C3 parent-pays-on-top model |
| `public_listing_enabled` | BOOLEAN | No | `true` for demo; publication still requires class `public_listing=true` and active institute |
| `teacher_names_in_public_listings` | BOOLEAN | No | `true` |
| `branding` | JSON | Yes | NULL; not used for white-label in sprint |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Intentionally excluded:** `settlement_frequency`, `settlement_lag_days`, and hard-coded `platform_commission_pct=2.0` because they conflict with approved decisions. Index unique `institute_id`.

### 3.10 `teacher_institute` (canonical assignment history)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK; sprint addition to give assignments public UUIDs if needed |
| `uuid` | CHAR(36) | No | unique |
| `teacher_id` | BIGINT UNSIGNED | No | FK → `teacher_profiles.id` ON DELETE RESTRICT |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `joined_on` | DATE | No | current date set by server when omitted |
| `left_on` | DATE | Yes | NULL while active |
| `status` | VARCHAR(32) | No | `active`; allowed active, former |
| `revenue_share_pct` | DECIMAL(5,2) | Yes | NULL; not used during sprint |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; index `(institute_id,status)`; index `(teacher_id,status)`; unique active assignment should be enforced in application transaction (MySQL partial unique indexes are not available like PostgreSQL's). Never hard-delete assignment history.

### 3.11 Lookup tables

Each lookup table uses `id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL PK`, its `code VARCHAR(40) NOT NULL UNIQUE`, `name VARCHAR(150) NOT NULL`, and `created_at`/`updated_at TIMESTAMP NULL DEFAULT NULL`, except where additional columns are listed below.

**`subjects`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(40), not null, unique.
- `name` VARCHAR(150), not null.
- `name_si` VARCHAR(150), nullable, default NULL.
- `name_ta` VARCHAR(150), nullable, default NULL.
- `category` VARCHAR(32), not null, default `core`.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

**`grades`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(40), not null, unique.
- `name` VARCHAR(150), not null.
- `level` VARCHAR(40), not null; values such as primary, junior_secondary, ol, al.
- `sort_order` SMALLINT UNSIGNED, not null, default `0`.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

**`streams`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(40), not null, unique.
- `name` VARCHAR(150), not null.
- `level` VARCHAR(40), not null, default `al`.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

**`exam_targets`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(40), not null, unique.
- `name` VARCHAR(150), not null.
- `exam` VARCHAR(16), not null; values `ol` or `al`.
- `year` SMALLINT UNSIGNED, not null.
- `approximate_exam_date` DATE, nullable, default NULL.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

**`mediums`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(16), not null, unique (`sin`, `tam`, `eng`).
- `name` VARCHAR(100), not null.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

**`syllabi`**
- `id` BIGINT UNSIGNED, PK, auto-increment, not null.
- `code` VARCHAR(40), not null, unique (`local`, `edexcel`, `cambridge`).
- `name` VARCHAR(120), not null.
- `created_at`, `updated_at` TIMESTAMP nullable, default NULL.

Seed lookup values through Laravel seeders. Do not let client requests create new taxonomy values during the sprint.

### 3.12 `classes` (canonical, no hall table in sprint)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `teacher_id` | BIGINT UNSIGNED | No | FK → `teacher_profiles.id` ON DELETE RESTRICT |
| `subject_id` | BIGINT UNSIGNED | No | FK → `subjects.id` ON DELETE RESTRICT |
| `grade_id` | BIGINT UNSIGNED | No | FK → `grades.id` ON DELETE RESTRICT |
| `stream_id` | BIGINT UNSIGNED | Yes | NULL; FK → `streams.id` ON DELETE RESTRICT |
| `exam_target_id` | BIGINT UNSIGNED | No | FK → `exam_targets.id` ON DELETE RESTRICT |
| `medium_id` | BIGINT UNSIGNED | No | FK → `mediums.id` ON DELETE RESTRICT |
| `syllabus_id` | BIGINT UNSIGNED | No | FK → `syllabi.id` ON DELETE RESTRICT |
| `title` | VARCHAR(255) | No | none |
| `slug` | VARCHAR(200) | Yes | NULL; class UUID is the public route identifier |
| `description` | TEXT | Yes | NULL |
| `mode` | VARCHAR(32) | No | `in_person`; allowed in_person, online, hybrid |
| `max_students` | UNSIGNED INT | Yes | NULL means no configured cap |
| `starts_on` | DATE | No | none |
| `ends_on` | DATE | Yes | NULL for ongoing class |
| `status` | VARCHAR(32) | No | `draft`; allowed draft, active, archived |
| `public_listing` | BOOLEAN | No | `false`; opt-in required before public display |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |
| `deleted_at` | TIMESTAMP | Yes | NULL; soft delete |

**Foreign keys:** as noted for institute, teacher and lookups. **Omitted source field:** `hall_id` because halls/building locations are out of scope. **Indexes:** unique `uuid`; `(institute_id,status)`; `(teacher_id,status)`; `(public_listing,status,subject_id,grade_id,exam_target_id)` for catalogue queries; index `title` only if search implementation needs it. Add district filtering through the joined institute index `(district,status)`.

### 3.13 `class_schedules` (canonical)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `class_id` | BIGINT UNSIGNED | No | FK → `classes.id` ON DELETE CASCADE |
| `day_of_week` | VARCHAR(16) | No | lower-case day name |
| `start_time` | TIME | No | none |
| `end_time` | TIME | No | must be later than start time |
| `effective_from` | DATE | No | none |
| `effective_to` | DATE | Yes | NULL while current |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** `(class_id,effective_from,effective_to)`.

### 3.14 `class_sessions` (canonical, manual creation; no cron required)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `class_id` | BIGINT UNSIGNED | No | FK → `classes.id` ON DELETE RESTRICT |
| `substitute_teacher_id` | BIGINT UNSIGNED | Yes | NULL; FK → `teacher_profiles.id` ON DELETE RESTRICT |
| `session_date` | DATE | No | date in Asia/Colombo |
| `start_time` | TIME | No | Asia/Colombo local time |
| `end_time` | TIME | No | must be later than start time |
| `status` | VARCHAR(32) | No | `scheduled`; allowed scheduled, in_progress, conducted, cancelled, rescheduled |
| `cancellation_reason` | TEXT | Yes | NULL |
| `online_meeting_url` | VARCHAR(2048) | Yes | NULL; online meeting integration out of scope |
| `online_meeting_provider` | VARCHAR(32) | Yes | NULL |
| `created_by_user_id` | BIGINT UNSIGNED | No | sprint addition; FK → `users.id` ON DELETE RESTRICT |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Omitted source field:** `hall_id` because `halls` are out of scope. **Indexes:** unique `uuid`; `(class_id,session_date)`; `(session_date,status)`.

### 3.15 `class_enrollments` (canonical)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `class_id` | BIGINT UNSIGNED | No | FK → `classes.id` ON DELETE RESTRICT |
| `student_id` | BIGINT UNSIGNED | No | FK → `student_profiles.id` ON DELETE RESTRICT |
| `enrolled_on` | DATE | No | server current date when omitted |
| `withdrawn_on` | DATE | Yes | NULL |
| `status` | VARCHAR(32) | No | `active`; allowed active, paused, withdrawn, completed |
| `student_card_no` | VARCHAR(80) | Yes | NULL; not used in sprint |
| `qr_token` | VARCHAR(255) | Yes | NULL; QR attendance deferred; unique when present |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; unique `(class_id,student_id)` in application for active enrolments; `(student_id,status)`; `(class_id,status)`. MySQL does not support the source schema's PostgreSQL-style partial unique index as written; implement safe application transaction/locking and optionally add a generated active-key column if strict database enforcement is required.

### 3.16 `class_fee_versions` (canonical, immutable history)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `class_id` | BIGINT UNSIGNED | No | FK → `classes.id` ON DELETE RESTRICT |
| `amount` | DECIMAL(15,2) | No | `>= 0`; institute tuition fee, excluding platform fee |
| `currency` | CHAR(3) | No | `LKR` |
| `billing_cycle` | VARCHAR(32) | No | `monthly`; allowed monthly, term, one_time |
| `effective_from` | DATE | No | none |
| `effective_to` | DATE | Yes | NULL while current |
| `created_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `created_at` | TIMESTAMP | No | current timestamp; immutable row |

**Indexes:** `(class_id,effective_from,effective_to)`. Never edit a fee version already referenced by an invoice. Changing fee creates a new version.

### 3.17 `student_guardians` (canonical pivot + link history)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `student_id` | BIGINT UNSIGNED | No | FK → `student_profiles.id` ON DELETE RESTRICT |
| `guardian_id` | BIGINT UNSIGNED | No | FK → `guardian_profiles.id` ON DELETE RESTRICT |
| `relationship` | VARCHAR(32) | No | one of father, mother, guardian, other |
| `is_primary` | BOOLEAN | No | `false` |
| `receives_attendance_sms` | BOOLEAN | No | `false`; no SMS sent in baseline |
| `receives_payment_sms` | BOOLEAN | No | `false`; no SMS sent in baseline |
| `receives_marks_notifications` | BOOLEAN | No | `false`; marks not in sprint |
| `status` | VARCHAR(16) | No | `active`; allowed active, revoked |
| `linked_at` | TIMESTAMP | No | set on accepted invitation |
| `linked_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `revoked_at` | TIMESTAMP | Yes | NULL |
| `revoked_by_user_id` | BIGINT UNSIGNED | Yes | NULL; FK → `users.id` ON DELETE RESTRICT |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** `(student_id,status)`; `(guardian_id,status)`; unique `(student_id,guardian_id)` so an existing pair is reactivated rather than duplicate rows on relink. Link/revoke actions must be audited. Active link is required for every parent-child data request.

### 3.18 `guardian_link_invitations` (sprint addition)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `student_id` | BIGINT UNSIGNED | No | FK → `student_profiles.id` ON DELETE RESTRICT |
| `invited_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `guardian_phone` | VARCHAR(20) | No | normalised E.164 |
| `relationship` | VARCHAR(32) | No | father, mother, guardian, other |
| `code_hash` | VARCHAR(255) | No | hash of random one-time code; never plaintext |
| `expires_at` | TIMESTAMP | No | expiry of invitation, not child lockout |
| `accepted_at` | TIMESTAMP | Yes | NULL |
| `accepted_by_user_id` | BIGINT UNSIGNED | Yes | NULL; FK → `users.id` ON DELETE RESTRICT |
| `revoked_at` | TIMESTAMP | Yes | NULL |
| `status` | VARCHAR(16) | No | `pending`; allowed pending, accepted, revoked, expired |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; index `(guardian_phone,status)`; index `(student_id,status)`; index `expires_at`. Only hashed code stored. Accept must be transactional and single-use; account phone must match `guardian_phone`.

### 3.19 `attendances` (canonical)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `session_id` | BIGINT UNSIGNED | No | FK → `class_sessions.id` ON DELETE RESTRICT |
| `enrollment_id` | BIGINT UNSIGNED | No | FK → `class_enrollments.id` ON DELETE RESTRICT |
| `marked_at` | TIMESTAMP | No | server time |
| `method` | VARCHAR(16) | No | `manual`; allowed qr, card, manual, geo; only manual in sprint |
| `status` | VARCHAR(16) | No | `present`; allowed present, absent, late, excused |
| `marked_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT; never client supplied |
| `notes` | TEXT | Yes | NULL |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Omitted source field:** `device_id` because `attendance_devices` is excluded. **Indexes:** unique `(session_id,enrollment_id)`; index `(enrollment_id,marked_at)`; index `(session_id,status)`. Use a transaction/upsert for corrections and write `audit_logs` for changes.

### 3.20 `commission_rules` (sprint addition; canonical schema has no fixed-fee rule table)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `class_id` | BIGINT UNSIGNED | Yes | NULL for institute rule; FK → `classes.id` ON DELETE RESTRICT |
| `calculation_type` | VARCHAR(16) | No | `flat`; allowed flat, percentage |
| `flat_amount` | DECIMAL(15,2) | Yes | NULL unless calculation_type=flat; default rule is Rs 100.00 |
| `rate_pct` | DECIMAL(7,4) | Yes | NULL unless calculation_type=percentage |
| `currency` | CHAR(3) | No | `LKR` |
| `is_active` | BOOLEAN | No | `true` |
| `effective_from` | DATE | No | current date when activated |
| `effective_to` | DATE | Yes | NULL while current |
| `created_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; `(institute_id,is_active,effective_from)`; `(class_id,is_active,effective_from)`. Rule precedence: active class-specific rule, then active institute rule, then documented Rs 100 default. Validate rule so exactly the matching amount/rate is set and percentage is within an agreed bound. Snapshot the applied result onto invoice/payment/commission records; don't recompute historical invoices.

### 3.21 `invoices` (canonical, no settlement dependency)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `invoice_no` | VARCHAR(50) | No | unique human-readable invoice ID generated server-side |
| `enrollment_id` | BIGINT UNSIGNED | No | FK → `class_enrollments.id` ON DELETE RESTRICT |
| `student_id` | BIGINT UNSIGNED | No | FK → `student_profiles.id` ON DELETE RESTRICT |
| `billed_guardian_id` | BIGINT UNSIGNED | No | FK → `guardian_profiles.id` ON DELETE RESTRICT |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `fee_version_id` | BIGINT UNSIGNED | No | FK → `class_fee_versions.id` ON DELETE RESTRICT |
| `billing_period` | VARCHAR(32) | No | e.g. `2026-11` or `one_time:enrollment_uuid` |
| `issued_on` | DATE | No | server date |
| `due_on` | DATE | No | decided by invoice policy; no promise of payout/settlement timing |
| `subtotal` | DECIMAL(15,2) | No | institute tuition amount (e.g. 2500.00) |
| `platform_fee` | DECIMAL(15,2) | No | parent-pays-on-top commission amount (e.g. 100.00) |
| `total` | DECIMAL(15,2) | No | subtotal + platform_fee |
| `currency` | CHAR(3) | No | `LKR` |
| `status` | VARCHAR(32) | No | `open`; allowed draft, open, paid, partially_paid, cancelled, overdue |
| `notes` | TEXT | Yes | NULL |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; unique `invoice_no`; `(status,due_on)`; `(enrollment_id,billing_period)`; `(billed_guardian_id,status)`; `(institute_id,status)`. Add a unique invoice-period constraint if the agreed billing policy guarantees one invoice per enrollment/period; define the uniqueness key precisely before adding it.

### 3.22 `invoice_items` (canonical, platform fee line type added)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `invoice_id` | BIGINT UNSIGNED | No | FK → `invoices.id` ON DELETE CASCADE |
| `description` | VARCHAR(255) | No | none |
| `amount` | DECIMAL(15,2) | No | non-negative; server-calculated |
| `type` | VARCHAR(32) | No | allowed tuition, platform_fee, exam, material, late_fee, adjustment; default `tuition` |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** `(invoice_id,type)`. Invoice items are immutable after payment is initiated; corrections require a new adjustment/cancel-and-reissue workflow.

### 3.23 `payments` (canonical; demo provider added)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `payment_no` | VARCHAR(50) | No | unique readable payment reference |
| `invoice_id` | BIGINT UNSIGNED | No | FK → `invoices.id` ON DELETE RESTRICT |
| `payer_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT; server derived from session |
| `amount` | DECIMAL(15,2) | No | exact parent total, e.g. 2600.00 |
| `platform_fee` | DECIMAL(15,2) | No | commission snapshot, e.g. 100.00 |
| `net_to_institute` | DECIMAL(15,2) | No | institute amount owed (not proof of payout), e.g. 2500.00 |
| `currency` | CHAR(3) | No | `LKR` |
| `status` | VARCHAR(32) | No | `pending`; allowed pending, processing, succeeded, failed, refunded, partially_refunded, cancelled |
| `method` | VARCHAR(32) | No | `mock` for baseline; future values can include card, wallet, bank, cash |
| `paid_at` | TIMESTAMP | Yes | NULL until mock/gateway reports success via allowed provider path |
| `idempotency_key` | VARCHAR(100) | No | unique; generated by client, validated by server |
| `failure_reason` | TEXT | Yes | NULL |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; unique `payment_no`; unique `idempotency_key`; `(status,created_at)`; `invoice_id`; `(payer_user_id,created_at)`. Amount, platform fee and institute amount must not change after creation. Only system-controlled status fields and approved failure metadata may transition; audit each transition.

### 3.24 `payment_transactions` (canonical, gateway/mock attempt metadata)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `payment_id` | BIGINT UNSIGNED | No | FK → `payments.id` ON DELETE RESTRICT |
| `gateway` | VARCHAR(32) | No | `mock`; future `payhere` |
| `gateway_txn_id` | VARCHAR(150) | Yes | NULL until provider reference exists |
| `gateway_status` | VARCHAR(80) | Yes | NULL until provider result |
| `gateway_request` | JSON | Yes | NULL; redact secrets and PII before storing |
| `gateway_response` | JSON | Yes | NULL; redact secrets and PII |
| `gateway_webhook` | JSON | Yes | NULL; stretch PayHere only, secure retention |
| `gateway_fee` | DECIMAL(15,2) | No | `0.00` for mock provider; never confuse with platform fee |
| `created_at` | TIMESTAMP | No | current timestamp; append-only event/attempt record |

**Indexes:** `payment_id`; `(gateway,gateway_txn_id)` (unique only if provider guarantees uniqueness and NULL behaviour is acceptable). Do not store secrets, full card data or OTPs in JSON payloads.

### 3.25 `platform_commissions` (canonical + flat-fee snapshot; no settlement FK)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `payment_id` | BIGINT UNSIGNED | No | unique FK → `payments.id` ON DELETE RESTRICT; at most one commission accrual per succeeded payment for this sprint |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `amount` | DECIMAL(15,2) | No | commission accrued, snapshot from invoice/payment |
| `commission_type_snapshot` | VARCHAR(16) | No | `flat`; allowed flat, percentage |
| `rate_pct_snapshot` | DECIMAL(7,4) | Yes | NULL for flat fee; exact applied % for percentage fee |
| `flat_amount_snapshot` | DECIMAL(15,2) | Yes | Rs 100.00 for flat rule, NULL for percentage |
| `status` | VARCHAR(32) | No | `accrued`; `refunded` only if an approved refund/reversal workflow exists; no `settled` in sprint |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `payment_id`; `(institute_id,status,created_at)`; `(status,created_at)`. The admin report labels every demo row as test/demo accrual, not received cash or net profit. Omit canonical `settlement_id` because settlements/payouts are deferred.

### 3.26 `boost_products` (sprint addition; one simple demo package is enough)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `code` | VARCHAR(40) | No | unique, e.g. `DEMO_7_DAY` |
| `name` | VARCHAR(150) | No | none |
| `description` | TEXT | Yes | NULL |
| `price` | DECIMAL(15,2) | No | server-controlled price |
| `currency` | CHAR(3) | No | `LKR` |
| `duration_days` | UNSIGNED SMALLINT | No | `7` for initial demo package |
| `is_active` | BOOLEAN | No | `true` |
| `demo_only` | BOOLEAN | No | `true` during sprint |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`, unique `code`, `(is_active,demo_only)`.

### 3.27 `boost_purchases` (sprint addition; records purchase only)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `uuid` | CHAR(36) | No | unique |
| `institute_id` | BIGINT UNSIGNED | No | FK → `institutes.id` ON DELETE RESTRICT |
| `class_id` | BIGINT UNSIGNED | No | FK → `classes.id` ON DELETE RESTRICT |
| `boost_product_id` | BIGINT UNSIGNED | No | FK → `boost_products.id` ON DELETE RESTRICT |
| `purchased_by_user_id` | BIGINT UNSIGNED | No | FK → `users.id` ON DELETE RESTRICT |
| `product_name_snapshot` | VARCHAR(150) | No | snapshot at purchase |
| `amount` | DECIMAL(15,2) | No | server-side product price snapshot |
| `currency` | CHAR(3) | No | `LKR` |
| `status` | VARCHAR(32) | No | `demo_paid`; allowed pending, demo_paid, cancelled |
| `idempotency_key` | VARCHAR(100) | No | unique |
| `created_at` | TIMESTAMP | Yes | NULL |
| `updated_at` | TIMESTAMP | Yes | NULL |

**Indexes:** unique `uuid`; unique `idempotency_key`; `(institute_id,created_at)`; `(class_id,status)`. This record has no effect on public ranking/placement in the sprint. If the team later decides to activate featured listings, model an explicit placement period and audit the C1 scope change first.

### 3.28 `audit_logs` (canonical; immutable history)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `user_id` | BIGINT UNSIGNED | Yes | NULL for system action; FK → `users.id` ON DELETE RESTRICT |
| `action` | VARCHAR(120) | No | e.g. attendance.marked, guardian_link.revoked, payment.succeeded_demo |
| `auditable_type` | VARCHAR(120) | Yes | NULL for non-polymorphic action |
| `auditable_id` | BIGINT UNSIGNED | Yes | NULL |
| `old_values` | JSON | Yes | NULL; minimise sensitive data |
| `new_values` | JSON | Yes | NULL; minimise sensitive data |
| `ip_address` | VARCHAR(45) | Yes | NULL |
| `user_agent` | VARCHAR(1000) | Yes | NULL |
| `created_at` | TIMESTAMP | No | current timestamp; no updated_at, no deleted_at |

**Indexes:** `(auditable_type,auditable_id)`; `(user_id,created_at)`; `(action,created_at)`. Append only. Do not log OTPs, full invite codes, payment secrets or unnecessary child information.

### 3.29 `webhook_logs` (canonical table, PayHere stretch)

| Column | MySQL type | Null? | Default / rule |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | No | PK |
| `provider` | VARCHAR(32) | No | `payhere` when integration is enabled |
| `event_type` | VARCHAR(100) | No | provider status/event label |
| `headers` | JSON | Yes | NULL; strip secrets/auth headers |
| `payload` | JSON | No | sanitised callback payload |
| `signature_status` | VARCHAR(16) | No | `invalid` initially; allowed valid, invalid, unchecked |
| `payment_transaction_id` | BIGINT UNSIGNED | Yes | NULL; FK → `payment_transactions.id` ON DELETE RESTRICT |
| `received_at` | TIMESTAMP | No | current timestamp |

**Indexes:** `(provider,received_at)`; `(payment_transaction_id)`; `(signature_status,received_at)`. Store callback evidence only after sensitive fields are scrubbed; verify PayHere signature and amounts before applying status. No webhook is assumed to exist for mock-provider transactions.

## 4. Laravel-managed infrastructure tables

Let Laravel generate its framework tables from the selected Laravel 13 starter/application configuration rather than manually duplicating them. At minimum inspect the installed migrations for `migrations`, `sessions` if database-backed sessions are selected, `cache`/`cache_locks` if database cache is selected, `jobs`/`failed_jobs` if database queue is selected, and Sanctum's `personal_access_tokens` if the package migration is included. The first-party SPA should use cookie/session auth; personal access tokens are not needed for the web app in this sprint.

## 5. Recommended migration order

1. `users`, `roles`, `institutes` (if `user_roles.institute_id` FK is added after institutes), `user_roles`, `otp_verifications` — avoid circular FK dependencies by creating `users`, `roles`, and `institutes` before `user_roles`; a follow-up migration can add `user_roles.institute_id` FK after `institutes` exists.
2. Lookup tables: `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi`.
3. `teacher_profiles`, `student_profiles`, `guardian_profiles`, `institute_settings`, `teacher_institute`.
4. `classes`, `class_schedules`, `class_fee_versions`, `class_enrollments`, `class_sessions`.
5. `student_guardians`, `guardian_link_invitations`, `attendances`.
6. `commission_rules`, `invoices`, `invoice_items`, `payments`, `payment_transactions`, `platform_commissions`.
7. `boost_products`, `boost_purchases`.
8. `audit_logs`, `webhook_logs` (or create required referenced tables first, then add FK with a follow-up migration).

**Implementation note:** Because `institutes.owner_user_id` refers to `users` while `user_roles.institute_id` refers to `institutes`, create users/roles first, then institutes, then user_roles with its institute FK. Avoid circular migration errors with deliberate migration order.

## 6. Critical constraints and automated tests

- User phone normalisation/uniqueness; OTP challenge expiry, one-time consumption, max attempts and production refusal for log driver.
- Institute tenant isolation for all institute/class/student/teacher/attendance/invoice queries.
- Teacher assignment is required before class creation/assignment; teacher cannot manage an unrelated class.
- Unique active enrolment for `(class_id,student_id)`; unique attendance for `(session_id,enrollment_id)`.
- Parent read must fail without an active guardian link, including after revocation.
- Guardian invite must match the authenticated guardian's phone, expire, and be single-use.
- Commission rule precedence and snapshots; no changes to invoice amounts after creation.
- A successful mock payment creates exactly one commission accrual in one transaction; retries with the same idempotency key do not duplicate payment or commission.
- No payout/settlement records or success claims exist in the sprint. Demo test ledger must be visibly labelled.
- Boost purchase amounts come from server configuration; purchase creates no ranking/placement effect in this sprint.
- All money is `DECIMAL(15,2)` and currency is LKR; never use floating-point arithmetic for server money calculations.
- Every attendance correction, guardian-link change, fee change, invoice/payment transition and commission accrual writes an audit record.
