# TuitionHub — Sprint Schema (migration specification)

**Status:** APPROVED (D-013) — Migration Specification. Part 1 covers groups **G1–G3** (`CLAUDE.md` source order, item 4). Part 2 (R2: `audit_logs` and G4) is approved separately under D-015, and Part 3 (R3: G5 classes and G6 attendance) under D-024. Approval is conditional on VP-1: these migrations run first only on the disposable `tuitionhub_test` database (D-013).
**Prepared:** 2026-10-28, branch `sprint/day1-api-scaffold`. **Revision 2** (2026-10-28) applies the founders' direction on Q1–Q24.
**Sources, highest first:** `docs/PRODUCT_DECISIONS.md` (C1–C8) → `docs/DECISION_LOG.md` approved entries (D-005, D-011, D-012) → `docs/API_CONTRACT.md` v0.2 → this file → `docs/reference/TuitionHub_Sprint_Schema_Draft.md` and `docs/reference/TuitionHub_ER_Schema.md` (historical).

**How to read the "Basis" labels**

| Label | Meaning |
|---|---|
| **Decided** (source) | Already fixed by C1–C8, approved D-011/D-012, or contract v0.2. |
| **PROPOSAL** (source) | Not fixed by any approved document. Source is *brief* (the R1 task brief), *draft* (sprint draft), *canonical* (ER schema) or *new* (introduced here). Needs founder review. |

Every PROPOSAL is also listed under [Open questions](#open-questions).

---

## 0. Conventions

| # | Convention | Basis |
|---|---|---|
| 0.1 | MySQL **8.4** InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`. Matches `api/config/database.php` (`DB_CHARSET`, `DB_COLLATION`) and D-003/D-004. The collation is case-insensitive: unique `code`/`email` values that differ only in case collide, and a `CHECK (x IN (…))` also accepts any casing (`'Colombo'`, `'colombo'`, `'COLOMBO'` all pass). **The collation does not enforce canonical casing.** Role, scope, status, position and district values must be canonicalised by the server (exact stored spelling) before writing. | Decided (D-003, D-004); canonicalisation rule PROPOSAL (brief) |
| 0.2 | Primary keys `id BIGINT UNSIGNED AUTO_INCREMENT` (`$table->id()`); FK columns `BIGINT UNSIGNED`. | PROPOSAL (draft) |
| 0.3 | Public entities have `uuid CHAR(36) NOT NULL` with a unique index, generated server-side and never accepted from the client as authority. Routes use UUIDs only. | Decided (`CLAUDE.md`, contract §1.5) |
| 0.4 | Money `DECIMAL(15,2)`; currency `CHAR(3)` default `'LKR'`. (No money columns in G1–G3.) | Decided (contract §1.5) |
| 0.5 | Statuses and small vocabularies are `VARCHAR(32)`, never native `ENUM`; allowed values are pinned by a `CHECK`. | PROPOSAL (brief, draft) |
| 0.6 | **Event instants** (`created_at`, `updated_at`, `*_at`) are `DATETIME` holding **UTC**, not `TIMESTAMP`. See §0.A. | PROPOSAL (brief; founder direction Q1) |
| 0.7 | **Calendar dates** are `DATE` (an `Asia/Colombo` calendar date). **Class times** are `TIME` (`Asia/Colombo` wall-clock time). Never time-zone converted. | Decided (contract §1.5, D-005) |
| 0.8 | Soft deletes (`deleted_at`) only where a table says so. **No G1–G3 table has soft deletes** (founder direction Q10). Disabling uses `status` where the table has one (`users.status = 'suspended'`, `institutes.status = 'suspended'`). Never soft-delete money or audit tables. | PROPOSAL (brief; founder direction Q10) |
| 0.9 | "One active row" uniqueness: a nullable **STORED** generated `active_flag` (`1` when active, `NULL` otherwise) inside a unique index. MySQL lets a unique index hold any number of `NULL`s, so historical rows never collide. | Decided (contract §2.3) |
| 0.10 | Simple invariants are enforced with `CHECK` constraints, in addition to Form Requests and services. | PROPOSAL (brief) |
| 0.11 | FKs are `ON DELETE RESTRICT`, and `ON UPDATE` is left unset (MySQL default `NO ACTION`). **No `CASCADE`/`SET NULL`** on any column that feeds a generated column or a `CHECK`; MySQL forbids it (see §0.C). For FK columns that also appear in a `CHECK` (`user_roles.role_scope`, `user_roles.institute_id`), whether the FK could state `ON DELETE RESTRICT` explicitly was open (§0.C-2). **Resolved by VP-1 (D-014): explicit `ON DELETE RESTRICT` is accepted** on MySQL 8.4. | PROPOSAL (brief, draft); FK form verified (D-014) |
| 0.12 | Constraint and index names are explicit and ≤ 64 characters, e.g. `user_roles_user_role_scope_unique`. | PROPOSAL (new) |

### 0.A DATETIME convention (instead of `timestamps()`)

- **How it is declared** (founder direction Q1). Every table declares the two columns explicitly, instead of `$table->timestamps()` (which creates `TIMESTAMP`):

  ```php
  $table->dateTime('created_at')->nullable();
  $table->dateTime('updated_at')->nullable();
  ```

  - Other instants use the same method, e.g. `$table->dateTime('expires_at')`.
  - `dateTime()` is documented on the Laravel 13 migrations page ("creates a `DATETIME` equivalent column"). The `datetimes()` shortcut in the installed 13.38.0 source is **not** used: it isn't documented, and the explicit form is clearer.
- **Eloquent compatibility:**
  - Eloquent writes `created_at`/`updated_at` itself on `save()`, `create()` and `update()`, using `freshTimestamp()` in the application time zone. That zone is `UTC` (`api/config/app.php` `'timezone' => 'UTC'`, D-005).
  - The values are formatted with the grammar date format `Y-m-d H:i:s`, which fits `DATETIME` exactly. `datetime` casts work the same way on `DATETIME` as on `TIMESTAMP`.
  - **Constraint:** `DATETIME` is not converted by MySQL, so its values are only UTC because the application writes UTC. `app.timezone` must stay `UTC`. Do not use database defaults such as `CURRENT_TIMESTAMP` or `useCurrent()` for instants; the application always supplies the value.
- **Writes that bypass Eloquent must set UTC timestamps themselves.** Query-builder inserts and updates (`DB::table(...)->insert/update`) and seed inserts inside migrations (e.g. the `roles` rows, Q4) do **not** fill `created_at`/`updated_at`. They must set both explicitly to the current UTC time, e.g. `now('UTC')`. Leaving them NULL, or using a local-time value, is a defect.
- **Why** (MySQL 8.4 manual, verified 2026-10-28):
  - `TIMESTAMP` ends at `2038-01-19 03:14:07` UTC and is converted through the session time zone.
  - `DATETIME` runs to year 9999 and is never converted.
- **Instants vs dates:**
  - `*_at` columns are UTC instants. The API renders them as ISO 8601 `Z` (contract §1.5).
  - `*_on` columns and `session_date`/`effective_from` are `DATE` values in `Asia/Colombo`.
  - **Never** derive a `DATE` by truncating a UTC `DATETIME` in SQL. Convert to `Asia/Colombo` in application code first.

### 0.B Generated columns, CHECKs and composite FKs in Laravel

- **Generated column:** `$table->unsignedTinyInteger('active_flag')->nullable()->storedAs('…')`.
  - For a generated column that must be `NOT NULL`, call `->nullable(false)` explicitly. The installed MySQL grammar (`MySqlGrammar::modifyNullable`) only emits `NOT NULL` for a generated column when `nullable` is explicitly `false`.
  - A generated column may reference only base columns and generated columns declared **earlier**. It may not reference `AUTO_INCREMENT` columns.
- **CHECK:** Blueprint has no `check()` method (confirmed in the 13.38.0 source). Add each CHECK in the same migration, after `Schema::create`, with `DB::statement('ALTER TABLE … ADD CONSTRAINT … CHECK (…)')`. Drop it in `down()` (or by dropping the table).
- **Not rollback-safe:**
  - MySQL DDL commits implicitly, so a migration of `Schema::create` followed by `ALTER TABLE … ADD CONSTRAINT … CHECK` is **not atomic as a whole**.
  - If the `ALTER` fails, the table already exists, the migration is not recorded as run, and a re-run fails on "table already exists". Manual cleanup is then needed.
  - VP-1 (and any first run of these migrations) must therefore run **only on the disposable `tuitionhub_test` database** (D-010 guard), never on `tuitionhub`.
- **Composite FK:** `$table->foreign(['role_id', 'role_scope'], 'user_roles_role_scope_fk')->references(['id', 'scope'])->on('roles')->restrictOnDelete();`. VP-1 confirmed that MySQL 8.4 accepts the explicit `->restrictOnDelete()` here (§0.C-2, D-014).
- **String FK columns:** `roles.scope` and `user_roles.role_scope` must have the **identical** type, character set and collation. MySQL requires the same charset and collation for nonbinary string FK columns. Declare both explicitly: `$table->string('scope', 32)->charset('utf8mb4')->collation('utf8mb4_unicode_ci')` and the same for `role_scope`, so a later change to the connection or table default cannot make them diverge.

### 0.C MySQL 8.4 limits these designs depend on (manual, verified 2026-10-28)

1. A `CHECK` may not reference other tables, subqueries, stored functions, variables or `AUTO_INCREMENT` columns. **It cannot read `roles.name` through `user_roles.role_id`.**
2. "Foreign key referential actions (`ON UPDATE`, `ON DELETE`) are prohibited on columns used in `CHECK` constraints", and the reverse.
   - The manual does not say whether an **explicit** `ON DELETE RESTRICT`, as opposed to an omitted clause (which defaults to `NO ACTION`), counts as a prohibited referential action.
   - Before VP-1, nothing in this document claimed that `restrictOnDelete()` was valid on a CHECK-bearing FK column. In G1–G3 this affects only `user_roles.role_scope` (composite FK + `user_roles_scope_chk`) and `user_roles.institute_id` (FK + the same CHECK).
   - **Resolved by VP-1 (Q20, D-014, 2026-10-28):** MySQL 8.4 accepted both FKs with explicit `ON DELETE RESTRICT` together with `user_roles_scope_chk`, with no error. `information_schema` reports `DELETE_RULE = RESTRICT`, and VP-16 confirms that the parent delete is rejected (error 1451).
     - The unset (`NO ACTION`) form was not needed and was not tried.
     - The fallback this item originally planned for (leave `ON DELETE` unset, or drop the backstop) was not used.
3. The base column of a **stored** generated column cannot have an FK with `CASCADE`, `SET NULL` or `SET DEFAULT`. An FK cannot reference a **virtual** generated column.
4. A composite FK needs an index in the parent whose first columns are the referenced columns, in the same order. Referencing a non-unique index is deprecated, so a **unique** `(id, scope)` index is used.
5. InnoDB checks constraints immediately; there are **no deferred FKs**. Insert order inside a transaction must therefore respect FK direction.

---

## G1 — Lookups

All six taxonomy tables are seeded reference data; clients never create values (draft §3.11). The API refers to them by `code` (contract §1.5), so they have **no `uuid`**. None of them has soft deletes or FKs.

### G1.1 `subjects`

Purpose: subject taxonomy for classes and catalogue filters (contract §7.1, §7.5).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(40) | No | — | unique, e.g. `MATH` | Decided (contract §1.5, §7.1) |
| `name` | VARCHAR(150) | No | — | English name | PROPOSAL (draft) |
| `name_si` | VARCHAR(150) | Yes | NULL | Sinhala name | PROPOSAL (canonical, draft) |
| `name_ta` | VARCHAR(150) | Yes | NULL | Tamil name | PROPOSAL (canonical, draft) |
| `category` | VARCHAR(32) | No | `'core'` | `core\|optional\|extra` | PROPOSAL (canonical, draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | explicit `dateTime()` (§0.A) | PROPOSAL (§0.6) |

- **Unique:** `subjects_code_unique (code)`.
- **CHECK:** `subjects_category_chk CHECK (category IN ('core','optional','extra'))`.

### G1.2 `grades`

Purpose: grade taxonomy (e.g. `G11`, `AL2`).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(40) | No | — | unique | Decided (contract §1.5) |
| `name` | VARCHAR(150) | No | — | | PROPOSAL (draft) |
| `level` | VARCHAR(40) | No | — | `primary\|junior_secondary\|ol\|al` | PROPOSAL (canonical, draft) |
| `sort_order` | SMALLINT UNSIGNED | No | `0` | display order | PROPOSAL (canonical, draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `grades_code_unique (code)`.
- **Index:** `grades_sort_order_index (sort_order)`.
- **CHECK:** `grades_level_chk CHECK (level IN ('primary','junior_secondary','ol','al'))`.

### G1.3 `streams`

Purpose: A/L stream taxonomy (e.g. `SCI_MATH`).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(40) | No | — | unique | Decided (contract §1.5) |
| `name` | VARCHAR(150) | No | — | | PROPOSAL (draft) |
| `level` | VARCHAR(40) | No | `'al'` | | PROPOSAL (canonical, draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `streams_code_unique (code)`.

### G1.4 `exam_targets`

Purpose: target exam sitting (e.g. `OL_2027`, `AL_2027`).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(40) | No | — | unique | Decided (contract §1.5) |
| `name` | VARCHAR(150) | No | — | | PROPOSAL (draft) |
| `exam` | VARCHAR(16) | No | — | `ol\|al` | PROPOSAL (canonical, draft) |
| `year` | SMALLINT UNSIGNED | No | — | exam year | PROPOSAL (canonical, draft) |
| `approximate_exam_date` | DATE | Yes | NULL | calendar date | PROPOSAL (canonical, draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `exam_targets_code_unique (code)`.
- **CHECK:** `exam_targets_exam_chk CHECK (exam IN ('ol','al'))`.

### G1.5 `mediums`

Purpose: language of instruction.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(16) | No | — | unique: `sin`, `tam`, `eng` | Decided for `sin` (contract §7.1); PROPOSAL for `tam`, `eng` (canonical, draft) |
| `name` | VARCHAR(100) | No | — | | PROPOSAL (draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `mediums_code_unique (code)`.

### G1.6 `syllabi`

Purpose: syllabus taxonomy.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `code` | VARCHAR(40) | No | — | unique: `local`, `edexcel`, `cambridge` | Decided for `local` (contract §7.1); PROPOSAL for the others (canonical, draft) |
| `name` | VARCHAR(120) | No | — | | PROPOSAL (draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `syllabi_code_unique (code)`.

### G1.7 `roles`

Purpose: the stored-role vocabulary. Only `super_admin`, `institute_admin` and `institute_staff` exist; `teacher` and `guardian` are **never** roles (D-011, contract §3). No Spatie and no `guard_name` (D-012).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `name` | VARCHAR(64) | No | — | unique; one of the three role names | Decided values (D-011); PROPOSAL type |
| `scope` | VARCHAR(32), `utf8mb4` / `utf8mb4_unicode_ci` (explicit) | No | — | `global\|institute`; must match `user_roles.role_scope` exactly in type, charset and collation (§0.B) | PROPOSAL (brief; founder direction Q2) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `roles_name_unique (name)`.
  - `roles_id_scope_unique (id, scope)` — the parent key for the composite FK from `user_roles`. It is logically redundant with the PK; it exists only for that FK. PROPOSAL (brief).
- **CHECKs** (PROPOSAL):
  - `roles_scope_chk CHECK (scope IN ('global','institute'))` (brief).
  - `roles_name_scope_chk CHECK ((name = 'super_admin' AND scope = 'global') OR (name IN ('institute_admin','institute_staff') AND scope = 'institute'))` (new). This pins both the vocabulary and the scope of each role, so a mistaken seed or update cannot create a fourth role or flip a role's scope.
- **Not built:** `guard_name` (D-012); `permissions` and `role_permissions` (contract §2.2).
- **Deviation:** the draft seeds six roles with `guard_name`. That is superseded by D-011 and D-012.

---

## G2 — Identity

### G2.1 `users`

Purpose: one row per person who has logged in with OTP. Created on first successful OTP verify with `name = NULL` (contract §4.2).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §4.2) |
| `name` | VARCHAR(255) | Yes | NULL | `onboarding_required` is true while NULL (derived; not stored) | Decided (contract §2.3) |
| `phone` | VARCHAR(20) | No | — | E.164 Sri Lankan mobile `^\+947\d{8}$`; plain unique | Decided (contract §1.5, D-012 F1) |
| `email` | VARCHAR(255) | Yes | NULL | unique when present | Decided (contract §2.3, D-012 F1) |
| `password` | VARCHAR(255) | Yes | NULL | not used for login in sprint | Decided (contract §2.3) |
| `phone_verified_at` | DATETIME | Yes | NULL | UTC. In `log` mode it means "verified via local dev OTP", **not** real verification | Decided column (contract §4.2); PROPOSAL type |
| `status` | VARCHAR(32) | No | `'active'` | `active\|suspended` only; `suspended` is how an account is disabled | Decided values (contract §3, §4.4); PROPOSAL default (founder direction Q6) |
| `remember_token` | VARCHAR(100) | Yes | NULL | Laravel compatibility only | PROPOSAL (draft, skeleton) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `users_uuid_unique (uuid)`, `users_phone_unique (phone)`, `users_email_unique (email)`.
- **Index:** `users_status_index (status)`.
- **FKs:** none.
- **CHECKs:**
  - `users_status_chk CHECK (status IN ('active','suspended'))`. PROPOSAL (founder direction Q6).
  - `users_phone_format_chk CHECK (REGEXP_LIKE(phone, '^[+]947[0-9]{8}$', 'c'))`. PROPOSAL (founder direction Q7).
    - The bracket class `[+]` avoids backslash escaping in PHP and SQL strings.
    - `'c'` makes the match case-sensitive (no letters can appear anyway).
    - Acceptance by MySQL 8.4.13 is checked in VP-1. Form Requests validate the same format regardless.
- **Generated:** none. F1: no `active_flag` on `users`.
- **Not built:**
  - `type` (D-011, contract §2.3).
  - `email_verified_at` (PROPOSAL: no email verification flow in sprint).
  - `avatar_path` (PROPOSAL: no upload flow, and C6 rules out photos).
  - `deleted_at` and soft deletes (founder direction Q10). Accounts are disabled with `status = 'suspended'`, and rows are never deleted in the sprint. Phone and email are therefore never reused (D-012 F1).
- **Deviations:**
  - The draft's `type` and index `(type,status)` are dropped (C8).
  - The draft's `name NOT NULL` becomes nullable (contract).
  - The canonical and draft `pending` status is dropped (Q6).
  - Canonical and draft soft deletes are dropped (Q10).
- **Migration note** (founder direction Q9):
  - The skeleton's `0001_01_01_000000_create_users_table.php` is edited **before its first run** (approved Day 1 plan, Step 4).
  - After the edit it creates **`users` and `sessions` only**. The `password_reset_tokens` table is removed from both `up()` and `down()`, because there is no password login or reset in the sprint.
  - `sessions` stays as in the skeleton (database session driver for Sanctum SPA auth).

### G2.2 `user_roles`

Purpose: **current-state** stored role grants: `super_admin` (global) and `institute_admin`/`institute_staff` (institute-scoped). It is the only role store (D-011). Teacher and guardian access never appear here (contract §3).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `user_id` | BIGINT UNSIGNED | No | — | FK `users.id` | Decided (contract §3) |
| `role_id` | BIGINT UNSIGNED | No | — | part of the composite FK to `roles` | Decided (contract §3) |
| `role_scope` | VARCHAR(32), `utf8mb4` / `utf8mb4_unicode_ci` (explicit, identical to `roles.scope`) | **No** | — | copied from `roles.scope` by the service; pinned by the composite FK | PROPOSAL (brief; founder direction Q2) |
| `institute_id` | BIGINT UNSIGNED | Yes | NULL | FK `institutes.id`; NULL only for `super_admin` | Decided (contract §3) |
| `scope_key` | BIGINT UNSIGNED, **STORED generated** | **No** | — | `IFNULL(institute_id, 0)` | PROPOSAL (brief, draft concept) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Generated:** `scope_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(institute_id, 0)) STORED NOT NULL`.
  - `0` can never be a real institute ID, because `AUTO_INCREMENT` starts at 1.
  - Laravel: `$table->unsignedBigInteger('scope_key')->nullable(false)->storedAs('IFNULL(`institute_id`, 0)')`.
- **Unique:** `user_roles_user_role_scope_unique (user_id, role_id, scope_key)`.
  - This allows at most one global `super_admin` row per user (all have `scope_key = 0`), and at most one row per user, role and institute.
  - A plain unique key on the nullable `institute_id` would not catch duplicate global rows, because the unique index allows any number of NULLs.
- **Other indexes:**
  - `user_roles_institute_role_index (institute_id, role_id)`, for listing an institute's admins and staff.
  - The composite FK's index `(role_id, role_scope)` is created automatically.
  - `user_id` is covered by the leftmost column of the unique key.
- **FKs** (no `ON UPDATE` action on any):
  - `user_roles_user_fk (user_id) → users(id) ON DELETE RESTRICT`.
  - `user_roles_institute_fk (institute_id) → institutes(id)`.
    - `institute_id` is the base of a stored generated column and is used in a CHECK, so this FK **must not** be `CASCADE`/`SET NULL` (§0.C).
    - `ON DELETE RESTRICT`, stated explicitly. VP-1 confirmed MySQL accepts it (§0.C-2, D-014).
  - `user_roles_role_scope_fk (role_id, role_scope) → roles(id, scope) ON DELETE RESTRICT`. PROPOSAL (brief). This replaces a single-column FK on `role_id`. `role_scope` is used in the CHECK; VP-1 confirmed the explicit `RESTRICT` form (D-014).
- **CHECK:** `user_roles_scope_chk CHECK ((role_scope = 'global' AND institute_id IS NULL) OR (role_scope = 'institute' AND institute_id IS NOT NULL))`. PROPOSAL (brief).
- **Soft deletes:** none. Revoking a role **deletes the row**, and the deletion is audited. PROPOSAL (brief; see Q3).

**How the composite FK and the CHECK work together (PROPOSAL, database backstop)**

1. A CHECK cannot look up `roles.name` (§0.C-1), so the role's scope is copied into `user_roles.role_scope`.
2. The composite FK `(role_id, role_scope) → roles(id, scope)` accepts the row only if that exact pair exists in `roles`. So `role_scope` can never disagree with the role's real scope: `super_admin` + `institute` is rejected, and so is `institute_staff` + `global`.
3. `roles_name_scope_chk` fixes each role's scope. Because `ON UPDATE` is `NO ACTION`, `roles.scope` cannot be changed while any `user_roles` row references it.
4. `user_roles_scope_chk` ties `role_scope` to whether `institute_id` is set. A global role must have no institute; an institute role must have one.
5. Chain: **role → scope (FK) → institute present or absent (CHECK)**. A `super_admin` row with an institute, or an institute role without one, is rejected by the database **whatever code path writes it**.
6. `scope_key` then makes duplicates impossible within each scope.

- **Application rule** (PROPOSAL, brief; the DB backstop is defence in depth, not a substitute):
  - `RoleAssignmentService` is the **only** writer of `user_roles`. It is used by API flows and seeders alike.
  - It derives `role_scope` from the `roles` row. It never takes `role_scope` or `institute_id` from request input.
  - It writes the matching audit event in the same transaction. `audit_logs` must therefore exist first (see "Later groups": first in R2).
  - It **refuses to revoke an owner's `institute_admin` grant** for an institute while that institute's `owner_user_id` still references the user. This follows D-011: the owner always holds `institute_admin` for their institute (VP-26). The owner cannot be changed in the sprint (contract §5.5).
  - It writes canonical values only (§0.1): exact role names and scopes, never caller-supplied casing.
- **Effective access:**
  - A `super_admin` needs this table's row.
  - An institute admin needs this table's row **for that institute**.
  - Institute staff need this table's `institute_staff` row **and** an active `institute_staff` row (G3.2, contract §3).
  - Authorization is re-derived from the database on every request. Roles are never cached in the session (contract §4.4: display hints only).
- **Deviation:** the draft's `scope_key VARCHAR(64)` (`'global'` / `'institute:{id}'`), maintained by the server, becomes a generated `BIGINT` that the server cannot get wrong.

### G2.3 `otp_challenges`

Purpose: one OTP login challenge (contract §4.1–§4.2). The code is stored as a hash only and never logged outside `OTP_DRIVER=log`.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique; returned as `challenge_id` | Decided (contract §2.3, §4.1) |
| `phone` | VARCHAR(20) | No | — | E.164 | Decided (contract §2.3) |
| `code_hash` | VARCHAR(255) | No | — | hash only; never plaintext | Decided (contract §4.1); PROPOSAL type |
| `purpose` | VARCHAR(32) | No | — | `login` only in sprint | Decided (contract §4.1) |
| `attempts` | TINYINT UNSIGNED | No | `0` | failed verify count | Decided (contract §2.3) |
| `max_attempts` | TINYINT UNSIGNED | No | — | set from config (proposed 5); no DB default | Decided column (contract §2.3); PROPOSAL "no default" |
| `expires_at` | DATETIME | No | — | UTC; proposed 5 min | Decided column (contract §2.3) |
| `consumed_at` | DATETIME | Yes | NULL | set atomically on success; single use | Decided (contract §2.3, §4.2) |
| `last_sent_at` | DATETIME | No | — | resend cooldown anchor | Decided column (contract §2.3); PROPOSAL NOT NULL |
| `request_ip_hash` | CHAR(64) | Yes | NULL | hashed IP, never a raw IP | Decided column (contract §2.3); PROPOSAL type |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `otp_challenges_uuid_unique (uuid)`.
- **Indexes** (PROPOSAL):
  - `otp_challenges_phone_purpose_created_index (phone, purpose, created_at)`, for per-phone rate limits and finding the latest challenge.
  - `otp_challenges_expires_at_index (expires_at)`, for a later purge.
- **FKs:** none. A challenge can exist before its user does.
- **CHECKs** (PROPOSAL):
  - `otp_challenges_purpose_chk CHECK (purpose IN ('login'))`.
  - `otp_challenges_attempts_chk CHECK (max_attempts >= 1 AND attempts <= max_attempts)`.
- **Attempt guard** (required of the OTP service; founder direction Q19):
  - Every attempt increment is a single guarded atomic update: `UPDATE otp_challenges SET attempts = attempts + 1, updated_at = ? WHERE id = ? AND consumed_at IS NULL AND attempts < max_attempts`.
  - 0 affected rows means "attempts exhausted or already consumed", so the request fails (`429`/`422`, contract §4.2).
  - The CHECK is only a backstop. The guard keeps concurrent verifies from passing the limit. The other OTP details were settled in A1 (D-019); purging expired challenges stays deferred (Q19).
- **Soft deletes:** none.
- **Deviation:** the canonical and draft `otp_verifications` table is renamed and gains `max_attempts` and `last_sent_at` (contract §2.3).

### G2.4 `teacher_profiles`

Purpose: a user's teacher profile. Creating it grants **no** institute access (contract §6.1). Its `uuid` is the `teacher_uuid` used in assignments (contract §6.2).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `user_id` | BIGINT UNSIGNED | No | — | unique FK `users.id`; one profile per user | Decided FK (contract §6.1); PROPOSAL unique (draft) |
| `display_name` | VARCHAR(255) | No | — | | Decided field (contract §6) |
| `headline` | VARCHAR(255) | Yes | NULL | | Decided field (contract §6) |
| `bio` | TEXT | Yes | NULL | | Decided field (contract §6) |
| `years_experience` | SMALLINT UNSIGNED | No | `0` | | Decided field (contract §6); PROPOSAL default (draft) |
| `is_independent` | BOOLEAN (TINYINT(1)) | No | `0` | not editable via sprint API | Decided (contract §2.3) |
| `public_visibility` | VARCHAR(32) | No | `'institute_attributed'` | `full\|institute_attributed\|hidden`; not editable via sprint API | Decided (contract §2.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `teacher_profiles_uuid_unique (uuid)`, `teacher_profiles_user_id_unique (user_id)`.
- **Soft deletes:** none (founder direction Q10). The table has no `status`. A teacher is disabled by suspending the user (`users.status`) or by ending assignments (`teacher_institute`). There is no separate profile disable in the sprint.
- **FK:** `teacher_profiles_user_fk (user_id) → users(id) ON DELETE RESTRICT`.
- **CHECK:** `teacher_profiles_visibility_chk CHECK (public_visibility IN ('full','institute_attributed','hidden'))`. PROPOSAL.
- **Deviation:** the draft's default `'hidden'` becomes `'institute_attributed'` (contract §2.3). The draft's index `(is_independent, public_visibility)` is dropped (PROPOSAL: no sprint query uses it).

### G2.5 `guardian_profiles`

Purpose: marks a user as a guardian. Guardian **access** comes only from active `student_guardians` links (later group), never from this row (D-011). API routes identify guardians by **user** UUID (contract §9.4).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique; not used in sprint routes | PROPOSAL (brief, draft) |
| `user_id` | BIGINT UNSIGNED | No | — | unique FK `users.id` | Decided FK (canonical, contract §3); PROPOSAL unique (draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `guardian_profiles_uuid_unique (uuid)`, `guardian_profiles_user_id_unique (user_id)`.
- **Soft deletes:** none (founder direction Q10). The table has no `status`. A guardian is disabled by suspending the user or by revoking links (later group). There is no separate profile disable in the sprint.
- **FK:** `guardian_profiles_user_fk (user_id) → users(id) ON DELETE RESTRICT`.
- **Not built:** `occupation`, `preferred_contact` (`sms|whatsapp|app|email`), `has_smartphone`. PROPOSAL (data minimisation): no sprint endpoint reads them, and `preferred_contact` implies SMS (D-012 F3 spirit). See Q11.

---

## G3 — Organisation

### G3.1 `institutes`

Purpose: a tuition institute. Its owner automatically holds `institute_admin` for it (D-011).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §5) |
| `owner_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; set by the server to the caller; never changed via API | Decided (contract §5.1, §5.5) |
| `name` | VARCHAR(255) | No | — | | Decided field (contract §5) |
| `description` | TEXT | Yes | NULL | | Decided field (contract §5) |
| `status` | VARCHAR(32) | No | `'active'` | `active\|suspended`; only `super_admin` changes it | Decided (contract §5.1, §5.5) |
| `district` | VARCHAR(100) | No | — | one of the 25 districts below, in the canonical spelling; catalogue filter | Decided field (contract §5, §7.5); PROPOSAL NOT NULL (draft) and list (founder direction Q14) |
| `city` | VARCHAR(100) | Yes | NULL | | Decided field (contract §5) |
| `contact_phone` | VARCHAR(20) | Yes | NULL | | Decided field (contract §5) |
| `contact_email` | VARCHAR(255) | Yes | NULL | | Decided field (contract §5) |
| `business_reg_no` | VARCHAR(100) | Yes | NULL | | Decided field (contract §5) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | Decided fields (contract §5); PROPOSAL type |

- **Unique:** `institutes_uuid_unique (uuid)`.
- **Indexes** (PROPOSAL, draft):
  - `institutes_owner_index (owner_user_id)`.
  - `institutes_district_status_index (district, status)`.
  - `institutes_status_index (status)`.
- **FK:** `institutes_owner_fk (owner_user_id) → users(id) ON DELETE RESTRICT`.
- **CHECKs:**
  - `institutes_status_chk CHECK (status IN ('active','suspended'))`. Decided values (contract §5.5); PROPOSAL CHECK.
  - `institutes_district_chk CHECK (district IN ('Ampara','Anuradhapura','Badulla','Batticaloa','Colombo','Galle','Gampaha','Hambantota','Jaffna','Kalutara','Kandy','Kegalle','Kilinochchi','Kurunegala','Mannar','Matale','Matara','Monaragala','Mullaitivu','Nuwara Eliya','Polonnaruwa','Puttalam','Ratnapura','Trincomalee','Vavuniya'))`. PROPOSAL (founder direction Q14).
- **Districts:** the 25 administrative districts of Sri Lanka, in canonical stored spelling (title case, alphabetical):
  - Ampara, Anuradhapura, Badulla, Batticaloa, Colombo
  - Galle, Gampaha, Hambantota, Jaffna, Kalutara
  - Kandy, Kegalle, Kilinochchi, Kurunegala, Mannar
  - Matale, Matara, Monaragala, Mullaitivu, Nuwara Eliya
  - Polonnaruwa, Puttalam, Ratnapura, Trincomalee, Vavuniya
- **District rules:**
  - **Server validation:** the Form Request accepts only these 25 values, matching case-insensitively.
  - **Canonicalisation:** the server maps the input to the exact canonical spelling above before writing. The CHECK alone would also accept `colombo` (§0.1).
  - The same list drives the catalogue `district` filter (contract §7.5). The contract example `"Colombo"` is canonical.
  - The spelling `Monaragala` (also written "Moneragala") is the proposed canonical form; founders to confirm with the list.
- **Soft deletes:** none (founder direction Q10). An institute is disabled with `status = 'suspended'` (contract §5.5).
- **Not built:**
  - `slug` (closed, founder direction Q12: routes use UUIDs and the contract shape has none).
  - `vat_no`, `logo_path`, `lat`, `lng` (closed, Q13). No endpoint uses them, and maps are out of scope (C7).
  - `deleted_at` (Q10).
  - The draft's status `draft`: the contract creates institutes as `active` and allows only `active|suspended`.
- **Atomic creation rule** (Decided, contract §5.1; D-011):
  - `POST /institutes` runs **one transaction**: insert `institutes` (with `owner_user_id` = caller), then `RoleAssignmentService` inserts `user_roles (owner, institute_admin, 'institute', new institute_id)`, then the audit event.
  - The FK order is satisfied by the insert order, so no deferred constraint is needed (InnoDB has none; §0.C-5).
  - Any failure rolls back everything, so there is never an institute without an owner-admin, or the reverse.

### G3.2 `institute_staff`

Purpose: an employment record that gives institute staff their access, together with the `institute_staff` role (contract §3). It also defines "institute manager" (`position = manager`). History is kept: rows are never deleted.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (canonical) |
| `institute_id` | BIGINT UNSIGNED | No | — | FK `institutes.id` | Decided (canonical, contract §2.1) |
| `user_id` | BIGINT UNSIGNED | No | — | FK `users.id` | Decided (canonical) |
| `position` | VARCHAR(32) | No | — | `manager\|clerk\|receptionist` | Decided for `manager` (contract §3); PROPOSAL for the others (canonical, brief) |
| `status` | VARCHAR(32) | No | `'active'` | `active\|former` | PROPOSAL (brief) |
| `joined_on` | DATE | No | — | `Asia/Colombo` date the staff member joined | PROPOSAL (founder direction Q15; renamed from canonical `joined_at`) |
| `left_on` | DATE | Yes | NULL | `Asia/Colombo` date the staff member left | PROPOSAL (brief) |
| `active_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `1` when active, else NULL | PROPOSAL (brief) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Generated:** `active_flag TINYINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'active' AND left_on IS NULL, 1, NULL)) STORED`.
- **Unique:** `institute_staff_active_unique (institute_id, user_id, active_flag)`. This allows **one active** row per user and institute, and any number of `former` rows (their flag is NULL).
- **Index:** `institute_staff_user_status_index (user_id, status)`, for the per-request access lookup.
- **FKs:**
  - `institute_staff_institute_fk (institute_id) → institutes(id) ON DELETE RESTRICT`.
  - `institute_staff_user_fk (user_id) → users(id) ON DELETE RESTRICT`.
  - Both are `RESTRICT` by convention (§0.11): employment history must never be cascade-deleted. Neither column feeds the generated column or a CHECK, so MySQL itself would allow a cascade.
- **CHECKs** (PROPOSAL):
  - `institute_staff_status_chk CHECK (status IN ('active','former'))`.
  - `institute_staff_position_chk CHECK (position IN ('manager','clerk','receptionist'))`.
  - `institute_staff_status_left_chk CHECK ((status = 'active' AND left_on IS NULL) OR (status = 'former' AND left_on IS NOT NULL))`. Status and end date can never disagree.
  - `institute_staff_dates_chk CHECK (left_on IS NULL OR left_on >= joined_on)` (founder direction Q15).
- **Uuid:** none (no sprint endpoint exposes staff rows). PROPOSAL.
- **Soft deletes:** none. History is kept as `former` rows.
- **Staff lifecycle rule** (PROPOSAL, brief):
  - **Add:** one transaction inserts `institute_staff` (`active`) **and** the `user_roles` `institute_staff` row for the same institute, plus audit.
  - **End:** one transaction sets `status = 'former'` and `left_on`, **deletes** the `user_roles` `institute_staff` row for that institute (leaving any `institute_admin` row alone), and writes audit.
  - **Access** needs both rows (contract §3). Re-hiring creates a **new** `institute_staff` row and a new role row; the old row stays `former`.
  - See "Known sprint gaps": there is no staff add or end endpoint.

### G3.3 `teacher_institute`

Purpose: a teacher's assignment to an institute. An **active** row is the source of teacher access (with `classes.teacher_id` for class data). It is never deleted; ending sets `left_on` and `former` (contract §3, §6.2).

**`teacher_id` reference — confirmed from the contract:**
- The column references **`teacher_profiles.id`**, not `users.id`.
- Contract §6.2 takes `teacher_uuid`, which "is the teacher profile UUID".
- Contract §3 defines the assigned teacher as the user "whose `teacher_profiles` row is the class's `classes.teacher_id`".
- The canonical schema and the draft also link `teacher_profiles ||--o{ teacher_institute`.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique; not exposed in sprint responses | PROPOSAL (draft) |
| `teacher_id` | BIGINT UNSIGNED | No | — | FK `teacher_profiles.id` | Decided (contract §3, §6.2) |
| `institute_id` | BIGINT UNSIGNED | No | — | FK `institutes.id` | Decided (contract §6.2) |
| `joined_on` | DATE | No | — | `Asia/Colombo` date | Decided (contract §6.2) |
| `left_on` | DATE | Yes | NULL | NULL while active | Decided (contract §3, §6.2) |
| `status` | VARCHAR(32) | No | `'active'` | `active\|former` | Decided (contract §3, §6.2) |
| `active_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `1` when active, else NULL | Decided pattern (contract §2.3); PROPOSAL expression |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Generated:** `active_flag TINYINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'active' AND left_on IS NULL, 1, NULL)) STORED`.
- **Unique:**
  - `teacher_institute_uuid_unique (uuid)`.
  - `teacher_institute_active_unique (teacher_id, institute_id, active_flag)`. A second active assignment returns `409` (contract §6.2), and historical rows are allowed.
- **Indexes:** `teacher_institute_institute_status_index (institute_id, status)` (lists an institute's teachers, §6.3). `teacher_id` is covered by the leftmost column of the unique key.
- **FKs:**
  - `teacher_institute_teacher_fk (teacher_id) → teacher_profiles(id) ON DELETE RESTRICT`.
  - `teacher_institute_institute_fk (institute_id) → institutes(id) ON DELETE RESTRICT`.
- **CHECKs** (PROPOSAL):
  - `teacher_institute_status_chk CHECK (status IN ('active','former'))`.
  - `teacher_institute_status_left_chk CHECK ((status = 'active' AND left_on IS NULL) OR (status = 'former' AND left_on IS NOT NULL))`.
  - `teacher_institute_dates_chk CHECK (left_on IS NULL OR left_on >= joined_on)`.
- **Soft deletes:** none (never deleted; contract §6.2).
- **Not built:** `revenue_share_pct`. PROPOSAL: institute-internal finance, not in the contract, and unused in the draft.
- **Deviation:** the draft enforced "unique active" only in application code. The contract §2.3 pattern puts it in the database.
- **Ending an assignment removes access immediately:**
  - Every teacher permission check (Policies, `teaching_at`, `my_access`, §6.4) uses one shared `active()` scope: `status = 'active' AND left_on IS NULL`. Existence of a row alone never counts.
  - Nothing is cached in the session, so the next request after `former` is set already fails (contract §3: "access is lost on the next request").
  - See "Known sprint gaps": there is no end-assignment endpoint (contract §6.2).

### Known sprint gaps (G3)

Contract v0.2 has **no** endpoints for these flows. None is invented here:

| Missing endpoint | Consequence in the sprint |
|---|---|
| Add institute staff | `institute_staff` and `user_roles` staff rows are created only by fictional seeders and tests, through the same services and transactions described in G3.2. |
| End institute staff | Ending staff happens only in seeders and tests, using the G3.2 transaction: `former` + `left_on`, role row deleted, audit. |
| End teacher assignment | Ending an assignment happens only in seeders and tests: set `status = 'former'` and `left_on`, plus audit. |

Adding any of these endpoints needs a contract change with approval and a changelog entry (`CLAUDE.md`). Until then, the demo cannot show these flows in the UI.

---

## G1–G3 migration order

Every FK points to a table created earlier, so the order runs in a **normal sequence with no deferred or later-added FKs**. There are no cycles: `institutes → users` is one-way, and `users` references nothing. File timestamps follow this order.

| # | Migration | Creates | FKs to |
|---|---|---|---|
| 1 | `0001_01_01_000000_create_users_table.php` (skeleton, **edited before first run**; `password_reset_tokens` removed, Q9) | `users`, `sessions` | — |
| 2 | skeleton `…000001`, `…000002` (unchanged) | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | — |
| 3 | `create_lookup_tables` | `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi` | — |
| 4 | `create_roles_table` | `roles` (+ CHECKs; + seed rows, Q4) | — |
| 5 | `create_otp_challenges_table` | `otp_challenges` (+ CHECKs) | — |
| 6 | `create_teacher_profiles_table` | `teacher_profiles` | `users` |
| 7 | `create_guardian_profiles_table` | `guardian_profiles` | `users` |
| 8 | `create_institutes_table` | `institutes` | `users` |
| 9 | `create_user_roles_table` | `user_roles` (+ generated `scope_key`, composite FK, CHECK) | `users`, `roles`, `institutes` |
| 10 | `create_institute_staff_table` | `institute_staff` (+ `active_flag`, CHECKs) | `institutes`, `users` |
| 11 | `create_teacher_institute_table` | `teacher_institute` (+ `active_flag`, CHECKs) | `teacher_profiles`, `institutes` |

- `user_roles` belongs to G2 logically but **must migrate after `institutes`** (G3), because of its `institute_id` FK.
- Writing it as a later `ALTER TABLE ADD FOREIGN KEY` is not needed.
- **Runtime atomicity:** institute creation and the owner's `institute_admin` grant happen in one transaction, in FK order: `institutes` first, then `user_roles` (G3.1).
- Staff add/end are single transactions (G3.2).
- **Hard prerequisite:** the audit events those flows write need `audit_logs`.
  - `audit_logs` is specified **first in R2**.
  - It must be migrated before **any** role grant or revoke, institute creation, or staff add or end runs, including through seeders.
  - The G1–G3 migrations themselves write no audit rows and may run before it. The **flows** may not.
- **First run** of these migrations (VP-1) happens only on the disposable `tuitionhub_test` database. `ALTER TABLE … ADD CHECK` after `Schema::create` is not rollback-safe (§0.B).

## Seed data (G1–G3)

| Table | Rows | Basis |
|---|---|---|
| `roles` | `super_admin` → `global`; `institute_admin` → `institute`; `institute_staff` → `institute`. Exactly these three. | Decided names (D-011); PROPOSAL `scope` (brief) and seeding method (Q4) |
| `mediums` | `sin` "Sinhala"; `tam` "Tamil"; `eng` "English" | `sin`/"Sinhala" Decided (contract §7.1); others PROPOSAL (canonical, draft) |
| `syllabi` | `local` "Local"; `edexcel`; `cambridge` | `local`/"Local" Decided (contract §7.1); others PROPOSAL |
| `subjects` | At least `MATH` (contract §7.1). Candidates from canonical: `PHY`, `CHEM`, `BIO`, `ICT`, `ENG`, `SIN`, `TAM`, `HIS`, `GEO` | Full list and names: Q21 |
| `grades` | At least `G11`, `AL2` (contract §8.1, §7.1). Canonical candidates: `G6`–`G11`, `AL1`, `AL2` | Full list, names, `level`, `sort_order`: Q21 |
| `streams` | At least `SCI_MATH` "Physical Science" (contract §7.1). Canonical candidates: `SCI_BIO`, `COMMERCE`, `ARTS`, `TECH` | Full list and names: Q21 |
| `exam_targets` | At least `OL_2027` "O/L 2027", `AL_2027` "A/L 2027" (contract §7.1, §8.1) | Full list, `year`, dates: Q21 |
| `users`, `institutes`, staff, teachers | **Not seeded in G1–G3.** Fictional demo seed data (C1) is a later task, written through `RoleAssignmentService`. No real people or phone numbers. | Decided (C1, C6) |

---

## Validation plan (to run later — not executed now)

All tests run on MySQL `tuitionhub_test` under the D-010 guard. "DB" tests insert directly to prove the database itself rejects bad rows; "service" tests go through `RoleAssignmentService` and the staff and assignment services.

| ID | Test | Expected |
|---|---|---|
| VP-1 | Migrate G1–G3 on MySQL 8.4.13, **only on the disposable `tuitionhub_test` database** (DDL is not rollback-safe, §0.B) | All tables, generated columns, composite FK and CHECKs are created. **Resolves §0.C-2:** for `user_roles.role_scope` and `user_roles.institute_id`, try the FK with explicit `ON DELETE RESTRICT` and with `ON DELETE` unset; record which forms MySQL accepts. Also confirms `REGEXP_LIKE` and the district `IN` list in a CHECK. If a form fails, stop and revise the design (§0.C-2) before approval. |
| VP-2 | Insert a second global `super_admin` for the same user (DB) | Duplicate-key error (`scope_key = 0` collides) |
| VP-3 | Insert a second `institute_admin` for the same user and institute (DB) | Duplicate-key error |
| VP-4 | Same user, `institute_admin` at institutes A and B (DB) | Both accepted |
| VP-5 | `super_admin` row with `institute_id` set (DB) | CHECK violation |
| VP-6 | `institute_staff` role row with `institute_id` NULL (DB) | CHECK violation |
| VP-7 | `super_admin` role id with `role_scope = 'institute'` and an institute (DB) | Composite FK violation |
| VP-8 | Insert a fourth role, or update `roles.scope` of a referenced role (DB) | CHECK violation / FK `NO ACTION` violation |
| VP-9 | Service rejects a scoped `super_admin` and an unscoped institute role; ignores client-supplied `role_scope` | Validation error; nothing written |
| VP-10 | Second **active** `institute_staff` row for the same user and institute (DB) | Duplicate-key error |
| VP-11 | Several `former` rows plus one `active` row for the same user and institute (DB) | All accepted (history) |
| VP-12 | `status = 'former'` with `left_on` NULL, or `active` with `left_on` set (DB, both tables) | CHECK violation |
| VP-13 | Second active `teacher_institute` for the same teacher and institute (DB and §6.2) | Duplicate-key error / API `409` |
| VP-14 | `left_on` earlier than `joined_on` (DB, `institute_staff` and `teacher_institute`) | CHECK violation |
| VP-15 | FK violations: `user_roles` to a missing user, role or institute; `teacher_institute` to a missing teacher profile; `institutes.owner_user_id` missing (DB) | FK error |
| VP-16 | Delete a `users`, `roles` or `institutes` row that is referenced (DB) | FK `RESTRICT` error |
| VP-17 | Institute creation fails after the institute insert (forced exception) | Neither the institute nor the owner `institute_admin` row exists (rollback) |
| VP-18 | End a staff member, then call a staff-only endpoint | `403`/`404` on the very next request; the `user_roles` staff row is gone; the `former` row is kept; an audit row exists |
| VP-19 | Staff role row present but no active `institute_staff` row (and the reverse) | No staff access |
| VP-20 | End a teacher assignment (set `former`/`left_on`), then the teacher's next request | No institute or class access; not listed in `teaching_at` / `my_access` |
| VP-21 | Re-hire or re-assign after ending | New active row accepted; old row unchanged |
| VP-22 | Role at institute A used against institute B | `403`/`404` (contract §3 "Same-institute") |
| VP-23 | OTP: `attempts > max_attempts` or `purpose = 'signup'` (DB) | CHECK violation |
| VP-24 | `users.phone` not matching `^[+]947[0-9]{8}$`; `users.status = 'pending'`; `institutes.district` outside the 25-district list (DB) | CHECK violation. A non-canonical casing such as `colombo` **passes** the CHECK (§0.1), so a separate service test proves the server stores `Colombo`. |
| VP-25 | `created_at`/`updated_at` written by Eloquent, and by a query-builder or migration seed insert (e.g. `roles`) | Both stored as UTC `DATETIME`, never NULL; read back unchanged; rendered with `Z` |
| VP-26 | `RoleAssignmentService` asked to revoke an owner's `institute_admin` grant while `institutes.owner_user_id` still references that user for that institute | Rejected; the `user_roles` row is kept; no audit "revoked" event. Revoking a non-owner admin at the same institute succeeds. |

---

## Open questions

Each item is a PROPOSAL or a gap that C1–C8, D-011/D-012 and contract v0.2 do not settle.

- **"Closed (founder direction)"** means the founders chose the outcome on 2026-10-28. It becomes an approved decision when this file is approved.
- **"Open"** items still need an answer.

| # | Question | Outcome | Status |
|---|---|---|---|
| Q1 | `DATETIME` vs `TIMESTAMP` for instants | UTC `DATETIME`, declared as explicit nullable `$table->dateTime('created_at')` / `$table->dateTime('updated_at')` (not `datetimes()`). Query-builder and migration-seed inserts must set UTC timestamps (§0.A). | Closed (founder direction) |
| Q2 | Database backstop for role scope | Kept: `roles.scope`, `roles_id_scope_unique`, `user_roles.role_scope` NOT NULL `VARCHAR(32)` with **identical charset and collation** to `roles.scope`, composite FK, `user_roles_scope_chk`, `roles_name_scope_chk`. Subject to VP-1. | Closed, subject to VP-1 |
| Q3 | Role revocation | Hard delete of the `user_roles` row, with audit | Closed (founder direction) |
| Q4 | Where `roles` is seeded | Inside its migration (with explicit UTC timestamps, Q1) | Closed (founder direction) |
| Q5 | `institute_admin` and `institute_staff` for the same user and institute | Allowed | Closed (founder direction) |
| Q6 | `users.status` values | `active\|suspended` only, default `active`; `pending` removed | Closed (founder direction) |
| Q7 | Phone CHECK | `REGEXP_LIKE(phone, '^[+]947[0-9]{8}$', 'c')`, subject to VP-1 | Closed (founder direction) |
| Q8 | `users` columns | Omit `email_verified_at` and `avatar_path`; keep `remember_token` | Closed (founder direction) |
| Q9 | `password_reset_tokens` | Removed from the edited skeleton users migration (`up()` and `down()`) | Closed (founder direction) |
| Q10 | Soft deletes on `users`, `teacher_profiles`, `guardian_profiles`, `institutes` | Removed. Disabling uses `status` where the table has one (`users`, `institutes`). | Closed (founder direction) |
| Q11 | `guardian_profiles` columns | Omit `occupation`, `preferred_contact`, `has_smartphone`; keep `uuid` | Closed (founder direction) |
| Q12 | `institutes.slug` | Not built | Closed (founder direction) |
| Q13 | `institutes.vat_no`, `logo_path`, `lat`, `lng` | Not built | Closed (founder direction) |
| Q14 | `institutes.district` | The 25 districts (G3.1); server validation + canonical spelling + `institutes_district_chk`. Confirm the `Monaragala` spelling. | Closed (founder direction) |
| Q15 | Staff dates | `joined_on DATE` (renamed from `joined_at`); `institute_staff_dates_chk` | Closed (founder direction) |
| Q16 | Staff add, no endpoint; no staff `uuid`; `position` values | Recorded under "Known sprint gaps"; seeders and tests only | Closed (founder direction) |
| Q17 | `teacher_institute.uuid`; `revenue_share_pct` | Keep `uuid`; omit `revenue_share_pct` | Closed (founder direction) |
| Q18 | Ending staff or teacher assignments, no endpoint | Recorded under "Known sprint gaps" | Closed (founder direction) |
| Q19 | OTP details: `code_hash` method (the contract fixes HMAC-SHA256 only for invitations); `max_attempts` with no DB default; `last_sent_at` NOT NULL; resend = new challenge or update; purge of expired challenges | **Fixed now:** the service increments attempts only through a guarded atomic update, `… SET attempts = attempts + 1 WHERE id = ? AND consumed_at IS NULL AND attempts < max_attempts`, and treats 0 affected rows as "attempts exhausted". `otp_challenges_attempts_chk` is the backstop. **A1 (D-019):** `code_hash` = HMAC-SHA256 under `OTP_HMAC_KEY` over `uuid\|phone\|code`; `max_attempts` set by the service from config (5); `last_sent_at` always set; resend = new challenge, older pending ones expired. | **Resolved by D-019, except purge of expired challenges (deferred)** |
| Q20 | Does MySQL 8.4.13 accept a CHECK on FK columns with explicit `ON DELETE RESTRICT`, or only with `ON DELETE` unset (`user_roles.role_scope`, `user_roles.institute_id`)? | **Explicit `ON DELETE RESTRICT` is accepted** with `user_roles_scope_chk` on MySQL 8.4 (VP-1, `tuitionhub_test`, 2026-10-28). No error; the fallback was not used (D-014). | **Resolved (VP-1)** |
| Q21 | Full lookup seed lists (codes, English/Sinhala/Tamil names, `level`, `sort_order`, exam `year` and dates) | Founders approved FD-7 (D-020): codes, English names, `level`, `sort_order` and exam `year` as in `LookupSeeder` and contract §4.6. Sinhala/Tamil names and exam dates stay NULL until supplied | **Resolved by D-020, except Sinhala/Tamil names and exam dates (open)** |
| Q22 | Lookup column details and their CHECKs | As proposed | Closed (founder direction) |
| Q23 | §0 conventions (`VARCHAR(32)` + CHECK, soft-delete policy, CHECKs, `RESTRICT`, constraint names) | As proposed, with the §0.11 VP-1 exception | Closed (founder direction) |
| Q24 | `teacher_profiles.years_experience` default `0`; draft index dropped | As proposed | Closed (founder direction) |

---

## Later groups only (R2; not specified here)

- **R2 starts with `audit_logs`** (no soft deletes; `DATETIME` UTC instants). It is a **hard prerequisite**: no role grant or revoke, institute creation, or staff add or end may run (API, seeder or test fixture) until `audit_logs` is migrated.
- **G4 Students and guardianship:** `student_profiles` (no `gender`, NIC or `school_id`: D-012 F2, C6), `guardian_invitations` (`pending_flag`, HMAC `code_hash`), `student_guardians` (`active_flag` on `revoked_at`; no SMS/marks flags: F3).
- **G5 Classes:** `classes`, `class_fee_versions`, `class_enrollments` (no `qr_token`/`student_card_no`: D-012), `class_sessions` (no `substitute_teacher_id`: F4; unique `(class_id, session_date, start_time)`).
- **G6 Attendance:** `attendances` (`method = manual` only: D-012).
- **G7 Money (demo):** `commission_rules`, `invoices` (`billing_period` storage open), `invoice_items`, `payments`, `payment_transactions`, `platform_commissions`.
- **G8 Boost (demo):** `boost_products`, `boost_purchases`.
- **Not built:** `institute_settings` (F5), and everything in contract §2.2.

---

# Part 2 — R2: `audit_logs` and G4 (APPROVED under D-015)

**Status:** **APPROVED (D-015) — Migration Specification for R2.** Approved by: Ranithu Shenal on 2026-10-28. Q25–Q37 are resolved, and the API behaviour is in contract v0.3.

- **Implementation** is a separate approved task (`CLAUDE.md` approval gate). The first run is on the disposable `tuitionhub_test` database only (VP-37).
- **Still open:** the audit IP retention period (pre-launch privacy review) and the real-money guardian billing policy (separate pre-launch review).
- **"PROPOSAL"** labels in the tables below record where each item originally came from. All Q25–Q37 items are approved by D-015.

- **Conventions:** §0 applies unchanged: UTC `DATETIME` instants written by the app, `DATE` for calendar dates, `VARCHAR(32)` + CHECK, `ON DELETE RESTRICT`, explicit constraint names, server-side canonical values, no soft deletes.
- **Labels:** "Decided" and "PROPOSAL" have the same meaning as in Part 1.
- **Prior evidence:** D-014 (VP-1) showed that MySQL 8.4 accepts a CHECK on columns whose FK is explicit `ON DELETE RESTRICT`. R2 relies on the same pattern, re-checked in VP-37.

## R2.0 `audit_logs`

Purpose: the immutable audit trail of contract §13 ("actor, target, old/new values, IP and user agent"). **Hard prerequisite:** this table must exist before any audited flow runs, including G1–G3 role, institute and staff flows (Part 1, "Later groups").

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `user_id` | BIGINT UNSIGNED | Yes | NULL | actor; NULL = system action | Decided (contract §13 "actor"; canonical) |
| `institute_id` | BIGINT UNSIGNED | Yes | NULL | tenant scope of the event, for institute-scoped queries | PROPOSAL (brief) |
| `action` | VARCHAR(64) | No | — | dotted event name from contract §13, e.g. `guardian_link.revoked` | Decided names (contract §13); PROPOSAL type |
| `auditable_type` | VARCHAR(64) | No | — | target type as a **morph alias** (e.g. `student_guardian`), never a PHP class name | Decided "target" (contract §13); PROPOSAL alias rule and NOT NULL |
| `auditable_id` | BIGINT UNSIGNED | No | — | target row id (no FK: polymorphic) | Decided "target"; PROPOSAL NOT NULL |
| `old_values` | JSON | Yes | NULL | allowlisted fields only (see rules) | Decided (contract §13) |
| `new_values` | JSON | Yes | NULL | allowlisted fields only | Decided (contract §13) |
| `ip_address` | VARCHAR(45) | Yes | NULL | request IP (IPv6-safe length) | Decided field (contract §13); PROPOSAL type |
| `user_agent` | VARCHAR(512) | Yes | NULL | truncated to 512 characters | Decided field (contract §13); PROPOSAL length |
| `created_at` | DATETIME | **No** | — | UTC; always set by the application | Decided (contract §13, §0.6) |

- **No `updated_at` or `deleted_at`** (contract §13: "immutable, no soft deletes"). The Eloquent model sets `UPDATED_AT = null`.
- **Indexes** (PROPOSAL, brief):
  - `audit_logs_user_created_index (user_id, created_at)`: actor.
  - `audit_logs_auditable_index (auditable_type, auditable_id)`: target.
  - `audit_logs_action_created_index (action, created_at)`: action.
  - `audit_logs_institute_created_index (institute_id, created_at)`: institute.
- **FKs** (both RESTRICT, so an audited user or institute can never be hard-deleted):
  - `audit_logs_user_fk (user_id) → users(id) ON DELETE RESTRICT`.
  - `audit_logs_institute_fk (institute_id) → institutes(id) ON DELETE RESTRICT` (PROPOSAL).
- **CHECKs:** none. Validity of the JSON is already enforced by the `JSON` type, and the action vocabulary lives in one code constant. A CHECK on the list would need a schema change for every new event.
- **Rules** (PROPOSAL unless marked):
  1. **Append-only, enforced by the application** for the sprint.
     - A single `AuditLogger` service is the only writer, and it only ever INSERTs.
     - The `AuditLog` model throws on `updating`/`deleting`.
     - No code path updates or deletes audit rows.
  2. **Same transaction as the business operation.**
     - The audit insert runs inside the business operation's `DB::transaction`. If either fails, both roll back (VP-36).
     - An audit row is never written for an operation that did not commit.
  3. **Never in `old_values`/`new_values`, the log channel or exception context:**
     - OTP codes or hashes, invitation codes or `code_hash` (contract §1.1, §13)
     - passwords, `remember_token`, session IDs, CSRF or API tokens, `APP_KEY`-derived values
     - **student personal data:** `full_name`, `date_of_birth`
     - **full phone numbers**. A **masked** phone in the §9.1 format (`+9477*****67`) is allowed (Q27).

     Snapshots use a **per-action allowlist**: UUIDs and IDs, status values, `relationship`, `is_primary`, masked phones, and business dates such as `linked_at`/`revoked_at`. They are never a raw model dump.
  4. **`institute_id`** is the tenant the event belongs to, for example the student's managing institute for guardian events. It is NULL for platform-level events (Q32).
  5. **`ip_address`** is stored raw in the sprint, with **restricted access**: no sprint endpoint exposes `audit_logs`, so it is readable only through direct database access. The retention period is an **unresolved pre-launch item** (pre-launch privacy review; Q28, D-015).
  6. **`auditable_type`** values are stable short aliases fixed in one morph map (e.g. `institute`, `user_role`, `institute_staff`, `teacher_assignment`, `student_profile`, `guardian_invitation`, `student_guardian`). An alias is never renamed once written (Q35).
  7. **Event vocabulary** (contract v0.3 §13): the 0.2 list plus `role.granted`, `role.revoked`, `staff.added`, `staff.ended`, `teacher_assignment.ended` and `student.created` (Q26).
  8. **Primary-guardian changes** (Q37):
     - Recorded inside the triggering event: `guardian_link.created` (initial primary) or `guardian_link.revoked` (promotion).
     - The record holds `old_values.primary_link_id` and `new_values.primary_link_id`, which are internal `student_guardians` IDs (either may be `null`).
     - No names, phones or other personal data. No separate event.
  9. **AuditLogger input rules** (future service; documented in M2, not implemented):
     - **`user_agent`** is truncated to at most 512 characters before insert.
     - **`ip_address`** is **never truncated**. A valid textual IPv4 or IPv6 address (including IPv4-mapped IPv6) fits in 45 characters.
       - The IP must come from Laravel's request handling with **correctly configured trusted proxies**, never from a raw client-supplied header.
       - It is validated as IPv4 or IPv6 with `filter_var($ip, FILTER_VALIDATE_IP)`, and a valid IP is stored **intact**.
     - **An invalid or missing IP is never silently replaced** (no placeholder, no truncation, no guessed value).
     - **Approved handling** (D-017; implemented in `App\Audit\AuditLogger`, tested by VP-57):
       - **No request** (console, seeders, system events): `ip_address` is NULL, with no warning.
       - **A request whose IP is missing or fails `filter_var`:** `ip_address` is NULL and `Log::warning('audit.ip_invalid')` is written. The warning carries only the action, the reason (`missing` or `invalid`) and the length, **never the raw value**.
       - **This never fails the business transaction.**
       - **A valid IPv4 or IPv6 address** is stored intact.
- **Alternative considered — database triggers (not selected):**
  - What they would do: `BEFORE UPDATE` and `BEFORE DELETE` triggers on `audit_logs` that `SIGNAL SQLSTATE '45000'` would make append-only a database guarantee.
  - Why they aren't selected for the sprint:
    1. With binary logging enabled (the MySQL 8.4 default), creating triggers may need privileges or settings (`log_bin_trust_function_creators`) that the restricted accounts in D-004 do not have. This would need verification.
    2. Triggers are raw DDL outside Laravel's schema builder, and are easy to miss in reviews.
    3. Removing `UPDATE`/`DELETE` privileges instead isn't possible with the current database-level grants.
  - Approved: application-level enforcement for the sprint (Q33, D-015).

## G4 — Students and guardianship

### G4.1 `student_profiles`

Purpose: an institute-managed student record (C6; contract §2.3, §8). The student user account is optional (`user_id` NULL). All demo data is fictional.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `managing_institute_id` | BIGINT UNSIGNED | No | — | FK `institutes.id`; set by the server from the route | Decided (contract §2.3, §8.1) |
| `created_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; set by the server from the session | Decided (contract §2.3, §8.1); PROPOSAL NOT NULL |
| `user_id` | BIGINT UNSIGNED | Yes | NULL | FK `users.id`; NULL in the sprint (§8.1); unique when set | Decided nullable (contract §2.3); PROPOSAL unique (draft) |
| `full_name` | VARCHAR(255) | No | — | | Decided (contract §2.3, §8.1) |
| `grade_id` | BIGINT UNSIGNED | No | — | FK `grades.id`; from `grade_code` (required) | Decided required (contract §8.1); PROPOSAL name (brief; draft `current_grade_id`) |
| `medium_id` | BIGINT UNSIGNED | No | — | FK `mediums.id`; from `medium_code` (required) | Decided required (contract §8.1); PROPOSAL FK (brief; draft stored a code) |
| `exam_target_id` | BIGINT UNSIGNED | Yes | NULL | FK `exam_targets.id`; optional | Decided optional (contract §8.1) |
| `date_of_birth` | DATE | Yes | NULL | optional; fictional only | Decided optional (contract §8.1) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `student_profiles_uuid_unique (uuid)`.
  - `student_profiles_user_id_unique (user_id)`. Multiple NULLs are allowed.
  - `student_profiles_id_institute_unique (id, managing_institute_id)`. PROPOSAL (brief): the parent key for the composite FK from `guardian_invitations`.
- **Indexes:** `student_profiles_institute_grade_index (managing_institute_id, grade_id)`. PROPOSAL: supports §8.2 listing and filtering, and the institute FK.
- **FKs** (all `ON DELETE RESTRICT`):
  - `student_profiles_institute_fk (managing_institute_id) → institutes(id)`.
  - `student_profiles_created_by_fk (created_by_user_id) → users(id)`.
  - `student_profiles_user_fk (user_id) → users(id)`.
  - `student_profiles_grade_fk (grade_id) → grades(id)`.
  - `student_profiles_medium_fk (medium_id) → mediums(id)`.
  - `student_profiles_exam_target_fk (exam_target_id) → exam_targets(id)`.
- **CHECKs:** none (no rule in an approved document).
- **Soft deletes:** none. PROPOSAL, consistent with Q10. There is no student-removal, archive or status endpoint in the sprint (Q34).
- **Derived, not stored:** `has_user_account` (`user_id IS NOT NULL`) and `active_guardian_count` (active links), both from the §8 shape.
- **Not built:** `gender` (D-012 F2), `nic` and `school_id` (C6, contract §2.3), `deleted_at`.
- **Note:** because of the composite FK, `managing_institute_id` cannot change while any `guardian_invitations` row references the student (`ON UPDATE NO ACTION`). There is no transfer flow in the sprint (PRODUCT_DECISIONS open item 4). **Student-transfer limitation** (verified by VP-50e, D-016): moving a student to another institute is blocked at the database level while invitations exist. A future transfer feature needs its own approved design, and must not drop or weaken this FK.

### G4.2 `guardian_invitations`

Purpose: a single-use, expiring code that lets a guardian link to a student (C6; contract §2.3, §9.1–§9.3).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `student_id` | BIGINT UNSIGNED | No | — | part of the composite FK | Decided (contract §2.3) |
| `institute_id` | BIGINT UNSIGNED | No | — | must equal the student's managing institute (composite FK) | Decided column (contract §2.3); PROPOSAL composite FK (brief) |
| `invited_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id` | Decided (contract §2.3) |
| `guardian_phone` | VARCHAR(20) | No | — | E.164 Sri Lankan mobile | Decided (contract §2.3, §9.1) |
| `relationship` | VARCHAR(32) | No | — | `father\|mother\|guardian\|uncle\|aunt` | Decided (contract §9.1) |
| `code_hash` | CHAR(64) | No | — | HMAC-SHA256 (hex) of the normalised code, keyed by the dedicated `GUARDIAN_INVITE_HMAC_KEY` (Q29); unique | Decided HMAC (contract §2.3); PROPOSAL dedicated key (contract v0.3), type and unique |
| `status` | VARCHAR(32) | No | `'pending'` | `pending\|accepted\|revoked\|expired` | Decided (contract §2.3) |
| `expires_at` | DATETIME | No | — | UTC; proposed 48 h after creation (configurable) | Decided (contract §2.3, §9.1) |
| `accepted_at` | DATETIME | Yes | NULL | | Decided (contract §2.3) |
| `accepted_by_user_id` | BIGINT UNSIGNED | Yes | NULL | FK `users.id`; the guardian who accepted | Decided (contract §2.3) |
| `revoked_at` | DATETIME | Yes | NULL | | Decided (contract §2.3) |
| `revoked_by_user_id` | BIGINT UNSIGNED | Yes | NULL | FK `users.id` | Decided (contract §2.3, §9.3) |
| `pending_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status = 'pending', 1, NULL)` | PROPOSAL (brief) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `guardian_invitations_uuid_unique (uuid)`.
  - `guardian_invitations_code_hash_unique (code_hash)`.
  - `guardian_invitations_pending_unique (student_id, guardian_phone, pending_flag)`. One pending invitation per student and phone (contract §9.1 `409`); any number of finished ones.
  - `guardian_invitations_id_student_unique (id, student_id)`. PROPOSAL (Q31): the parent key for the composite FK from `student_guardians`. It is logically redundant with the PK and exists only for that FK.
- **Indexes:**
  - `guardian_invitations_student_institute_index (student_id, institute_id)`. The named child index for the composite FK `guardian_invitations_student_institute_fk`. Founder-approved in M2 (D-016), so MySQL does not create an unnamed index for that FK.
  - `guardian_invitations_status_expires_index (status, expires_at)` (PROPOSAL, for a possible later expiry sweep; Q30).
  - The single-column FK indexes (invited/accepted/revoked by) are created automatically by MySQL.
- **FKs** (all `ON DELETE RESTRICT`):
  - `guardian_invitations_student_institute_fk (student_id, institute_id) → student_profiles(id, managing_institute_id)`. PROPOSAL (brief).
    - This makes an invitation whose institute is not the student's managing institute impossible at the database level (VP-27).
    - A separate single-column FK on `institute_id` is **not** added. The composite FK already guarantees the institute exists, through `student_profiles_institute_fk`.
  - `guardian_invitations_invited_by_fk (invited_by_user_id) → users(id)`.
  - `guardian_invitations_accepted_by_fk (accepted_by_user_id) → users(id)`.
  - `guardian_invitations_revoked_by_fk (revoked_by_user_id) → users(id)`.
- **CHECKs** (PROPOSAL):
  - `guardian_invitations_status_chk CHECK (status IN ('pending','accepted','revoked','expired'))`.
  - `guardian_invitations_relationship_chk CHECK (relationship IN ('father','mother','guardian','uncle','aunt'))`.
  - `guardian_invitations_phone_format_chk CHECK (REGEXP_LIKE(guardian_phone, '^[+]947[0-9]{8}$', 'c'))`.
  - `guardian_invitations_accepted_chk CHECK ((status = 'accepted' AND accepted_at IS NOT NULL AND accepted_by_user_id IS NOT NULL) OR (status <> 'accepted' AND accepted_at IS NULL AND accepted_by_user_id IS NULL))`.
  - `guardian_invitations_revoked_chk CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL) OR (status <> 'revoked' AND revoked_at IS NULL AND revoked_by_user_id IS NULL))`.
  - `guardian_invitations_accept_before_expiry_chk CHECK (accepted_at IS NULL OR accepted_at <= expires_at)`.
- **Lock order (corrected by D-022, D-1):** every write here follows Pattern I: lock the managing **institute** row first, then the **student** row, then the **invitation** row, each by primary key. Users rows are taken only with shared locks (foreign-key checks, audit rows, an explicit `FOR SHARE` re-check), never exclusively and never before the institute. **No range locking reads or range updates** on `guardian_invitations` or `student_guardians`: under REPEATABLE READ they lock index gaps, and two transactions for different students in the same gap can deadlock on their inserts. Sets of rows (for example the pending invitations for a student and phone) are found with a plain read taken after the institute lock (the institute lock serialises every writer of that institute's invitations and links, so the snapshot is current), then changed one row at a time by primary key. This replaces the earlier wording ("lock … the pending invitations with `SELECT … FOR UPDATE`"; "select the invitation by `code_hash` `FOR UPDATE`", then lock the student), which contradicted the student-first rule in G4.3.
- **Application rules** (Decided where marked "contract"; otherwise PROPOSAL):
  1. **Create** (§9.1; staff or admin of the managing institute). Everything runs in one transaction, plus the audit row:
     - Lock the institute row, then the student row (`SELECT … FOR UPDATE` by primary key). Find the pending invitations for (student, phone) with a plain read.
     - Mark any pending invitation whose `expires_at` has passed as `expired` (update by primary key), which clears its `pending_flag`.
     - If a non-expired pending invitation remains → `409` (contract).
     - Generate the code (Q29):
       - 8 characters drawn with a cryptographically secure source (`random_int`) from the alphabet `23456789ABCDEFGHJKMNPQRSTVWXYZ` (30 symbols; no `0 O 1 I L U`; about 39 bits).
       - Displayed as `XXXX-XXXX`.
       - Store only `code_hash` = HMAC-SHA256(normalised code, `GUARDIAN_INVITE_HMAC_KEY`).
       - On the very unlikely `code_hash` collision, retry with a new code.
     - Insert the row with `institute_id` = the student's managing institute.
     - Audit `guardian_invitation.created` **without** the code, hash or phone.
     - The response returns the code **once**, with `Cache-Control: no-store` (contract).
  2. **Accept** (§9.2; authenticated; throttled). All of the following run in **one transaction**:
     - **Normalise the code** (Q29):
       - Upper-case it and remove spaces and `-`.
       - If the result is not exactly 8 characters from the alphabet, return the generic `422` without querying.
       - Otherwise compute the HMAC and find the invitation's id, student and institute by `code_hash` with a **plain read, before the transaction** (no lock).
     - Redemption is rate-limited (contract §9.2: proposed 5 attempts per user per 15 min; `429`).
     - **Then lock, in this order:** the institute row, the student row, the invitation row (each by primary key), and finally the caller's and the inviter's users rows with a shared lock (`FOR SHARE`, in primary-key order). This serialises concurrent acceptances for the same student (Q25).
     - **Recheck under the lock:** status is `pending`, now < `expires_at`, the caller has `phone_verified_at` set, and the caller's phone equals `guardian_phone` (contract).
       - If the invitation has expired, it is marked `expired`.
       - Unknown, expired or used codes and phone mismatches all return one **generic `422`** (contract).
     - If an active link already exists for this student and guardian → `409` (contract; also the DB unique key).
     - Create `guardian_profiles` if missing (contract). A concurrent first acceptance by the same user is resolved by `guardian_profiles_user_id_unique`, then a locking re-read by `user_id`.
     - Insert the `student_guardians` row with `linked_by_user_id` = **inviter**, `linked_at` = now, `invitation_id` (contract).
       - `is_primary = 1` if the student has **no active primary** link; otherwise `0` (Q25).
     - Set the invitation to `accepted` / `accepted_at` / `accepted_by_user_id`.
     - Audit `guardian_invitation.accepted` and `guardian_link.created` (contract).
     - Link creation and invitation consumption commit together or not at all.
     - In `log` mode, `phone_verified_at` is **not** real phone verification (C6).
  3. **Revoke** (§9.3): lock the institute row, the student row, then the invitation row (by primary key). Only `pending` → `revoked`, setting `revoked_at`/`revoked_by_user_id`; otherwise `409` (contract). Audited.
  4. **Never log the plaintext code.** The accept request body and the create response are excluded from logs, audit rows and exception reports (contract §1.1).
  5. **HMAC key** (Q29):
     - `GUARDIAN_INVITE_HMAC_KEY` is a dedicated secret (32 random bytes, base64), read through config and kept only in local, ignored `.env` files. It is never committed, printed or logged; `.env.example` holds an empty placeholder.
     - If it is missing, **only** invitation create and accept fail closed (`503`, contract v0.3 §9.1). It is resolved lazily when those services run, not validated at boot, so unrelated Artisan commands and endpoints keep working.
     - Tests set a throwaway key at runtime; no key value is committed.
     - Rotating the key invalidates all pending codes.
  6. **Expiry is lazy** (Q30):
     - No scheduler is required.
     - On every read and action, an invitation is **effectively expired** when `expires_at <= now`, whatever its stored `status`. A stored `pending` never overrides an expired timestamp: responses show it as `expired`, accept gives the generic `422`, and revoke gives `409`.
     - The stored status is moved to `expired` whenever a write path touches the row under lock (create, accept). That transition is what clears `pending_flag`.

### G4.3 `student_guardians`

Purpose: the guardian→student link. An **active** row (`revoked_at IS NULL`) is the only source of guardian access (D-011, contract §3). History is kept.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `student_id` | BIGINT UNSIGNED | No | — | FK `student_profiles.id` | Decided (canonical, contract §2.3) |
| `guardian_id` | BIGINT UNSIGNED | No | — | FK `guardian_profiles.id`. Routes use the guardian's **user** UUID (§9.4), resolved through `guardian_profiles.user_id` | Decided (canonical) |
| `relationship` | VARCHAR(32) | No | — | copied from the invitation; `father\|mother\|guardian\|uncle\|aunt` | Decided (contract §9.1, §9.5) |
| `is_primary` | BOOLEAN (TINYINT(1)) | No | `0` | used by §11.2 `billed_guardian_id`; set **only** by the Q25 rules (first active link; promotion on revocation) | Decided concept (contract §11.2; canonical); PROPOSAL default and rules (D-015) |
| `linked_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; the **inviter** (contract §9.2) | Decided (contract §2.3, §9.2) |
| `linked_at` | DATETIME | No | — | UTC | Decided (contract §2.3) |
| `revoked_by_user_id` | BIGINT UNSIGNED | Yes | NULL | FK `users.id` | Decided (contract §2.3, §9.4) |
| `revoked_at` | DATETIME | Yes | NULL | UTC | Decided (contract §2.3, §9.4) |
| `invitation_id` | BIGINT UNSIGNED | Yes | NULL | composite FK with `student_id` to `guardian_invitations(id, student_id)`; unique | Decided column (contract §2.3); PROPOSAL nullable, unique, composite FK (Q31) |
| `active_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(revoked_at IS NULL, 1, NULL)` | PROPOSAL (brief) |
| `primary_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(is_primary = 1 AND revoked_at IS NULL, 1, NULL)` | PROPOSAL (brief) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Generated columns** (declared in this order, after their base columns):
  - `active_flag TINYINT UNSIGNED GENERATED ALWAYS AS (IF(revoked_at IS NULL, 1, NULL)) STORED`.
  - `primary_flag TINYINT UNSIGNED GENERATED ALWAYS AS (IF(is_primary = 1 AND revoked_at IS NULL, 1, NULL)) STORED`.
  - Neither base column has an FK, so the §0.C-3 limits don't apply.
- **Unique:**
  - `student_guardians_active_unique (student_id, guardian_id, active_flag)`. One **active** link per student and guardian (contract §2.3), with any number of revoked ones.
  - `student_guardians_primary_unique (student_id, primary_flag)`. At most one **active primary** guardian per student. Revoking the primary link frees the slot automatically.
  - `student_guardians_invitation_unique (invitation_id)`. One invitation creates at most one link. Multiple NULLs are allowed.
- **Indexes:**
  - `student_guardians_guardian_active_index (guardian_id, active_flag)`. PROPOSAL: supports §9.5 "my children" and per-request access checks; it also serves the `guardian_id` FK. The student-side lookups use the leftmost columns of the active unique key.
  - `student_guardians_invitation_student_index (invitation_id, student_id)`. PROPOSAL (Q31): the child index for the composite invitation FK. MySQL needs the FK columns as the leading columns in that order; the single-column unique key on `invitation_id` does not qualify.
- **FKs** (all `ON DELETE RESTRICT`):
  - `student_guardians_student_fk (student_id) → student_profiles(id)`.
  - `student_guardians_guardian_fk (guardian_id) → guardian_profiles(id)`.
  - `student_guardians_linked_by_fk (linked_by_user_id) → users(id)`.
  - `student_guardians_revoked_by_fk (revoked_by_user_id) → users(id)`.
  - `student_guardians_invitation_student_fk (invitation_id, student_id) → guardian_invitations(id, student_id)`. PROPOSAL (Q31). This replaces a single-column FK on `invitation_id`.
    - **Effect:** a link that names an invitation must belong to **that invitation's student**. A mismatch fails with 1452 (VP-43).
    - **NULL behaviour:** MySQL implements `MATCH SIMPLE` semantics: a composite FK value that is all or partly NULL is not checked against the parent (MySQL 8.4 manual, "FOREIGN KEY Constraint Differences").
      - A link with `invitation_id = NULL` is therefore accepted without an invitation. Its `student_id` is still checked by `student_guardians_student_fk`.
      - The sprint has no flow that creates a link without an invitation; only fictional seed or test fixtures might (VP-44).
    - The parent key is `guardian_invitations_id_student_unique`.
- **CHECKs** (PROPOSAL, brief):
  - `student_guardians_relationship_chk CHECK (relationship IN ('father','mother','guardian','uncle','aunt'))`.
  - `student_guardians_is_primary_chk CHECK (is_primary IN (0, 1))`.
  - `student_guardians_revocation_chk CHECK ((revoked_at IS NULL AND revoked_by_user_id IS NULL) OR (revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL))`. Revocation actor and time are set together.
  - `student_guardians_revoked_after_linked_chk CHECK (revoked_at IS NULL OR revoked_at >= linked_at)`.
- **History rules** (Decided by C6 and contract §2.3, §9.4; PROPOSAL where marked):
  - **Revoked links are never deleted and never reactivated.** No code path sets `revoked_at` back to NULL (application rule; a CHECK cannot compare old and new values).
  - **Re-linking always creates a new row** through a new invitation. Revoked rows stay as history.
  - This replaces the draft's unique `(student_id, guardian_id)` with reactivation, which would overwrite revocation history.
  - **Revoke** (§9.4; institute admin or `super_admin`). Everything runs in one transaction:
    - **Lock the institute row, then the student row** (`SELECT … FOR UPDATE`), then the link row by primary key (found with a plain read after the locks; D-022).
    - Set `revoked_at` = now and `revoked_by_user_id`, which clears `active_flag` and `primary_flag`.
    - Promote a new primary if needed (below).
    - Audit `guardian_link.revoked`.
  - **Access** is checked against an active row on **every request** (contract §3, §9.4: "lost immediately"). Nothing is cached in the session.
  - **Primary guardian** (Q25, approved D-015; contract v0.3 §9.2, §9.4):
    - **The student row is locked** (`SELECT … FOR UPDATE`, taken before any link row) for **every** primary-guardian creation, revocation and promotion, right after the managing institute's row (D-022 Pattern I). Institute, then student, is always the first pair of locks, so concurrent operations on the same student run one at a time and cannot deadlock.
    - **First active guardian becomes primary.** On acceptance, with the student row locked, the new link gets `is_primary = 1` if the student has no active primary. `guardian_link.created` then records old primary link ID `null` and the new link's ID (Q37).
    - **Promotion on revocation:**
      - When the primary link is revoked, the same transaction (student row locked) first sets `revoked_at`, which frees `primary_flag`.
      - It then sets `is_primary = 1` on the **oldest remaining active link**, ordered by `linked_at`, then `id`.
      - `guardian_link.revoked` records the old primary link ID (the revoked link) and the new primary link ID (the promoted link, or `null`). These are internal IDs only, with no personal data (Q37).
    - **No active guardian left** → the student has no primary, and new invoices get `billed_guardian_id = NULL` (contract §11.2).
    - **Existing invoices keep** their original `billed_guardian_id` (snapshot).
    - **No manual primary-selection API.** `is_primary` is never set from request input.
    - The order (revoke, then promote) and the student-row lock prevent `student_guardians_primary_unique` conflicts and races (VP-41, VP-42).
    - **Unchanged constraints:** `student_guardians_active_unique` and `student_guardians_primary_unique` stay the database backstops. Invitations remain **single-use**: `guardian_invitations.code_hash` is unique, `student_guardians_invitation_unique` allows one link per invitation, and the accept transaction moves the invitation to `accepted` under lock.
  - **Billing access** (Q36, D-015; contract v0.3 §11.5, §11.6): any guardian with an **active** link may view the student's invoices and start a demo payment, whatever the invoice's `billed_guardian_id` (an immutable addressee snapshot). This covers the demo only; the real-money policy needs a separate pre-launch review.
- **Not built:** `status` (redundant with `revoked_at`); `receives_attendance_sms`, `receives_payment_sms`, `receives_marks_notifications` (D-012 F3).

## R2 migration order

The R2 migrations are **appended after** `2026_10_29_000009`. The M1 migrations are not modified (append-only, D-014). Each FK points to an earlier table, and there are no cycles or deferred FKs.

| # | Migration | Creates | FKs to |
|---|---|---|---|
| 12 | `2026_10_29_000010_create_audit_logs_table.php` | `audit_logs` | `users`, `institutes` |
| 13 | `2026_10_29_000011_create_student_profiles_table.php` | `student_profiles` (+ `(id, managing_institute_id)` unique) | `institutes`, `users`, `grades`, `mediums`, `exam_targets` |
| 14 | `2026_10_29_000012_create_guardian_invitations_table.php` | `guardian_invitations` (+ `pending_flag`, `(id, student_id)` unique, CHECKs) | `student_profiles` (composite), `users` |
| 15 | `2026_10_29_000013_create_student_guardians_table.php` | `student_guardians` (+ `active_flag`, `primary_flag`, invitation index, CHECKs) | `student_profiles`, `guardian_profiles`, `users`, `guardian_invitations` (composite) |

The filename prefix and sequence are a PROPOSAL, chosen so they sort after M1. As in Part 1, first runs happen only on the disposable `tuitionhub_test` (§0.B).

## R2 validation plan (to run later — not executed)

Cases are numbered after VP-26. "DB" cases are direct inserts asserting the MySQL code and constraint name; "Service" cases need the services or endpoints.

| ID | Kind | Test | Expected |
|---|---|---|---|
| VP-27 | DB | Invitation whose `institute_id` is not the student's `managing_institute_id` | 1452 `guardian_invitations_student_institute_fk` |
| VP-28 | DB + Service | Second **pending** invitation for the same student and phone; then again after the first is `expired`/`revoked`/`accepted` | 1062 `guardian_invitations_pending_unique` / API `409`; afterwards accepted |
| VP-29 | DB + Service | Redeem the same invitation twice | API: second accept `422` (generic), only one link. DB: a second link with the same `invitation_id` → 1062 `student_guardians_invitation_unique` |
| VP-30 | Service | Accept with a caller whose verified phone ≠ `guardian_phone`, or with `phone_verified_at` NULL | Generic `422`; no link; invitation stays `pending` |
| VP-31 | DB + Service | Second **active** link for the same student and guardian | 1062 `student_guardians_active_unique` / API `409` |
| VP-32 | DB | Several revoked links plus one active link for the same student and guardian | All accepted; history retained |
| VP-33 | DB | Two active links with `is_primary = 1` for one student; then revoke one and add another primary | 1062 `student_guardians_primary_unique`; after the revocation, accepted |
| VP-34 | Service | Revoke a link (§9.4), then the guardian's next request to §9.5, §10.5 and §11.4 | Student absent or `404` immediately; revoked row still present |
| VP-35 | Service | Create and accept an invitation, then inspect `audit_logs` JSON and the log output | No code, `code_hash`, HMAC key, OTP, token, **full** phone, `full_name` or `date_of_birth` anywhere; a masked phone (`+9477*****67`) is allowed |
| VP-36 | Service | Force a failure after the audit insert (and, separately, make the audit insert fail) inside a business transaction | Neither the business rows nor the audit row exist |
| VP-37 | DB | Migrate R2 on `tuitionhub_test` (the VP-1 equivalent): composite FK, generated columns, and CHECKs on FK columns (`accepted_by_user_id`, `revoked_by_user_id`) | All created. Any MySQL error → stop and report |
| VP-38 | DB | CHECK violations: relationship `other`; `is_primary = 2`; `revoked_at` without `revoked_by_user_id`; `revoked_at < linked_at`; `status = 'accepted'` without `accepted_at`; `accepted_at > expires_at`; bad `guardian_phone` | 3819 with the named CHECK |
| VP-39 | Service | Accept an expired invitation | Generic `422`; invitation becomes `expired`; no link |
| VP-40 | Service | Update or delete an `audit_logs` row through the model | Exception; row unchanged |
| VP-41 | Service | Primary promotion: links A (oldest), B and C are active and A is primary; revoke A; then revoke B; then revoke C | After A: B is primary (ties on `linked_at` broken by `id`). After B: C is primary. After C: no primary. The revoke audit rows record each promotion; existing invoices keep their `billed_guardian_id` |
| VP-42 | Service | Two acceptances for different guardians of the same student, run concurrently | Both links are created, and exactly one is primary (student-row lock); no `student_guardians_primary_unique` error reaches the caller |
| VP-43 | DB | `student_guardians` row whose `invitation_id` belongs to a **different** student | 1452 `student_guardians_invitation_student_fk` |
| VP-44 | DB | `student_guardians` row with `invitation_id = NULL` | Accepted (`MATCH SIMPLE`); `student_id` still checked by `student_guardians_student_fk` |
| VP-45 | Service | `GUARDIAN_INVITE_HMAC_KEY` unset: create and accept an invitation; also run an unrelated endpoint and an Artisan command | Invitation endpoints `503` (fail closed); unrelated endpoint and command unaffected; the key never appears in output or logs |
| VP-46 | Service | Code normalisation: accept `7kq2 m9xd` and `7KQ2-M9XD` for code `7KQ2M9XD`; then `7KQ2-M9X` and `0KQ2-M9XD` | The first two match. The malformed ones get the generic `422` with no database lookup |
| VP-47 | Service | Invitation stored `pending` with `expires_at` in the past: read it, accept it, revoke it | Shown as `expired`; accept `422`; revoke `409`; a new invitation for the same student and phone succeeds (the old row is moved to `expired` under lock) |
| VP-48 | Service | Q36 billing access: student with active links A (the billed guardian) and B, plus a revoked link C. B views the invoice and starts a demo payment; C tries both; then A is revoked and B tries again | B: `200`/`201`. C: `404`. After A's revocation, B can still view and pay; the invoice's `billed_guardian_id` is unchanged |
| VP-49 | Service | Q37 audit content: the first acceptance (initial primary) and a primary revocation that promotes another link | `guardian_link.created` holds `primary_link_id` old `null` → new = the new link's ID. `guardian_link.revoked` holds old = the revoked link's ID → new = the promoted link's ID (or `null`). No names or phones in either row |
| VP-50 | DB | G4 FK violations and parent protection: (a) student parents must exist (6 FKs); (b) `student_profiles.user_id` is unique but may be NULL many times; (c) an institute with students cannot be deleted; (d) invitation actor users must exist (3 FKs); (e) a student's `managing_institute_id` cannot change while an invitation references it; (f) link parents must exist, and a linked student or guardian cannot be deleted | 1452 / 1062 `student_profiles_user_id_unique` / 1451, each with the named FK |
| VP-51 | DB | `audit_logs` constraints: (a) structure: no `updated_at`/`deleted_at`, `created_at` NOT NULL with no default, JSON columns; (b) `created_at` required; (c) invalid JSON rejected; (d) actor and institute must exist; (e) an audited user cannot be deleted; (f) a system event with NULL actor and institute is accepted | (b) 1364 `created_at`; (c) 3140 `new_values`; (d) 1452 `audit_logs_user_fk` / `audit_logs_institute_fk`; (e) 1451 `audit_logs_user_fk`; (f) accepted |
| VP-52 | DB | Two invitations with the same `code_hash` | 1062 `guardian_invitations_code_hash_unique` |
| VP-53 | DB | Writing a generated column directly: (a) `guardian_invitations.pending_flag`; (b) `student_guardians.active_flag` and `primary_flag` | 3105, naming the column |
| VP-54 | Model | Public UUIDs (`HasPublicUuid`): `UserFactory` creates a random **v4** UUID; a supplied UUID is replaced, including on `saveQuietly()`; changing it throws; `id` stays an incrementing integer key; route key is `uuid`; malformed or numeric route values resolve to nothing **without a query**; `id` and internal FKs are not serialized | As described |
| VP-55 | Model | Date serialization: instants render as `YYYY-MM-DDTHH:MM:SSZ` with no fractional seconds; an instant given in another zone (e.g. Asia/Colombo) is converted to UTC before it is stored and rendered; calendar dates render as `YYYY-MM-DD` | As described |
| VP-56 | Model | Morph map: audit targets use the approved aliases (`institute`, `user_role`, `institute_staff`, `teacher_assignment`, `student_profile`, `guardian_invitation`, `student_guardian`); an unmapped model throws `ClassMorphViolationException` | As described |
| VP-57 | Service | AuditLogger input rules: alias, actor and institute recorded; denylisted keys (codes, hashes, passwords, tokens, student name or DOB, full phones) throw **before** the allowlist filter and before any write; non-allowlisted keys dropped; an action without an allowlist refused; user agent limited to 512 characters; valid IPv4/IPv6 stored intact; invalid or missing IP from a request → NULL plus a warning without the raw value; no request → NULL with no warning | As described |
| VP-58 | Model | Generated columns (`scope_key`, `active_flag`, `pending_flag`, `primary_flag`) are refused through Eloquent before any query; seeded roles are read-only | `LogicException`; database unchanged |
| VP-59 | Service | District canonicalization: any casing or spacing returns the canonical spelling (`colombo` → `Colombo`); `Moneragala` is accepted as an alias for `Monaragala`; unknown districts are rejected; `InstituteService` stores the canonical value | Completes the VP-24 service part |

## R2 decisions (D-015)

All items were approved by Ranithu Shenal on 2026-10-28 (D-015). The two pre-launch items at the end remain **unresolved**.

| # | Topic | Decision | Status |
|---|---|---|---|
| Q25 | Primary guardian selection | The first active guardian becomes primary. When the primary is revoked, the oldest remaining active link (`linked_at`, then `id`) is promoted in the same transaction. The **student row is locked** for creation, revocation and promotion. No active guardian → no primary. Existing invoices keep their `billed_guardian_id`. No manual primary-selection API (contract v0.3 §9.2, §9.4, §11.2) | **Approved (D-015)** |
| Q26 | Additional audit events | `role.granted`, `role.revoked`, `staff.added`, `staff.ended`, `teacher_assignment.ended`, `student.created` (contract v0.3 §8.1, §13) | **Approved (D-015)** |
| Q27 | Phones in audit snapshots | No full phone numbers; the masked form (`+9477*****67`) only | **Approved (D-015)** |
| Q28 | `audit_logs.ip_address` | Raw IP in the sprint, with restricted access (no endpoint exposes audit rows) | **Approved (D-015)**; retention period **unresolved pre-launch item** |
| Q29 | Invitation code | 8 characters from `23456789ABCDEFGHJKMNPQRSTVWXYZ`, secure random source, displayed `XXXX-XXXX`. Normalised by upper-casing and removing **spaces and hyphens** before HMAC-SHA256 with the dedicated `GUARDIAN_INVITE_HMAC_KEY`. A missing key fails closed (`503`) for invitation flows only. Redemption rate-limited; the key is never printed or committed | **Approved (D-015)** |
| Q30 | Expiry | Lazy; no scheduler. Every read checks `expires_at`; a stored `pending` never overrides an expired timestamp | **Approved (D-015)** |
| Q31 | Invitation/student integrity | Composite FK `(invitation_id, student_id)` → `guardian_invitations(id, student_id)`, with parent unique `guardian_invitations_id_student_unique` and child index `student_guardians_invitation_student_index`. A NULL `invitation_id` is not checked (`MATCH SIMPLE`) | **Approved (D-015)** |
| Q32 | `audit_logs.institute_id` for platform events | May be NULL for platform-level events; otherwise the target's institute | **Approved (D-015)** |
| Q33 | Append-only enforcement | Application-enforced for the sprint; no triggers | **Approved (D-015)** |
| Q34 | Student removal | No student-removal, archive, status or soft delete in the sprint | **Approved (D-015)** |
| Q35 | Audit target types | Stable short aliases (morph map), never class names; `auditable_*` NOT NULL | **Approved (D-015)** |
| Q36 | Guardian billing access (demo) | **Any actively linked guardian** may view a student's invoice and start a demo payment, regardless of `billed_guardian_id` (contract v0.3 §11.5, §11.6, §15) | **Approved (D-015)** |
| Q37 | Auditing primary changes | Recorded within `guardian_link.created` or `guardian_link.revoked`, with the old and new primary guardian-link IDs and no personal data | **Approved (D-015)** |
| — | Audit IP retention period | Pre-launch privacy review | **Unresolved (pre-launch)** |
| — | Real-money guardian billing policy | Separate pre-launch review; Q36 covers the demo only | **Unresolved (pre-launch)** |
| — | AuditLogger handling of an invalid or missing IP | No request → NULL, no warning. Missing or invalid from a request → NULL plus a warning without the raw value. The business transaction never fails (R2.0 rule 9) | **Approved (D-017)** |

---

# Part 3 — R3: G5 Classes and G6 Attendance (DRAFT)

**Status:** **APPROVED (D-024) by TuitionHub founders on 2026-11-01. Migrated: M3 verified on `tuitionhub_test` (D-025) and applied to the development database `tuitionhub` on 2026-11-01 (batch 3).** Prepared 2026-11-01 on `sprint/day1-api-scaffold` (baseline `ca3d8a9`).

- **Sources, highest first:**
  - contract v0.7: §7 classes, §8.3–§8.4 enrolments, §10 sessions and attendance, §11.2 fee selection, §13 audit;
  - D-022/D-023 (lock order);
  - the R3 brief (2026-11-01);
  - the "R1 design notes — not yet approved" in `DECISION_LOG.md`, which are proposals only;
  - the sprint draft §3.12–§3.19 and the ER schema, both historical.
- **Labels:** as in Part 1. **PROPOSAL (R3 brief)** and **PROPOSAL** record where each item came from. **Every item in Part 3, and Q38–Q56, is approved by D-024.**
- **Conventions:** §0 applies unchanged.
  - UTC `DATETIME` instants; `DATE` and `TIME` in `Asia/Colombo`.
  - `VARCHAR(32)` plus a `CHECK` for vocabularies, with server-canonicalised spelling (§0.1).
  - `ON DELETE RESTRICT`, `ON UPDATE` unset, no `CASCADE`/`SET NULL`.
  - Explicit constraint names.
  - **No soft deletes** (§0.8). Classes are retired with `status = archived`; no G5/G6 row is ever deleted by the application.
- **Not built** (D-012, contract §2.3): `classes.hall_id` and `slug`; `class_enrollments.qr_token` and `student_card_no`; `class_sessions.substitute_teacher_id` (F4), `cancellation_reason` and `online_meeting_*`; `attendances.device_id`; every `deleted_at`.

### Contradictions found between the sources (each resolved by D-024 below)

| # | Point | Sources disagree | Part 3 uses |
|---|---|---|---|
| X1 | Enrolment start | The R3 brief says `enrolled_at`; contract §8.3 and the draft say `enrolled_on` (a date) | `enrolled_on DATE`, following the contract (Q38) |
| X2 | Cancelled session slot | Contract §10.1: duplicate (class, date, start time) → `409`. The R1 note says that as written, a cancelled session does **not** free its slot. The brief makes the slot unique among non-cancelled sessions only | The brief's rule; contract §10.1 needs a clarifying note (Q39) |
| X3 | `max_students` | Draft: nullable, NULL meaning no cap. Contract: ≥ 1. Brief: NOT NULL | `NOT NULL`, `CHECK ≥ 1` |
| X4 | Fee amount | Draft: ≥ 0. Contract §7.1: > 0 | `CHECK > 0` |
| X5 | Fee immutability | Brief and draft: immutable. Contract §7.4 closes the previous version's `effective_to` | Everything is immutable except `effective_to`, which may be set once, from NULL (Q43) |
| X6 | Status vocabularies | Draft enrolment: `active\|paused\|withdrawn\|completed`; draft session: `scheduled\|in_progress\|conducted\|cancelled\|rescheduled`. The contract names only `active`/`withdrawn_on` and `scheduled`/`cancelled` | `active\|withdrawn` and `scheduled\|cancelled` (Q40) |
| X7 | Missing endpoints | §10.3 refuses attendance on cancelled sessions, and §8.3/§11.2 depend on an enrolment being active, but no endpoint cancels a session or withdraws an enrolment | The schema supports both; the endpoints are proposed for E3 in contract v0.7.1 §8.5 and §10.6 (Q41) |

## G5 — Classes

### G5.1 `classes`

Purpose: a class an institute runs, with one assigned teacher (contract §3, §7).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (§0.3) |
| `institute_id` | BIGINT UNSIGNED | No | — | FK `institutes.id`; from the route, never changes | Decided (contract §7.1) |
| `teacher_id` | BIGINT UNSIGNED | No | — | FK `teacher_profiles.id`; must have an **active assignment** at `institute_id` (service rule) | Decided (contract §3, §7.1) |
| `subject_id`, `grade_id`, `exam_target_id`, `medium_id`, `syllabus_id` | BIGINT UNSIGNED | No | — | FKs to the G1 lookups; from exact codes (D-021) | Decided (contract §7.1) |
| `stream_id` | BIGINT UNSIGNED | Yes | NULL | FK `streams.id` | Decided (contract §7.1) |
| `title` | VARCHAR(255) | No | — | free-text rules §1.6 | Decided (contract §7); type PROPOSAL (draft) |
| `description` | TEXT | Yes | NULL | multi-line free text §1.6 | Decided (contract §7) |
| `mode` | VARCHAR(32) | No | — | `in_person\|online\|hybrid` | Decided (contract §7.1) |
| `max_students` | INT UNSIGNED | No | — | `≥ 1` | Decided `≥ 1` (contract); NOT NULL PROPOSAL (R3 brief) |
| `starts_on` | DATE | No | — | | Decided (contract §7.1) |
| `ends_on` | DATE | Yes | NULL | `≥ starts_on` | Decided nullable (contract); CHECK PROPOSAL (R3 brief) |
| `status` | VARCHAR(32) | No | `'draft'` | `draft\|active\|archived` | Decided (contract §7.1, §7.4) |
| `public_listing` | BOOLEAN (TINYINT(1)) | No | `0` | public only when `active` and the institute is active (§7.5) | Decided (contract §7.5); default PROPOSAL (R3 brief, draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `classes_uuid_unique (uuid)`.
  - `classes_id_institute_unique (id, institute_id)`. PROPOSAL (R3 brief): the parent key for the enrolment composite FK.
- **Indexes** (PROPOSAL):
  - `classes_institute_status_index (institute_id, status)`: §7.2 lists; also serves the institute FK.
  - `classes_teacher_status_index (teacher_id, status)`: teacher views; also serves the teacher FK.
  - `classes_catalogue_index (status, public_listing, subject_id, grade_id)`: §7.5.
  - The other lookup FK indexes are created automatically.
- **FKs** (all `ON DELETE RESTRICT`):
  - `classes_institute_fk`, `classes_teacher_fk`.
  - `classes_subject_fk`, `classes_grade_fk`, `classes_stream_fk`, `classes_exam_target_fk`, `classes_medium_fk`, `classes_syllabus_fk`.
- **CHECKs** (PROPOSAL):
  - `classes_status_chk CHECK (status IN ('draft','active','archived'))`.
  - `classes_mode_chk CHECK (mode IN ('in_person','online','hybrid'))`.
  - `classes_max_students_chk CHECK (max_students >= 1)`.
  - `classes_dates_chk CHECK (ends_on IS NULL OR ends_on >= starts_on)`.
  - `classes_public_listing_chk CHECK (public_listing IN (0, 1))`.
- **Not a database rule:** that the teacher's assignment is active. `teacher_institute` has no unique key on (teacher, institute), because history rows exist, so there is nothing for an FK to reference.
- **Application rules** (Decided where marked "contract"; otherwise PROPOSAL):
  1. **Create** (§7.1). Everything runs in one transaction, plus the audit rows:
     - Lock the institute, then re-authorize (owner or admin; a suspended institute → `403 institute_suspended`).
     - Check, with plain reads after the lock (every writer of `teacher_institute` holds the institute lock), that the teacher has an active assignment there (contract).
     - Lookup codes must match exactly. `status` must be `draft` or `active` (contract). Check the date rule.
     - Insert the class and its first fee version (G5.2). Audit `class.created` and `fee.version_created` (contract).
  2. **Update** (§7.4): lock the institute, then the class, and re-authorize.
     - A new teacher needs an active assignment.
     - `max_students` below the active enrolment count (counted under the class lock) → `409` (contract).
     - A fee change follows G5.2. Status transitions are an open question (Q46).
  3. **Teacher access** to class data also needs that teacher's assignment to still be active (contract §3). An ended assignment does not change `teacher_id`; the class keeps it until §7.4 reassigns it.

### G5.2 `class_fee_versions`

Purpose: the fee history of a class (contract §7.1, §7.4, §11.2). Invoices snapshot the amount, so changing a fee never changes an issued invoice.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `class_id` | BIGINT UNSIGNED | No | — | FK `classes.id` | Decided (draft, contract) |
| `amount` | DECIMAL(15,2) | No | — | `> 0`; institute tuition only, without the platform fee | Decided (contract §7.1, §11.1) |
| `currency` | CHAR(3) | No | `'LKR'` | `'LKR'` only | Decided (contract §1.5) |
| `billing_cycle` | VARCHAR(32) | No | — | `monthly\|term\|one_time` | Decided (contract §7.1) |
| `effective_from` | DATE | No | — | | Decided (contract §7.1) |
| `effective_to` | DATE | Yes | NULL | NULL while current; set **once** when the next version is created | Decided (contract §7.4) |
| `current_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(effective_to IS NULL, 1, NULL)` | PROPOSAL (R3 brief) |
| `created_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; from the session | Decided (contract §7.1) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | `updated_at` changes only when `effective_to` is closed | PROPOSAL (§0.6) |

- **No `uuid`:** a fee version is never addressed by any route (Q43).
- **Unique:**
  - `class_fee_versions_current_unique (class_id, current_flag)`: at most one open version per class (R3 brief).
  - `class_fee_versions_class_from_unique (class_id, effective_from)` (R1 note).
- **FKs:** `class_fee_versions_class_fk`, `class_fee_versions_created_by_fk`.
- **CHECKs** (PROPOSAL):
  - `class_fee_versions_amount_chk CHECK (amount > 0)`.
  - `class_fee_versions_currency_chk CHECK (currency = 'LKR')`.
  - `class_fee_versions_cycle_chk CHECK (billing_cycle IN ('monthly','term','one_time'))`.
  - `class_fee_versions_dates_chk CHECK (effective_to IS NULL OR effective_to >= effective_from)`.
- **Application rules:**
  1. **Append-only** (contract §7.4). `amount`, `currency`, `billing_cycle`, `effective_from` and `created_by_user_id` never change. `effective_to` changes once, from NULL to a date. The model refuses any other change before a query runs, in the style of `GuardsGeneratedColumns` (PROPOSAL).
  2. **New version, under the class lock:**
     - A plain read finds the open version.
     - The new `effective_from` must be after the open version's `effective_from` (contract), otherwise `422`.
     - The open version's `effective_to` is set to the day before, by primary key, and the new version is inserted with `effective_to = NULL`.
     - So versions are contiguous and never overlap. The database enforces one open version and distinct start dates; non-overlap is a service rule and is tested (VP-76).
  3. **Fee for a date `d`** (invoice `issued_on`, §11.2): `effective_from <= d AND (effective_to IS NULL OR effective_to >= d)`. This matches at most one row. Before the first version's start → `422` (contract).
  4. **`current_fee` in the `Class` shape:** the version in force today (`Asia/Colombo`), or, before any is in force, the earliest future one (Q54).

### G5.3 `class_enrollments`

Purpose: a student's place in a class (contract §8.3). History is kept: a withdrawal never deletes or reactivates a row.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §8.3) |
| `class_id` | BIGINT UNSIGNED | No | — | part of both composite FKs' tenant check | Decided (draft, contract) |
| `student_id` | BIGINT UNSIGNED | No | — | | Decided (draft, contract) |
| `institute_id` | BIGINT UNSIGNED | No | — | must equal the class's institute **and** the student's managing institute (two composite FKs) | **PROPOSAL (R3 brief)**: new column, tenant invariant |
| `status` | VARCHAR(32) | No | `'active'` | `active\|withdrawn` | Decided `active` (contract §8.3); values PROPOSAL (Q40) |
| `enrolled_on` | DATE | No | — | | Decided (contract §8.3; X1) |
| `withdrawn_on` | DATE | Yes | NULL | set together with `withdrawn` | Decided (contract §8.3) |
| `active_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status = 'active', 1, NULL)` | Decided pattern (§0.9); **R3 brief**: keyed on `status`, so every historical row is NULL |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `class_enrollments_uuid_unique (uuid)`.
  - `class_enrollments_active_unique (class_id, student_id, active_flag)`: **one active enrolment** per class and student; any number of withdrawn ones (R3 brief).
  - `class_enrollments_id_class_unique (id, class_id)`. PROPOSAL (R3 brief): the parent key for the attendance composite FK.
- **Indexes:**
  - `class_enrollments_class_institute_index (class_id, institute_id)` and `class_enrollments_student_institute_index (student_id, institute_id)`.
  - These are the named child indexes for the two composite FKs, as in G4.2. The second also serves "a student's enrolments".
- **FKs** (both `ON DELETE RESTRICT`; no separate single-column FKs, as in G4.2, because both composites are NOT NULL):
  - `class_enrollments_class_institute_fk (class_id, institute_id) → classes(id, institute_id)`, parent `classes_id_institute_unique`.
  - `class_enrollments_student_institute_fk (student_id, institute_id) → student_profiles(id, managing_institute_id)`, parent `student_profiles_id_institute_unique`, which already exists (G4.1).
  - **Effect:** an enrolment whose class and student belong to different institutes cannot exist (VP-60).
  - **Consequence:** a student's `managing_institute_id` and a class's `institute_id` cannot change while enrolments exist (`ON UPDATE NO ACTION`). That matches the G4.1 transfer limitation.
- **CHECKs** (PROPOSAL):
  - `class_enrollments_status_chk CHECK (status IN ('active','withdrawn'))`.
  - `class_enrollments_withdrawal_chk CHECK ((status = 'active' AND withdrawn_on IS NULL) OR (status = 'withdrawn' AND withdrawn_on IS NOT NULL))`.
  - `class_enrollments_dates_chk CHECK (withdrawn_on IS NULL OR withdrawn_on >= enrolled_on)`.
- **Application rules:**
  1. **Create** (§8.3): lock the institute, then the class; re-authorize (same-institute staff or admin; suspended → `403`).
     - The class must be `active` (contract).
     - The student must be managed by the same institute; otherwise `404` (contract), with the composite FK as backstop.
     - Active count `≥ max_students` → `409` (contract). An existing active enrolment → `409` (contract); the unique key is the backstop and is mapped **without chaining** the database error (D-022).
     - **`enrolled_on`** (D-024): may be in the future, but must be `>= classes.starts_on` and, when the class has one, `<= classes.ends_on`; otherwise `422`.
     - Audited `enrollment.created` (contract).
  2. **Withdraw** (proposed E3 endpoint, contract v0.7.1 §8.5; Q41): status `withdrawn` and `withdrawn_on`, under the institute and class locks. **Re-enrolling creates a new row**, which the unique key allows once the old row is withdrawn (VP-63, VP-64).
     - **Withdrawal date safeguard** (D-024): a requested withdrawal date **before** `enrolled_on` is stored as `withdrawn_on = enrolled_on`. That is a zero-length enrolment: it was never active on any date (rule 3), and `class_enrollments_dates_chk` still holds (VP-81).
     - **Attendance safeguard** (D-024): a withdrawal date that would leave existing attendance outside the enrolment's active period, i.e. any attendance row whose session date is `>= withdrawn_on`, is refused with `409`. Attendance history is never invalidated (VP-82).
  3. **Active on date `d`** (attendance, §10.3): `enrolled_on <= d AND (withdrawn_on IS NULL OR d < withdrawn_on)`. `withdrawn_on` is the first day not enrolled (Q48).

### G5.4 `class_sessions`

Purpose: one dated occurrence of a class (contract §10.1). There is no recurring generation in the sprint.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §10) |
| `class_id` | BIGINT UNSIGNED | No | — | FK `classes.id` | Decided (contract §10.1) |
| `session_date` | DATE | No | — | `Asia/Colombo` date | Decided (contract §10.1) |
| `start_time`, `end_time` | TIME | No | — | `Asia/Colombo`; `end_time > start_time`; whole minutes (Q45) | Decided (contract §10.1) |
| `status` | VARCHAR(32) | No | `'scheduled'` | `scheduled\|cancelled` | Decided values (contract §10); vocabulary PROPOSAL (Q40) |
| `slot_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status <> 'cancelled', 1, NULL)` | **PROPOSAL (R3 brief)**; X2, Q39 |
| `created_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; from the session | PROPOSAL (draft) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `class_sessions_uuid_unique (uuid)`.
  - `class_sessions_slot_unique (class_id, session_date, start_time, slot_flag)`: one **non-cancelled** session per class, date and start time. Cancelled sessions keep their row and free the slot (R3 brief).
  - `class_sessions_id_class_unique (id, class_id)`. PROPOSAL (R3 brief): the parent key for the attendance composite FK.
- **Indexes:** the class FK is served by the slot unique key, which leads with `class_id`.
- **FKs:** `class_sessions_class_fk`, `class_sessions_created_by_fk`.
- **CHECKs** (PROPOSAL):
  - `class_sessions_status_chk CHECK (status IN ('scheduled','cancelled'))`.
  - `class_sessions_times_chk CHECK (end_time > start_time)`.
  - `class_sessions_minutes_chk CHECK (SECOND(start_time) = 0 AND SECOND(end_time) = 0)` (Q45).
  - Sessions cannot cross midnight.
- **Application rules:**
  1. **Create** (§10.1): lock the institute, then the class; re-authorize (the assigned teacher with an active assignment, or an institute manager; suspended → `403`).
     - A duplicate non-cancelled slot → `409` (contract), mapped without chaining.
     - Overlapping sessions with different start times are allowed; the contract checks only the start time.
     - Class status and date-range rules: Q47.
  2. **Cancel** (proposed E3 endpoint, contract v0.7.1 §10.6; Q41): `scheduled` → `cancelled` once, freeing the slot. The row is never deleted. A session that already has attendance cannot be cancelled (`409`, Q50).

## G6 — Attendance

### G6.1 `attendances`

Purpose: one attendance mark per session and enrolment (contract §2.3, §10.3).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `session_id` | BIGINT UNSIGNED | No | — | | Decided (contract, draft) |
| `enrollment_id` | BIGINT UNSIGNED | No | — | | Decided (contract, draft) |
| `class_id` | BIGINT UNSIGNED | No | — | must be both the session's and the enrolment's class (two composite FKs) | **PROPOSAL (R3 brief)**: new column, cross-class invariant |
| `status` | VARCHAR(32) | No | — | `present\|absent\|late\|excused` | Decided (contract §10.3) |
| `method` | VARCHAR(32) | No | `'manual'` | `manual` only in the sprint | Decided (D-012, contract) |
| `notes` | VARCHAR(500) | Yes | NULL | free text §1.6 | Decided field (contract §10.3); length PROPOSAL (Q44) |
| `marked_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; from the session, never the request | Decided (contract §10.3) |
| `marked_at` | DATETIME | No | — | UTC; set on insert and on every correction | Decided (contract §10.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **No `uuid`:** attendance is addressed by session and enrolment UUIDs (contract §10.3–§10.5).
- **Unique:** `attendances_session_enrollment_unique (session_id, enrollment_id)` (contract §1.6, §10.3).
- **Indexes:**
  - `attendances_session_class_index (session_id, class_id)` and `attendances_enrollment_class_index (enrollment_id, class_id)`: the child indexes for the composite FKs. The unique key does not qualify for the session FK: its second column is `enrollment_id`.
  - The enrolment index also serves §10.5 (a child's attendance).
- **FKs** (all `ON DELETE RESTRICT`):
  - `attendances_session_class_fk (session_id, class_id) → class_sessions(id, class_id)`, parent `class_sessions_id_class_unique`.
  - `attendances_enrollment_class_fk (enrollment_id, class_id) → class_enrollments(id, class_id)`, parent `class_enrollments_id_class_unique`.
  - `attendances_marked_by_fk (marked_by_user_id) → users(id)`.
  - **Effect:** an attendance row whose session and enrolment belong to different classes cannot exist (VP-61). Sessions and enrolments that have attendance cannot be deleted, so the history is kept (VP-67, VP-70).
- **CHECKs** (PROPOSAL):
  - `attendances_status_chk CHECK (status IN ('present','absent','late','excused'))`.
  - `attendances_method_chk CHECK (method IN ('manual'))`.
- **Application rules** (§10.3):
  1. **Marking:** lock the institute → the class → the session (by primary key); re-authorize.
     - Who may mark: the assigned teacher with an active assignment, or same-institute staff/admin. A suspended institute → `403`.
     - A cancelled session → `409` (contract); a session after today in `Asia/Colombo` → `409` (contract "proposed", Q55).
  2. **Enrolment eligibility:** a plain read, after the class lock, of the enrolments named in the request. Every enrolment writer holds the class lock, so the read is current.
     - Each must belong to the session's class, with the composite FK as backstop, and be **active on `session_date`** (G5.3 rule 3), otherwise `422` (contract).
     - A withdrawn enrolment can still be corrected for sessions before `withdrawn_on`.
  3. **Upsert:**
     - A plain read finds the existing rows for the session and those enrolments.
     - A new row is inserted; a changed row is updated by primary key. That update is a **correction**: new `marked_at` and `marked_by_user_id`, audited `attendance.corrected` with the old and new values (contract).
     - An unchanged row writes nothing (§1.6). Duplicates in one request → `422` (contract).
     - Audit payload granularity, and whether `notes` text is recorded or only `notes_changed`: Q49.
  4. **History:** withdrawing, archiving or cancelling never deletes attendance (RESTRICT, no delete path).

## G5/G6 lock order

Extends D-022 (Option A, Pattern I) and D-023:

```
1 otp_challenges
2 users                EXCLUSIVE — self-scoped transactions only (Pattern U)
3 institutes           EXCLUSIVE by primary key — first lock of every institute-scoped write
4a student_profiles → guardian_invitations → guardian_profiles → student_guardians      (E2)
4b classes → class_fee_versions → class_sessions → class_enrollments → attendances    (G5/G6)
   (institute_staff / teacher_institute / user_roles also sit at level 4, as before)
8 users                SHARED only (FK checks, audit rows, explicit FOR SHARE re-checks)
9 audit_logs           insert
```

- **Fixed order:** sessions come **before** enrolments. Attendance marking is the only path that needs both, and it needs the session's state first. No path locks an enrolment and then waits for a session.
- **Every row is locked by primary key.** Sets of rows (open fee version, active enrolments, existing attendance, active count) are found with **plain reads after the class lock**. Every writer of those rows holds the same institute and class locks, so the snapshot is current, and nothing locks a range.
- **G5/G6 never takes an explicit lock on `student_profiles`.** An enrolment insert takes an implicit shared lock on the student row (FK check) *after* the class lock. Every E2 writer of that row holds the same institute lock first, so the two never wait on each other.
- **Lookup rows** (implicit shared locks from the class FKs) have no writers.
- **`teacher_profiles`** gets an implicit shared lock from the class insert or teacher change. `TeacherProfileService` holds it exclusively and then waits only for a shared `users` lock (D-022 C-1 fix), which never closes a cycle.

| Write | Explicit locks, in order | Implicit shared locks | Plain reads after the class lock |
|---|---|---|---|
| Class create | `institutes` | `institutes` (held), `teacher_profiles`, lookups, `users` (actor, audit); fee insert: `classes` (new) | active teacher assignment |
| Class update / fee change | `institutes` → `classes` | as create; fee: `classes` (held), `users` | active count; open fee version |
| Enrolment create | `institutes` → `classes` | `classes` (held), `student_profiles`, `users` (audit) | student's institute; active count; existing active enrolment |
| Enrolment withdraw (Q41) | `institutes` → `classes` → `class_enrollments` | `users` (audit) | — |
| Session create | `institutes` → `classes` | `classes` (held), `users` | duplicate slot |
| Session cancel (Q41) | `institutes` → `classes` → `class_sessions` | `users` | attendance present (Q50) |
| Attendance mark | `institutes` → `classes` → `class_sessions` | `class_enrollments`, `class_sessions` (held), `users` | enrolments named; existing attendance |

- **All of these** use `Transactions::retrying()`, with nested calls propagating (D-022). Unique-key backstops are mapped to `409` without chaining the database error.
- **Throughput trade-off (Q56):** the institute lock serialises every write in one institute, including attendance marking. That is acceptable for the demo sprint; locking only the class would need its own review against Pattern I.

## R3 migration order (M3)

Appended after `2026_10_29_000013` (append-only, D-014). Every FK points to an earlier table, and there are no cycles or deferred FKs.

| # | Migration | Creates | FKs to |
|---|---|---|---|
| 16 | `2026_10_29_000014_create_classes_table.php` | `classes` (+ `(id, institute_id)` unique, CHECKs) | `institutes`, `teacher_profiles`, `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi` |
| 17 | `2026_10_29_000015_create_class_fee_versions_table.php` | `class_fee_versions` (+ `current_flag`, CHECKs) | `classes`, `users` |
| 18 | `2026_10_29_000016_create_class_sessions_table.php` | `class_sessions` (+ `slot_flag`, `(id, class_id)` unique, CHECKs) | `classes`, `users` |
| 19 | `2026_10_29_000017_create_class_enrollments_table.php` | `class_enrollments` (+ `active_flag`, `(id, class_id)` unique, CHECKs) | `classes` (composite), `student_profiles` (composite) |
| 20 | `2026_10_29_000018_create_attendances_table.php` | `attendances` (+ CHECKs) | `class_sessions` (composite), `class_enrollments` (composite), `users` |

**Composite FK parent keys**, verified against §0.C-4: the parent has a **unique** index whose first columns are the referenced columns, in the same order, with identical `BIGINT UNSIGNED` types.

| Child FK | Parent unique key | Exists? | Named child index |
|---|---|---|---|
| `class_enrollments (class_id, institute_id)` | `classes_id_institute_unique (id, institute_id)` | created in #16 | `class_enrollments_class_institute_index` |
| `class_enrollments (student_id, institute_id)` | `student_profiles_id_institute_unique (id, managing_institute_id)` | **yes** (G4.1, applied) | `class_enrollments_student_institute_index` |
| `attendances (session_id, class_id)` | `class_sessions_id_class_unique (id, class_id)` | created in #18 | `attendances_session_class_index` |
| `attendances (enrollment_id, class_id)` | `class_enrollments_id_class_unique (id, class_id)` | created in #19 | `attendances_enrollment_class_index` |

- **No `CASCADE`, `SET NULL` or `SET DEFAULT` anywhere.** The generated columns use `status` (enrolments, sessions) and `effective_to` (fee versions), none of which has an FK (§0.C-3). No CHECK uses an FK column (§0.C-2).
- `down()` drops each table. Rolling back M3 drops the five tables in reverse order and does not touch G1–G4.
- **The first run goes only to `tuitionhub_test`,** through the normal suite (`RefreshDatabase`). Applying M3 to `tuitionhub` needs its own approval, as for R2 (D-016).

## R3 validation plan (VP-60 onward; to run in M3, not executed)

"DB" cases are direct inserts asserting the MySQL error code and the constraint name. "Model" and "Service" cases need the models or the later E3 services.

| ID | Kind | Test | Expected |
|---|---|---|---|
| VP-60 | DB | **Cross-tenant enrolment:** class at institute A, student at B. Insert with `institute_id = A`, then with `= B` | 1452 `class_enrollments_student_institute_fk`; 1452 `class_enrollments_class_institute_fk` |
| VP-61 | DB | **Cross-class attendance:** session of class X, enrolment of class Y. Insert with `class_id = X`, then `= Y`, then a third class | 1452 on `attendances_enrollment_class_fk`, then on `attendances_session_class_fk`, then 1452 |
| VP-62 | DB | **Duplicate active enrolment** for the same class and student | 1062 `class_enrollments_active_unique` |
| VP-63 | DB | **Multiple historical withdrawals:** three `withdrawn` rows plus one `active` row for the same class and student | All accepted |
| VP-64 | DB + Service | **Re-enrolment** after withdrawal | A new row with a new UUID; the old row unchanged |
| VP-65 | DB | **Invalid capacity:** `max_students` 0, then NULL | 3819 `classes_max_students_chk`; 1048 |
| VP-66 | DB | **Duplicate session:** a second `scheduled` session in the same (class, date, start time); then cancel the first and insert again; then two cancelled sessions in one slot | 1062 `class_sessions_slot_unique`; afterwards accepted; accepted |
| VP-67 | DB | **Cancelled-session history:** a cancelled session with attendance; try to delete the session and the class | 1451 `attendances_session_class_fk` and `class_sessions_class_fk`; rows unchanged |
| VP-68 | DB | **Two current fee versions** for one class; then two versions with the same `effective_from` | 1062 `class_fee_versions_current_unique`; 1062 `class_fee_versions_class_from_unique` |
| VP-69 | DB | **Duplicate attendance** for a session and enrolment | 1062 `attendances_session_enrollment_unique` |
| VP-70 | DB + Service | **Historical attendance after withdrawal:** mark, then withdraw the enrolment, then try to delete it | Attendance rows still present; 1451 `attendances_enrollment_class_fk` |
| VP-71 | DB | **CHECK violations:** class status `open`, mode `remote`, `ends_on < starts_on`; fee amount `0`/`-1`, currency `USD`, cycle `weekly`, `effective_to < effective_from`; enrolment status `paused`, `withdrawn` without `withdrawn_on`, `active` with it, `withdrawn_on < enrolled_on`; session `end_time <= start_time`, status `done`, `15:30:20`; attendance status `sick`, method `qr` | 3819, each naming its CHECK |
| VP-72 | DB + Model | Writing `current_flag`, `active_flag` or `slot_flag` directly | 3105; the model refuses before any query (`LogicException`) |
| VP-73 | DB | **Parent protection:** delete an institute with classes, a teacher profile with classes, a used lookup row, a class with fee versions, enrolments or sessions, a user referenced as creator or marker | 1451, each with the named FK |
| VP-74 | DB | **Parent existence:** a class with an unknown teacher or lookup; an enrolment for an unknown class or student; attendance for an unknown session or enrolment | 1452, each with the named FK |
| VP-75 | Model | **Fee immutability:** change `amount`, `currency`, `billing_cycle` or `effective_from` of a stored version; set `effective_to` on a closed version | `LogicException`; the database is unchanged |
| VP-76 | Service (E3) | **No overlapping fee versions:** a new version on or before the open version's `effective_from` → `422`; a valid one closes the previous version the day before; every date matches at most one version | As described |
| VP-77 | Service (E3) | **Capacity:** enrolling into a full class → `409`; lowering `max_students` below the active count → `409` | As described |
| VP-78 | Service (E3) | **Active on date:** attendance for a session before `enrolled_on`, or on/after `withdrawn_on` → `422`; before `withdrawn_on` → accepted | As described |
| VP-79 | Structure | Column types (`DATE`/`TIME`/`DATETIME`, `DECIMAL(15,2)`), no `deleted_at`, generated expressions, every named index, FK rules `RESTRICT`, migration order | As specified |
| VP-80 | Model | Morph aliases `class`, `class_fee_version`, `class_enrollment`, `class_session`, `attendance`; public UUIDs on classes, enrolments and sessions only; no internal ids serialized | As described |
| VP-81 | DB + Service (E3) | **Zero-length enrolment:** withdrawal requested before `enrolled_on`. DB: `withdrawn_on = enrolled_on` is accepted | Stored as `withdrawn_on = enrolled_on`; active on no date |
| VP-82 | Service (E3) | **Attendance safeguard:** withdrawal dated on or before the date of a session that already has attendance for the enrolment | `409`; the enrolment and attendance unchanged |
| VP-83 | Service (E3) | **Future enrolment within class dates:** `enrolled_on` after today but within `starts_on`–`ends_on` → `201`; before `starts_on` or after `ends_on` → `422` | As described |

## R3 decisions (D-024)

All items were approved by the TuitionHub founders on 2026-11-01 (D-024).

| # | Topic | Decision | Status |
|---|---|---|---|
| Q38 | X1: `enrolled_at` (brief) vs `enrolled_on` (contract) | `enrolled_on DATE`, as in the contract | **Decided (D-024)** |
| Q39 | X2: may a cancelled session free its slot? | Yes; contract v0.7.1 §10.1 | **Decided (D-024)** |
| Q40 | X6: status vocabularies | Enrolments `active\|withdrawn`; sessions `scheduled\|cancelled` | **Decided (D-024)** |
| Q41 | X7: withdrawing an enrolment and cancelling a session | Schema ready; endpoints proposed for E3 (contract v0.7.1 §8.5, §10.6), to be finalised in the E3 plan | **Decided (D-024)** |
| Q42 | New columns `class_enrollments.institute_id` and `attendances.class_id`, plus the parent keys `classes (id, institute_id)`, `class_sessions (id, class_id)` and `class_enrollments (id, class_id)` | Approved: tenant and cross-class invariants enforced by composite FKs | **Decided (D-024)** |
| Q43 | X5: fee versions | No `uuid`; `effective_to` set once, from NULL; model guard against any other change | **Decided (D-024)** |
| Q44 | `attendances.notes` length | `VARCHAR(500)` | **Decided (D-024)** |
| Q45 | Whole-minute times (`HH:MM`) | `SECOND(...) = 0` CHECK | **Decided (D-024)** |
| Q46 | Class status transitions | `draft → active → archived`, also `draft → archived`; `archived` is final; an archived class takes no enrolments, sessions or attendance | **Decided (D-024)** |
| Q47 | Date and state boundaries | Enrolment and session creation need an `active` class. `enrolled_on` **may be in the future** but must be `>= starts_on` and `<= ends_on` (when set). `session_date` must fall within `starts_on`–`ends_on`. Withdrawal before `enrolled_on` → `withdrawn_on = enrolled_on` (zero-length). A withdrawal that would invalidate existing attendance → `409` | **Decided (D-024)** |
| Q48 | What "active on `session_date`" means | `enrolled_on <= d < withdrawn_on` (withdrawal day not included) | **Decided (D-024)** |
| Q49 | Attendance audit payloads | One `attendance.recorded` per new row (`session_uuid`, `enrollment_uuid`, `status`, `method`); `attendance.corrected` with old and new `status`/`method` and `notes_changed` only, never the note text | **Decided (D-024)** |
| Q50 | Cancelling a session that has attendance | Refused (`409`); attendance history is never orphaned | **Decided (D-024)** |
| Q51 | Guardian view of attendance after the enrolment is withdrawn (§10.5) | Past sessions stay visible; the guardian link governs access, not the enrolment | **Decided (D-024)** |
| Q52 | Catalogue index; `slug` | As G5.1; `slug` not built | **Decided (D-024)** |
| Q53 | The Eloquent model name (`class` is a PHP reserved word) | `App\Models\TuitionClass`, table `classes`, morph alias `class` | **Decided (D-024)** |
| Q54 | `current_fee` when the next version starts in the future | The version in force today; before any, the earliest future one | **Decided (D-024)** |
| Q55 | Attendance for future sessions | `409` for a `session_date` after today (`Asia/Colombo`) | **Decided (D-024)** |
| Q56 | Attendance serialised by the institute lock | Accepted for the sprint (Pattern I); revisit with real load | **Decided (D-024)** |

## M3 implementation plan (approved with D-024)

- **Scope:** schema only. Five migrations, five models and constraint tests. **No** endpoints, services, seeders or packages; those are E3.
- **Files:**
  - `database/migrations/2026_10_29_000014`–`000018` (as above).
  - `app/Models/TuitionClass.php`, `ClassFeeVersion.php`, `ClassEnrollment.php`, `ClassSession.php`, `Attendance.php`.
    - Casts: `DATE` with `immutable_date:Y-m-d`; `TIME` as strings; `DATETIME` with `immutable_datetime`.
    - Hidden internal ids; `HasPublicUuid` on the three public tables; `GuardsGeneratedColumns`.
    - A fee-version immutability guard.
  - `AppServiceProvider` morph map: five aliases.
  - `tests/Feature/Schema/Concerns/SchemaFixtures.php`: `insertClass`, `insertFeeVersion`, `insertEnrollment`, `insertSession`, `insertAttendance`.
  - New tests, all with `RefreshDatabase`:
    - `R3SchemaStructureTest`;
    - `ClassConstraintsTest`, `FeeVersionConstraintsTest`, `EnrollmentConstraintsTest`, `SessionConstraintsTest`, `AttendanceConstraintsTest`;
    - additions to `GeneratedColumnGuardTest`, `MorphMapTest` and `PublicUuidTest`;
    - a model test for fee immutability.
  - Docs:
    - a D-024 entry (M3 verified);
    - Part 3's status;
    - `DateTimeConventionTest`'s table list.
- **Expected tests:** about **45–55 new** (VP-60–VP-75, VP-79, VP-80, and the database half of VP-81; VP-76–VP-78 and VP-82–VP-83 come with E3), so roughly **497–507** in total.
- **Verification:** `php artisan test` (D-010 guard; `tuitionhub_test` only), `migrate --pretend` against `tuitionhub_test` for review, and `git diff --check`. Applying M3 to `tuitionhub` is a separate approval.

# Part 4 — R4: G7 Demo money and G8 Boost (DRAFT)

**Status:** **DRAFT — not approved. Documentation only; nothing is migrated or implemented.** Prepared 2026-11-02 on `sprint/day1-api-scaffold` (baseline `80b9bff`, after E2b). Revised the same day with the R4 security and billing corrections: number lengths, active-class billing, idempotency and concurrency, billing cycles, demo enforcement per table, money precision and migration readiness. Then revised again in the **R4 final review**: the deadlock wording, Q63's recommended option A, future-enrolment billing (Q77) and the runtime BCMath check. **Awaiting founder approval as D-029 (PROPOSED).**

- **Sources, highest first:**
  - `PRODUCT_DECISIONS.md` C1–C3 (demo money only, Rs 100 flat default, no settlement/payout, boost is a record only);
  - contract v0.10: §2.3 adaptations, §11 invoices/payments/commission, §12 boost, §13 audit events;
  - D-015 (`billed_guardian_id`, Q36 billing access), D-022/D-024 (lock order, Pattern I);
  - the R4 brief (2026-11-02);
  - the sprint draft §3.20–§3.27 and the ER schema, both historical (column detail only where compatible).
- **Labels:** as in Parts 1–3. **Decided (…)** names its source. **PROPOSAL (R4 brief)** and **PROPOSAL** need approval; together with Q57–Q77 they are the approval list for this part (D-029, PROPOSED).
- **Conventions:** §0 applies unchanged:
  - money is `DECIMAL(15,2)` and currency `CHAR(3)` `'LKR'` (§0.4);
  - vocabularies are `VARCHAR(32)` plus a `CHECK`, with server-canonicalised spelling;
  - instants are UTC `DATETIME`; dates are `DATE` in `Asia/Colombo`;
  - every FK is `ON DELETE RESTRICT` with `ON UPDATE` unset, and there is no `CASCADE`/`SET NULL`;
  - constraint names are explicit;
  - **no soft deletes** (§0.8 forbids them on money tables). No G7/G8 row is ever deleted by the application.
- **Demo boundary (C2, C3):** every G7/G8 row is demo-only, enforced **per table** as shown in "Demo-only enforcement by table" below. Not every table has an `is_demo` column. There is **no** settlement, payout, refund or bank column anywhere, and `net_to_institute` is an amount **owed**, never a paid-out amount.

### Demo-only enforcement by table

| Table | How the database pins it as demo | Other demo rules |
|---|---|---|
| `commission_rules` | No `is_demo` column. Rows are seeded or configured only (no write endpoint, contract §14.1) | Read-only to the application in the sprint |
| `invoices` | `is_demo` column, `invoices_demo_chk CHECK (is_demo = 1)` | — |
| `invoice_items` | No `is_demo` column; it belongs to a demo invoice (RESTRICT FK) | `type = 'tuition'` only |
| `payments` | `is_demo` column, `payments_demo_chk CHECK (is_demo = 1)`; `payments_provider_chk CHECK (provider = 'mock')` | — |
| `payment_transactions` | No `is_demo` column; `payment_transactions_gateway_chk CHECK (gateway = 'mock')` | Append-only |
| `platform_commissions` | `is_demo` column, `platform_commissions_demo_chk CHECK (is_demo = 1)`; `status = 'accrued'` only | Labelled "accrued, not collected" |
| `boost_products` | `is_demo` column, `boost_products_demo_chk CHECK (is_demo = 1)` | Seeded; no write endpoint |
| `boost_purchases` | No `is_demo` column; `boost_purchases_status_chk CHECK (status = 'demo_recorded')` | No placement fields |

- **No new `is_demo` columns are proposed** for `commission_rules`, `invoice_items`, `payment_transactions` or `boost_purchases`. Their demo status follows from their parent row, from `gateway`/`status`, or from having no writer.
- **API rule (E4):** every money object in a §11/§12 response carries `demo: true`, and every such response carries a `demo_label`. The contract already shows both on `Invoice`, `Payment`, the accrual and the purchase. §12.1 products and the §11.7 completion payload show `demo: true` without a `demo_label`; adding a label there is a contract amendment for D-029 (Q76).
- **Not built:**
  - **Settlements and payouts:** `settlements`, `settlement_items`, `institute_bank_accounts`, `platform_commissions.settlement_id`.
  - **Old commission fields:** `institute_settings` commission fields and any 2% rule.
  - **Refunds:** `refunds`; the `refunded`/`partially_refunded`/`processing` payment statuses; invoice `draft`/`partially_paid`/`overdue`.
  - **Extra invoice lines:** `invoice_items` types other than `tuition`.
  - **Live gateways:** `webhook_logs` and gateway JSON payloads.
  - **Boost placement:** `featured_listings`, and any boost placement, period or duration field (C1).

### Contradictions found between the sources (each needs a decision; proposed resolution shown)

| # | Point | Sources disagree | Part 4 proposes |
|---|---|---|---|
| X8 | Fee snapshot column | Contract §2.3 lists `invoices.fee_amount_snapshot`; §11.1 defines `subtotal` as the fee-version amount snapshot | No separate column: `subtotal` **is** the fee snapshot, and `fee_version_id` records its source (Q58) |
| X9 | Invoice statuses | Draft: `draft\|open\|paid\|partially_paid\|cancelled\|overdue`. Contract §11.1: `open\|paid\|cancelled` | The contract's three |
| X10 | Payment statuses | Draft adds `processing`, `refunded`, `partially_refunded`. Contract §2.3: `pending\|succeeded\|failed\|cancelled` | The contract's four |
| X11 | Commission rule shape | Draft: `institute_id` NOT NULL (no platform scope), `calculation_type`, `rate_pct DECIMAL(7,4)`, `is_active`. Contract §2.3: `scope platform\|institute\|class`, `rule_type`, `percentage`, effective dates, seeded platform rule | The contract's columns; `percentage` precision Q60 |
| X12 | `billed_guardian_id` | Draft: NOT NULL. Contract §11.2: `NULL` when no guardian is linked yet | Nullable |
| X13 | Invoice items | Draft: `ON DELETE CASCADE`; six item types. Brief/contract §11.2: one `tuition` item | `RESTRICT`; `tuition` only; one per invoice |
| X14 | Boost | Draft: `amount`, `status demo_paid`, `duration_days`, `product_name_snapshot`. Contract §2.3: `price_snapshot`, `status demo_recorded`, no placement/period fields | The contract's columns (Q70 for a name snapshot) |
| X15 | Cancelled invoices | Contract §11.1 allows `cancelled`, but no endpoint cancels an invoice | The schema supports it; no cancel path in E4 (Q65) |

## G7 — Demo money

### G7.1 `commission_rules`

Purpose: the TuitionHub fee added on top of the class fee (C3, contract §11.1). Resolution at invoice creation: the active `class` rule, else the active `institute` rule, else the `platform` rule. Rules are versioned like fees: append-only, with `effective_to` set once.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `scope` | VARCHAR(32) | No | — | `platform\|institute\|class` | Decided (contract §2.3) |
| `institute_id` | BIGINT UNSIGNED | Yes | NULL | NULL for `platform`; required for `institute` and `class` | Decided (contract §2.3); class-scope requirement PROPOSAL (tenant FK) |
| `class_id` | BIGINT UNSIGNED | Yes | NULL | required only for `class` | Decided (contract §2.3) |
| `scope_target_id` | BIGINT UNSIGNED, **STORED generated** | Yes | — | `CASE scope WHEN 'platform' THEN 0 WHEN 'institute' THEN institute_id ELSE class_id END`. NULL only for a row that `commission_rules_scope_target_chk` rejects, so a bad target fails on the CHECK (3819), not on NOT NULL | **PROPOSAL**: makes "one active rule per target" a unique key (NULL targets never collide) |
| `rule_type` | VARCHAR(32) | No | — | `flat\|percentage` | Decided (contract §2.3) |
| `flat_amount` | DECIMAL(15,2) | Yes | NULL | set only for `flat`; `> 0` (Q59) | Decided (contract §2.3) |
| `percentage` | DECIMAL(5,2) | Yes | NULL | set only for `percentage`; `0 < p <= 100` (Q60) | Decided column (contract §2.3); type PROPOSAL |
| `currency` | CHAR(3) | No | `'LKR'` | `'LKR'` only | Decided (contract §1.5, §2.3) |
| `effective_from` | DATE | No | — | | Decided (contract §2.3) |
| `effective_to` | DATE | Yes | NULL | NULL while current; set **once** when replaced | Decided column (contract §2.3); set-once PROPOSAL (as G5.2) |
| `active_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(effective_to IS NULL, 1, NULL)` | Decided pattern (§0.9) |
| `created_by_user_id` | BIGINT UNSIGNED | Yes | NULL | FK `users.id`; NULL only for seeded rules | Decided column (contract §2.3); nullable PROPOSAL (no management endpoint, contract §14.1) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `commission_rules_uuid_unique (uuid)`.
  - `commission_rules_active_unique (scope, scope_target_id, active_flag)`: **one open rule per scope target** (R4 brief). The platform target is `0`, so only one open platform rule can exist.
  - `commission_rules_target_from_unique (scope, scope_target_id, effective_from)`.
- **Indexes:** `commission_rules_class_institute_index (class_id, institute_id)` (child index for the composite FK); `institute_id` gets its FK index.
- **FKs** (all `ON DELETE RESTRICT`):
  - `commission_rules_institute_fk (institute_id) → institutes(id)`.
  - `commission_rules_class_institute_fk (class_id, institute_id) → classes(id, institute_id)`, parent `classes_id_institute_unique` (exists, G5.1). With `class_id` NULL the FK is not checked (MATCH SIMPLE). For a class rule it guarantees the class belongs to the rule's institute.
  - `commission_rules_created_by_fk (created_by_user_id) → users(id)`.
  - `scope_target_id` is generated from FK columns that use `RESTRICT` only, which §0.C-3 allows.
- **CHECKs** (PROPOSAL):
  - `commission_rules_scope_chk CHECK (scope IN ('platform','institute','class'))`.
  - `commission_rules_scope_target_chk CHECK ((scope = 'platform' AND institute_id IS NULL AND class_id IS NULL) OR (scope = 'institute' AND institute_id IS NOT NULL AND class_id IS NULL) OR (scope = 'class' AND institute_id IS NOT NULL AND class_id IS NOT NULL))`.
  - `commission_rules_type_chk CHECK (rule_type IN ('flat','percentage'))`.
  - `commission_rules_value_chk CHECK ((rule_type = 'flat' AND flat_amount IS NOT NULL AND flat_amount > 0 AND percentage IS NULL) OR (rule_type = 'percentage' AND percentage IS NOT NULL AND percentage > 0 AND percentage <= 100 AND flat_amount IS NULL))`.
  - `commission_rules_currency_chk CHECK (currency = 'LKR')`.
  - `commission_rules_dates_chk CHECK (effective_to IS NULL OR effective_to >= effective_from)`.
- **Seed** (Decided, contract §2.3): one platform rule, `flat`, `100.00` LKR, `effective_from` = Q61, `created_by_user_id` NULL. The seeder is idempotent.
- **Application rules:**
  1. **Read-only in the sprint.** There is no rule-management endpoint (contract §14.1); rules are seeded or configured. Rows are append-only, and the model refuses any change except setting `effective_to` once (as G5.2).
  2. **Resolution for a date `d`** (invoice `issued_on`): for each scope in order class → institute → platform, the row with `effective_from <= d AND (effective_to IS NULL OR effective_to >= d)`. The first match wins. No platform rule in force → `500`, failing closed (a configuration error, VP-96).
  3. **Calculation:** `flat` uses `flat_amount`; `percentage` uses `subtotal × percentage / 100`, rounded **half-up** to 2 decimals. It is computed as specified in "Money arithmetic and precision" below: server-side, never with floats. **No universal 2% default** (C3).

### G7.2 `invoices`

Purpose: a demo bill for one enrolment and billing period (contract §11.1, §11.2). Every amount and the applied rule are **snapshotted**, so a later fee or rule change never alters an issued invoice.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §11.1) |
| `invoice_no` | VARCHAR(48) | No | — | unique, server-generated; final form `INV-YYYY-MM-` + zero-padded id, e.g. `INV-2026-11-000001`; a 40-character `TMP-<uuid>` placeholder exists only inside the creating transaction ("Invoice and payment numbers") | Decided (contract §11.1); length and scheme PROPOSAL (Q62) |
| `enrollment_id` | BIGINT UNSIGNED | No | — | must be an enrolment of `class_id`, `student_id`, `institute_id` (composite FK) | Decided (draft, contract §11.2) |
| `class_id` | BIGINT UNSIGNED | No | — | | **PROPOSAL (R4 brief)**: tenant/consistency column, as G5.3 |
| `student_id` | BIGINT UNSIGNED | No | — | | Decided (draft, contract §11.1) |
| `institute_id` | BIGINT UNSIGNED | No | — | the class's and the student's institute | Decided (draft, contract §11.1) |
| `fee_version_id` | BIGINT UNSIGNED | No | — | the version in force on `issued_on`, of `class_id` (composite FK) | Decided (draft, contract §11.2) |
| `billed_guardian_id` | BIGINT UNSIGNED | Yes | NULL | FK `guardian_profiles.id`; immutable addressee snapshot | Decided (contract §11.2, D-015; X12) |
| `billing_period` | CHAR(7) | No | — | `YYYY-MM` | Decided format (contract §11.1 example); `term`/`one_time` classes Q63 |
| `issued_on` | DATE | No | — | | Decided (contract §11.2) |
| `due_on` | DATE | No | — | `>= issued_on` | Decided (contract §11.2) |
| `currency` | CHAR(3) | No | `'LKR'` | `'LKR'` only | Decided (contract §1.5) |
| `subtotal` | DECIMAL(15,2) | No | — | `> 0`; = fee version `amount` snapshot | Decided (contract §11.1; X8) |
| `platform_fee` | DECIMAL(15,2) | No | — | `>= 0`; resolved commission | Decided (contract §11.1) |
| `total` | DECIMAL(15,2) | No | — | `= subtotal + platform_fee` | Decided (contract §11.1) |
| `commission_rule_id` | BIGINT UNSIGNED | No | — | FK `commission_rules.id`; the rule applied | Decided (contract §2.3) |
| `commission_scope_snapshot` | VARCHAR(32) | No | — | `platform\|institute\|class` | **PROPOSAL**: the `Invoice.commission.scope` field (§11.1) without a join |
| `commission_rule_type_snapshot` | VARCHAR(32) | No | — | `flat\|percentage` | Decided (contract §2.3) |
| `commission_flat_snapshot` | DECIMAL(15,2) | Yes | NULL | set only for `flat` | Decided (contract §2.3) |
| `commission_percentage_snapshot` | DECIMAL(5,2) | Yes | NULL | set only for `percentage` | Decided (contract §2.3); type as G7.1 |
| `status` | VARCHAR(32) | No | `'open'` | `open\|paid\|cancelled` | Decided (contract §11.1; X9) |
| `billable_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status <> 'cancelled', 1, NULL)` | **PROPOSAL**: makes "one non-cancelled invoice per enrolment and period" (§11.2) a unique key |
| `is_demo` | BOOLEAN (TINYINT(1)) | No | `1` | always `1` | Decided (contract §2.3) |
| `created_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; the billing user, from the session | **PROPOSAL** (audit actor is also recorded; Q64) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | `updated_at` changes only on a status change | PROPOSAL (§0.6) |

- **Unique:**
  - `invoices_uuid_unique (uuid)`.
  - `invoices_invoice_no_unique (invoice_no)`.
  - `invoices_period_unique (enrollment_id, billing_period, billable_flag)`: one non-cancelled invoice per enrolment and period (contract §11.2). The service maps a collision to `409` without chaining (D-022).
  - `invoices_id_institute_unique (id, institute_id)`. PROPOSAL: the parent key for the payment composite FK.
- **Indexes:**
  - `invoices_enrollment_tenant_index (enrollment_id, class_id, student_id, institute_id)`: child index for the enrolment composite FK.
  - `invoices_fee_version_class_index (fee_version_id, class_id)`: child index for the fee composite FK.
  - `invoices_institute_status_index (institute_id, status)` (§11.3).
  - `invoices_student_status_index (student_id, status)` (§11.4).
  - `billed_guardian_id`, `commission_rule_id` and `created_by_user_id` get their FK indexes.
- **FKs** (all `ON DELETE RESTRICT`):
  - `invoices_enrollment_tenant_fk (enrollment_id, class_id, student_id, institute_id) → class_enrollments(id, class_id, student_id, institute_id)`.
    - Its parent key `class_enrollments_id_tenant_unique` is **new** (migration #21; Q57).
    - One FK guarantees that the enrolment, class, student and institute all agree. `class_enrollments` already guarantees the class and student belong to that institute (G5.3).
  - `invoices_fee_version_class_fk (fee_version_id, class_id) → class_fee_versions(id, class_id)`, with **new** parent key `class_fee_versions_id_class_unique` (migration #21; Q57): the fee always belongs to the invoiced class.
  - `invoices_billed_guardian_fk → guardian_profiles(id)`, `invoices_commission_rule_fk → commission_rules(id)`, `invoices_created_by_fk → users(id)`.
- **CHECKs** (PROPOSAL):
  - `invoices_status_chk CHECK (status IN ('open','paid','cancelled'))`.
  - `invoices_currency_chk CHECK (currency = 'LKR')`.
  - `invoices_amounts_chk CHECK (subtotal > 0 AND platform_fee >= 0 AND total = subtotal + platform_fee)`.
  - `invoices_dates_chk CHECK (due_on >= issued_on)`.
  - `invoices_period_chk CHECK (billing_period REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')`. MySQL 8.4 permits `REGEXP` in a CHECK; to be confirmed by VP-105.
  - `invoices_commission_snapshot_chk CHECK ((commission_rule_type_snapshot = 'flat' AND commission_flat_snapshot IS NOT NULL AND commission_percentage_snapshot IS NULL) OR (commission_rule_type_snapshot = 'percentage' AND commission_percentage_snapshot IS NOT NULL AND commission_flat_snapshot IS NULL))`.
  - `invoices_commission_scope_chk CHECK (commission_scope_snapshot IN ('platform','institute','class'))`.
  - `invoices_demo_chk CHECK (is_demo = 1)`.
  - `invoices_invoice_no_chk CHECK (invoice_no REGEXP '^(INV-[0-9]{4}-(0[1-9]|1[0-2])-[0-9]{6,20}|TMP-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$')`. PROPOSAL: only the two documented forms can be stored.
- **Not database rules** (service, tested): the fee version is the one in force on `issued_on`; the commission rule is the one resolved for that date and applies to this class/institute; `subtotal` equals the version's `amount`; `billed_guardian_id` was the primary active guardian at creation.
- **Application rules:**
  1. **Create** (§11.2). Everything runs in one transaction, plus the audit row:
     - Lock institute → class → enrolment, in that order; re-authorize (billing user; suspended → `403`).
     - **Both the class and the enrolment must be active**, checked on the locked rows. The class must have `status = 'active'`; `draft` or `archived` → `409` `reason: not_active` (**PROPOSAL**, R4 corrections). The enrolment must have `status = 'active'` (contract), otherwise also `409 not_active`. Contract §11.2 states only the enrolment rule; adding the class rule and the `409 not_active` mapping is a contract amendment for D-029 (Q73).
     - **The enrolment must be effective on `issued_on`** (`enrolled_on <= issued_on`), otherwise `422` on `issued_on`. This is **PROPOSED**, Q77; see "Billing date eligibility".
     - Plain reads after the locks: the fee version on `issued_on` (`422` if none); the commission rule; the student's primary active guardian; an existing non-cancelled invoice for the period (`409`, with the unique key as backstop); the billing-cycle rule (Q63).
     - Insert the invoice with a `TMP-<uuid>` placeholder number and its one item. Then set the final `invoice_no` by primary key in the **same** transaction ("Invoice and payment numbers").
     - Audit `invoice.created` (contract §13) with the final number.
  2. **Snapshots are immutable:** every column except `status`/`updated_at` never changes; the model refuses other changes (as G5.2).
  3. **Status:** `open → paid` only through demo completion (§11.7); `open → cancelled` has no endpoint (Q65); `paid` and `cancelled` are final.

### G7.3 `invoice_items`

Purpose: the invoice's single tuition line (contract §11.1 `items`).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `invoice_id` | BIGINT UNSIGNED | No | — | FK `invoices.id` | Decided (draft) |
| `type` | VARCHAR(32) | No | `'tuition'` | `tuition` only | Decided (contract §11.1 example; R4 brief; X13) |
| `description` | VARCHAR(255) | No | — | server-generated, e.g. "Tuition — November 2026" | Decided (contract §11.1) |
| `amount` | DECIMAL(15,2) | No | — | `> 0`; `= invoices.subtotal` | Decided (R4 brief) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **No `uuid`:** items are only returned inside their invoice.
- **Unique:** `invoice_items_invoice_type_unique (invoice_id, type)`: one tuition item per invoice (R4 brief). It also serves the invoice FK.
- **FKs:** `invoice_items_invoice_fk (invoice_id) → invoices(id)` `ON DELETE RESTRICT` (not the draft's `CASCADE`; invoices are never deleted).
- **CHECKs** (PROPOSAL): `invoice_items_type_chk CHECK (type IN ('tuition'))`; `invoice_items_amount_chk CHECK (amount > 0)`.
- **Not a database rule:** `amount = invoices.subtotal` (a cross-table rule). It is written in the same insert transaction and tested (VP-95). Items are immutable.

### G7.4 `payments`

Purpose: a demo payment attempt by a linked guardian (contract §11.6, §11.7). No real money moves.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §11.6) |
| `payment_no` | VARCHAR(48) | No | — | unique, server-generated; final form `PAY-YYYY-MM-` + zero-padded id, e.g. `PAY-2026-11-000001`; `TMP-<uuid>` placeholder only inside the creating transaction ("Invoice and payment numbers") | Decided (contract §11.6); length and scheme PROPOSAL (Q62) |
| `invoice_id` | BIGINT UNSIGNED | No | — | | Decided (draft, contract) |
| `institute_id` | BIGINT UNSIGNED | No | — | the invoice's institute (composite FK) | **PROPOSAL (R4 brief)**: tenant column, carried to commissions |
| `payer_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; from the session | Decided (contract §11.6) |
| `amount` | DECIMAL(15,2) | No | — | `= invoice.total` | Decided (contract §11.1) |
| `platform_fee` | DECIMAL(15,2) | No | — | `= invoice.platform_fee` | Decided (contract §11.1) |
| `net_to_institute` | DECIMAL(15,2) | No | — | `= invoice.subtotal`; **owed, not paid out** | Decided (contract §11.1) |
| `currency` | CHAR(3) | No | `'LKR'` | | Decided (contract §1.5) |
| `provider` | VARCHAR(32) | No | `'mock'` | `mock` only | Decided (contract §2.3) |
| `status` | VARCHAR(32) | No | `'pending'` | `pending\|succeeded\|failed\|cancelled` | Decided (contract §2.3; X10) |
| `pending_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status = 'pending', 1, NULL)` | **PROPOSAL**: one pending attempt per invoice (§11.6) |
| `succeeded_flag` | TINYINT UNSIGNED, **STORED generated** | Yes | — | `IF(status = 'succeeded', 1, NULL)` | **PROPOSAL**: at most one success per invoice (§11.7) |
| `idempotency_key` | CHAR(36) | No | — | unique; a client-generated UUID (Q66) | Decided unique (contract §11.6); type PROPOSAL |
| `paid_at` | DATETIME | Yes | NULL | set with `succeeded` | Decided (contract §11.7) |
| `completed_at` | DATETIME | Yes | NULL | set with any final status | **PROPOSAL** |
| `is_demo` | BOOLEAN (TINYINT(1)) | No | `1` | always `1` | Decided (contract §2.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:**
  - `payments_uuid_unique (uuid)`, `payments_payment_no_unique (payment_no)`, `payments_idempotency_key_unique (idempotency_key)`.
  - `payments_pending_unique (invoice_id, pending_flag)`: one `pending` attempt per invoice (contract §11.6).
  - `payments_succeeded_unique (invoice_id, succeeded_flag)`: **an invoice can never be paid twice**, even if a service check were missed (contract §11.7).
  - `payments_id_institute_unique (id, institute_id)`. PROPOSAL: the parent key for the commission composite FK.
- **Indexes:** `payments_invoice_institute_index (invoice_id, institute_id)` (child index for the composite FK); `payments_payer_created_index (payer_user_id, created_at)`.
- **FKs:**
  - `payments_invoice_institute_fk (invoice_id, institute_id) → invoices(id, institute_id)`.
  - `payments_payer_fk (payer_user_id) → users(id)`.
- **CHECKs** (PROPOSAL):
  - `payments_status_chk CHECK (status IN ('pending','succeeded','failed','cancelled'))`.
  - `payments_provider_chk CHECK (provider = 'mock')`; `payments_currency_chk CHECK (currency = 'LKR')`; `payments_demo_chk CHECK (is_demo = 1)`.
  - `payments_amounts_chk CHECK (net_to_institute > 0 AND platform_fee >= 0 AND amount = net_to_institute + platform_fee)`.
  - `payments_completion_chk CHECK ((status = 'pending' AND completed_at IS NULL AND paid_at IS NULL) OR (status = 'succeeded' AND completed_at IS NOT NULL AND paid_at IS NOT NULL) OR (status IN ('failed','cancelled') AND completed_at IS NOT NULL AND paid_at IS NULL))`.
  - `payments_payment_no_chk CHECK (payment_no REGEXP '^(PAY-[0-9]{4}-(0[1-9]|1[0-2])-[0-9]{6,20}|TMP-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$')` (PROPOSAL, as invoices).
- **Not database rules** (service, tested): the amounts equal the invoice snapshot; the payer has an **active** guardian link to the invoice's student (Q36); the invoice is `open`.
- **Application rules:**
  1. **Attempt** (§11.6): lock institute → invoice; re-authorize the payer's active link.
     - **Idempotency** follows "Idempotency keys and duplicate-key resolution" below, including when the same key was used at another institute:
       - the same key with the same authorized invoice and payer → the original attempt (`200`);
       - the same key for any other invoice, payer or institute → a sanitized `409` that reveals nothing about the other record.
     - Invoice not `open` → `409`; an existing `pending` attempt → `409` (unique backstop).
     - Insert the payment with a `TMP-<uuid>` number, set the final `payment_no` by primary key in the same transaction, and insert one `payment_transactions` row. Audit `payment.attempt_created`.
  2. **Complete** (§11.7, demo flag and non-production only): lock institute → invoice → payment.
     - Only `pending` transitions. Repeating the same outcome → `200`; a different outcome → `409`.
     - On `succeeded`: re-check the invoice is `open` under its lock (`paid`/`cancelled` → `409`, attempt unchanged). Set the payment `succeeded` + `paid_at` + `completed_at` and the invoice `paid`, and insert **one** `platform_commissions` row.
     - Audit `payment.succeeded|failed|cancelled` and `commission.accrued`.
  3. **Amounts and snapshots are immutable;** only `status`, `paid_at`, `completed_at` and `updated_at` change, once, from `pending`.

### G7.5 `payment_transactions`

Purpose: an append-only event trail per attempt (contract §11.6), `gateway = 'mock'` only.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `payment_id` | BIGINT UNSIGNED | No | — | FK `payments.id` | Decided (draft) |
| `gateway` | VARCHAR(32) | No | `'mock'` | `mock` only | Decided (contract §2.3) |
| `event` | VARCHAR(32) | No | — | `attempt_created\|succeeded\|failed\|cancelled` | **PROPOSAL** |
| `gateway_reference` | VARCHAR(64) | Yes | NULL | a mock reference, e.g. `MOCK-<uuid>`; never card or bank data | PROPOSAL (draft `gateway_txn_id`) |
| `created_at` | DATETIME | No | — | UTC; the event instant | PROPOSAL (draft: append-only) |

- **No `uuid`, no `updated_at`:** rows are never addressed or changed.
- **No JSON payload columns** (draft `gateway_request/response/webhook`): nothing to store for the mock gateway, and nothing that could hold PII or secrets (Q67).
- **Unique:** `payment_transactions_payment_event_unique (payment_id, event)`: each event at most once per attempt. It also serves the FK.
- **FKs:** `payment_transactions_payment_fk (payment_id) → payments(id)`.
- **CHECKs** (PROPOSAL): `payment_transactions_gateway_chk CHECK (gateway = 'mock')`; `payment_transactions_event_chk CHECK (event IN ('attempt_created','succeeded','failed','cancelled'))`.

### G7.6 `platform_commissions`

Purpose: the demo commission **accrual** for a succeeded payment (contract §11.7, §11.8). It is accrued, not collected; there are no settlement fields.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `payment_id` | BIGINT UNSIGNED | No | — | **unique**; the succeeded payment | Decided (contract §2.3) |
| `institute_id` | BIGINT UNSIGNED | No | — | the payment's institute (composite FK) | Decided (draft, contract §11.8) |
| `amount` | DECIMAL(15,2) | No | — | `>= 0`; `= payment.platform_fee` | Decided (contract §11.1) |
| `currency` | CHAR(3) | No | `'LKR'` | | Decided (contract §1.5) |
| `commission_rule_id` | BIGINT UNSIGNED | No | — | FK `commission_rules.id`; copied from the invoice | Decided (contract §2.3: rule snapshot columns as on invoices) |
| `commission_scope_snapshot`, `commission_rule_type_snapshot`, `commission_flat_snapshot`, `commission_percentage_snapshot` | as G7.2 | as G7.2 | — | copied from the invoice; same CHECKs | Decided (contract §2.3); scope PROPOSAL as G7.2 |
| `status` | VARCHAR(32) | No | `'accrued'` | `accrued` only | Decided (contract §2.3) |
| `accrued_at` | DATETIME | No | — | the payment's `paid_at` | Decided (contract §11.8) |
| `is_demo` | BOOLEAN (TINYINT(1)) | No | `1` | always `1` | Decided (contract §2.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `platform_commissions_uuid_unique (uuid)`; `platform_commissions_payment_unique (payment_id)`: **one accrual per payment** (contract §2.3, §11.7).
- **Indexes:**
  - `platform_commissions_payment_institute_index (payment_id, institute_id)`: child index for the composite FK. The unique key on `payment_id` alone does not cover `institute_id`.
  - `platform_commissions_institute_accrued_index (institute_id, accrued_at)` (§11.8 filters).
  - `platform_commissions_accrued_index (accrued_at)`.
- **FKs:**
  - `platform_commissions_payment_institute_fk (payment_id, institute_id) → payments(id, institute_id)`.
  - `platform_commissions_rule_fk (commission_rule_id) → commission_rules(id)`.
- **CHECKs** (PROPOSAL):
  - `platform_commissions_status_chk CHECK (status = 'accrued')`; `…_currency_chk`; `…_demo_chk CHECK (is_demo = 1)`; `…_amount_chk CHECK (amount >= 0)`.
  - The snapshot CHECKs as G7.2.
- **Not database rules:** the payment is `succeeded`, and the amount and snapshot equal the invoice's. Both are written in the completion transaction and tested (VP-93, VP-97).
- **Wording rule** (C3, contract §11.8): every read labels these rows "DEMO / TEST — accrued, not collected". No column, total or response may call them revenue, collected, settled or paid out.

## G8 — Boost (demo record only)

### G8.1 `boost_products`

Purpose: a seeded demo product (contract §12.1). It has no placement, ranking or duration (C1).

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `code` | VARCHAR(40) | No | — | unique, e.g. `DEMO_BOOST_BASIC` | Decided (contract §2.3) |
| `name` | VARCHAR(150) | No | — | | Decided (contract §2.3) |
| `description` | TEXT | Yes | NULL | | Decided (contract §2.3) |
| `price` | DECIMAL(15,2) | No | — | `> 0` | Decided (contract §2.3) |
| `currency` | CHAR(3) | No | `'LKR'` | | Decided (contract §2.3) |
| `is_active` | BOOLEAN (TINYINT(1)) | No | `1` | | Decided (contract §2.3) |
| `is_demo` | BOOLEAN (TINYINT(1)) | No | `1` | always `1` | Decided (contract §2.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **Unique:** `boost_products_uuid_unique (uuid)`, `boost_products_code_unique (code)`.
- **CHECKs** (PROPOSAL): `boost_products_price_chk CHECK (price > 0)`; `…_currency_chk`; `…_demo_chk CHECK (is_demo = 1)`; `…_active_chk CHECK (is_active IN (0, 1))`.
- **Seed** (Decided, contract §12.1 example): `DEMO_BOOST_BASIC`, "Demo boost", `1500.00` LKR, active (Q69). The seeder is idempotent. No endpoint writes products.

### G8.2 `boost_purchases`

Purpose: a labelled demo purchase **record** (contract §12.2). No payment is taken, and it has **no effect** on the catalogue: §7.5 never reads this table.

| Column | Type | Null | Default | Rule | Basis |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AI | No | — | PK | PROPOSAL (draft) |
| `uuid` | CHAR(36) | No | — | unique | Decided (contract §2.3) |
| `class_id` | BIGINT UNSIGNED | No | — | | Decided (contract §2.3) |
| `institute_id` | BIGINT UNSIGNED | No | — | the class's institute (composite FK) | Decided (contract §2.3) |
| `boost_product_id` | BIGINT UNSIGNED | No | — | FK `boost_products.id`; active at purchase (service) | Decided (contract §2.3) |
| `price_snapshot` | DECIMAL(15,2) | No | — | `> 0`; the product price at purchase | Decided (contract §2.3; X14) |
| `currency` | CHAR(3) | No | `'LKR'` | | Decided (contract §2.3) |
| `status` | VARCHAR(32) | No | `'demo_recorded'` | `demo_recorded` only | Decided (contract §2.3) |
| `idempotency_key` | CHAR(36) | No | — | unique; a client UUID (Q66) | Decided unique (contract §2.3) |
| `purchased_by_user_id` | BIGINT UNSIGNED | No | — | FK `users.id`; from the session | Decided (contract §2.3) |
| `created_at`, `updated_at` | DATETIME | Yes | NULL | | PROPOSAL (§0.6) |

- **No placement fields:** no start, end, duration, rank, position, slot or listing reference (C1; contract §2.3).
- **Unique:** `boost_purchases_uuid_unique (uuid)`, `boost_purchases_idempotency_key_unique (idempotency_key)`.
- **Indexes:**
  - `boost_purchases_class_institute_index (class_id, institute_id)`: child index for the composite FK.
  - `boost_purchases_institute_created_index (institute_id, created_at)` (§12.3).
  - `boost_product_id` and `purchased_by_user_id` get their FK indexes.
- **FKs:**
  - `boost_purchases_class_institute_fk (class_id, institute_id) → classes(id, institute_id)`, parent `classes_id_institute_unique` (exists).
  - `boost_purchases_product_fk → boost_products(id)`, `boost_purchases_purchased_by_fk → users(id)`.
- **CHECKs** (PROPOSAL): `boost_purchases_status_chk CHECK (status = 'demo_recorded')`; `…_price_chk CHECK (price_snapshot > 0)`; `…_currency_chk`.
- **Application rules** (§12.2): lock institute → class; re-authorize (admin of the owning institute; suspended → `403`).
  - **Idempotency** follows "Idempotency keys and duplicate-key resolution" below:
    - the same key with the same authorized class and purchaser → the original record (`200`);
    - the same key for any other class, purchaser or institute → a sanitized `409`.
  - The product must be active. The price comes from the product; client prices → `422`.
  - Audit `boost_purchase.recorded_demo` (contract §13). Rows are immutable.

## G7/G8 lock order

Extends the G5/G6 order (D-022 Option A, Pattern I; D-024):

```
1 otp_challenges
2 users                EXCLUSIVE — self-scoped transactions only (Pattern U)
3 institutes           EXCLUSIVE by primary key — first lock of every institute-scoped write
4a student_profiles → guardian_invitations → guardian_profiles → student_guardians      (E2)
4b classes → class_fee_versions → class_sessions → class_enrollments → attendances    (G5/G6)
4c   … class_enrollments → invoices → payments → platform_commissions                 (G7)
       invoice_items and payment_transactions are inserted only, after their parent's lock
4d classes → boost_purchases                                                          (G8)
   commission_rules and boost_products: read-only in the sprint (seeded), no writers
8 users                SHARED only (FK checks, audit rows, explicit FOR SHARE re-checks)
9 audit_logs           insert
```

- **Every G7/G8 write takes the institute lock first,** including the guardian's payment paths: the payer is re-authorized through `student_guardians`, whose writers all hold the same institute lock (D-023), so a plain read of the link after the institute lock is current.
- **Every row is locked by primary key.** Sets of rows are found with plain reads after the relevant lock: the fee version in force, the applied rule, the primary guardian, an existing invoice for the period, a pending or succeeded attempt, an idempotency key. Every writer of those rows holds the same locks.
- Skipping a level is allowed (a payment path does not lock `classes`); taking a level out of order is not.

| Write | Explicit locks, in order | Implicit shared locks | Plain reads after the locks |
|---|---|---|---|
| Invoice create (§11.2) | `institutes` → `classes` → `class_enrollments` | `class_fee_versions`, `commission_rules`, `guardian_profiles`, `student_profiles`, `users` (actor, audit); item insert: `invoices` (new) | fee version on `issued_on`; resolved rule; primary active guardian; non-cancelled invoice for the period |
| Payment attempt (§11.6) | `institutes` → `invoices` | `users` (payer, audit); transaction insert: `payments` (new) | payer's active link; pending attempt. The idempotency key is **not** read under this lock (see "Idempotency keys and duplicate-key resolution") |
| Demo completion (§11.7) | `institutes` → `invoices` → `payments` | `commission_rules`, `users` (audit) | invoice status (under its lock); existing accrual |
| Boost purchase (§12.2) | `institutes` → `classes` | `boost_products`, `users` | product active. The idempotency key is **not** read under this lock (as payments) |

- **All of these** use `Transactions::retrying()`, with nested calls propagating (D-022). Unique-key backstops (period, pending, succeeded, idempotency, accrual) are mapped to `409` or the idempotent replay without chaining the database error.
- **Read paths** (§11.3–§11.5, §11.8, §12.1, §12.3) take no locks.

## Invoice and payment numbers (Q62)

**PROPOSAL.** Unique, readable numbers without a counter table:

1. **Placeholder:** the row is inserted with `invoice_no` / `payment_no` = `TMP-` + a fresh UUID v4, e.g. `TMP-3f2a…`. That is 4 + 36 = **40 characters**, unique by construction.
2. **Final number:** in the **same** transaction, immediately after the insert and before any other statement that could fail, the row is updated by primary key:
   - invoices: `INV-` + `YYYY-MM` of `issued_on` + `-` + the `id` zero-padded to at least 6 digits;
   - payments: `PAY-` + `YYYY-MM` of the creation date in `Asia/Colombo` + `-` + the zero-padded `id`.

   The longest final form is 12 + 20 digits (`BIGINT UNSIGNED` maximum) = 32 characters. **`VARCHAR(48)`** holds both forms with margin.
3. **Atomicity:**
   - The placeholder exists only inside the creating transaction, which holds the institute → class → enrolment locks (invoices) or the institute → invoice locks (payments).
   - Under REPEATABLE READ no other transaction can read the uncommitted row, so a `TMP-` number is never visible.
   - If anything later fails, the whole transaction rolls back, including the insert, the placeholder and the update.
   - The audit row and the API response use the final number only.
4. **CHECKs:** `invoices_invoice_no_chk` and `payments_payment_no_chk` allow only the two forms. A committed `TMP-` row is impossible through the service, and VP-109 asserts it.
5. **Properties:** numbers are global (not per institute or per month), increase with `id`, and may have gaps after rollbacks. Gap-free numbering would need a counter table and an extra lock (Q62 alternative).

## Idempotency keys and duplicate-key resolution

Payment attempts (§11.6) and boost purchases (§12.2) carry a client `idempotency_key`. Each column is **globally** unique (`payments_idempotency_key_unique`, `boost_purchases_idempotency_key_unique`), so a key may already exist **at another institute**.

**Operation identity** (what makes a replay "the same request"):
- **Payment:** `(invoice_id, payer_user_id)`, and through the invoice, the institute.
- **Boost purchase:** `(class_id, purchased_by_user_id)`, and through the class, the institute.

**Rules** (PROPOSAL, R4 corrections):
1. **Authorization first.** The caller must be authorized for the operation they are requesting (their invoice, their class) before any idempotency outcome is returned. A request the caller may not make gets the normal `404`/`403`, whatever the key.
2. **Replay:** the same key and the same identity, at the same institute → `200` with the **original** record, re-read under the locks below.
3. **Sanitized conflict:** the same key with a different identity, or at a different institute → `409` `{"message":"This idempotency key has already been used.","reason":"idempotency_key_reused"}`.
   - The response names nothing about the other record: no uuid, number, amount, class, invoice, institute or person.
   - The new reason code `idempotency_key_reused` needs a contract amendment (Q74).
4. **No logging:** idempotency keys are never logged, audited or echoed in errors.
   - The MySQL duplicate-key message contains the key (`Duplicate entry '…' for key …`), so the `QueryException` is mapped by constraint name **without chaining**, and is never reported (D-022 pattern).
   - Service parameters carrying the key are marked `#[\SensitiveParameter]`.
5. **Outermost transaction only.** These operations refuse to run inside an enclosing transaction (`DB::transactionLevel() === 0` is asserted). This ensures the resolution step below really starts a **fresh** transaction with a fresh snapshot.

**Write sequence:**

| Step | Transaction | Locks held, in order | What happens |
|---|---|---|---|
| A. Pre-check | fresh, read-only | none | Plain read of the key's row: only `institute_id` and the identity columns, which **never change** after insert. Found → go to **R**. Not found → **B** |
| B. Attempt | `T1` (`Transactions::retrying()`) | `institutes` (own) → `invoices` (payment) or `classes` (boost) | Authorize, check state, `INSERT` with the key. Success → commit. Duplicate on the key's unique index → **roll `T1` back at once** (releasing every lock), then **R** |
| R. Resolve | `T2`, fresh | first none, then the own institute's locks | `R1` and `R2` below |

- **R1 (no locks held):** a read of the existing row's `institute_id` and identity columns in the fresh transaction `T2`.
  - This is a new snapshot, taken after `T1` rolled back and after the winning transaction committed. An uncommitted winner makes `T1`'s insert **wait**; `T1` gets the duplicate-key error only after the winner commits, and its insert succeeds if the winner rolls back.
  - The read is current for these immutable columns. It is **not** a nonlocking read inside the failed transaction's old REPEATABLE READ snapshot.
  - **Different institute** → sanitized `409` immediately. No lock is ever taken on the other institute's row.
- **R2 (same institute):** lock the own `institutes` row → the requested invoice (or class), and re-authorize. Then lock the existing row with `SELECT … FOR SHARE` by primary key, which is a current read. Compare the identity: equal → replay (`200`, the current state of the original); different → sanitized `409`.
  - The lock order is institute → invoice → payment (or institute → class → boost purchase), as in the main lock table.
  - When the existing row belongs to a different invoice or class of the **same** institute, that row is locked after the requested one. Every writer of either row holds the same institute lock first, so these paths are serialised by that lock. That makes a deadlock between them very unlikely, but it is not a general no-deadlock guarantee (see "Deadlocks, retries and failures" below).

**Idempotency lock-order matrix:**

| Situation | Locks taken, in order | Locks on another institute's rows |
|---|---|---|
| New key (normal path) | own `institutes` → `invoices`/`classes` → insert | none |
| Key already committed (pre-check hit) | R1: none → R2 (own institute only): own `institutes` → `invoices`/`classes` → `payments`/`boost_purchases` `FOR SHARE` | **none**. A different institute is answered from R1 without locks |
| Concurrent winner at the **same** institute | impossible: both need the same institute lock first, so they run one after the other. The second sees the committed key at A, or gets 1062 at B → R2 | none |
| Concurrent winner at **another** institute | B: own `institutes` → `invoices`/`classes` → `INSERT` waits for the winner's commit and then fails with 1062 → `T1` rolls back → R1 → sanitized `409` | **Only the implicit locks InnoDB itself takes during the `INSERT`'s duplicate-key check** (a shared lock on the conflicting index record, plus any gap or insert-intention locks under REPEATABLE READ). They are inherent to `INSERT`, and released when `T1` rolls back. They **can** take part in an InnoDB deadlock, for example when several sessions insert the same key (MySQL 8.4 manual, "Locks Set by Different SQL Statements in InnoDB"). The lock order keeps that rare but does not rule it out. Deadlocks are handled by retry ("Deadlocks, retries and failures"). The service never takes an explicit or `FOR SHARE` lock on another institute's row. |
| Completion (§11.7) | own `institutes` → `invoices` → `payments` → insert `platform_commissions` | none |

- **Never reversed:** no path locks a payment or purchase before its invoice or class, or another institute after its own.
- The unique keys `payments_succeeded_unique` and `platform_commissions_payment_unique` remain the final backstops against a double success or a double accrual.
- **A genuine two-process concurrency test** (two PHP processes, two database connections; VP-111, VP-115) is **required before release** (E2c). Single-connection tests cannot prove these interleavings.

**Deadlocks, retries and failures** (PROPOSAL, R4 final review):
1. **Lock order minimises deadlocks; it does not eliminate them.**
   - InnoDB may set implicit locks the application never asks for: a shared lock on a duplicate index record during an `INSERT`, gap and insert-intention locks under REPEATABLE READ, and shared locks from FK checks.
   - These can form a deadlock even when every explicit lock follows the documented order.
2. **Existing retry handling is kept.**
   - `T1` runs inside `Transactions::retrying()` (D-022; `app/Support/Transactions.php`): MySQL error **1213** / SQLSTATE **40001** (deadlock) is retried, at most `MAX_ATTEMPTS = 3` times and only for the outermost transaction; nested calls propagate. A lock wait timeout (**1205**) is not retried, because the request already waited the full `innodb_lock_wait_timeout`.
   - When retries run out, or a lock wait times out, the result is `503 {"message":"The service is busy. Please try again."}` with `Retry-After: 1`. Nothing was saved, so the client may repeat the request with the **same** key.
   - A retried `T1` starts again from its first statement and may now meet the duplicate key, which leads to **R**.
3. **The failed transaction is fully rolled back before resolution.**
   - `T1` is the outermost transaction (rule 5), so the rollback is a real `ROLLBACK`, not a savepoint. Every lock `T1` held, including the implicit lock on the other institute's index record, is released before **R** begins.
   - **R** asserts `DB::transactionLevel() === 0` before it starts.
4. **No resolution step locks another institute's rows while holding this institute's lock.**
   - R1 holds no locks at all.
   - R2 locks only this institute's `institutes` row, the requested invoice or class, and an existing row **of the same institute**.
   - The only cross-institute lock anywhere is InnoDB's implicit one during `T1`'s `INSERT`, and it ends with `T1`'s rollback.
5. **Database details never reach API responses.**
   - 1213 or lock-wait failures → the generic `503` above. A duplicate key on an idempotency, period, pending or succeeded unique key → the mapped `409`/replay. Any other `QueryException` → a generic `500`.
   - No response, log line or audit row contains SQL, constraint or index names, `Duplicate entry` text, or key values.
   - The exception is mapped without chaining (D-022).
6. **Release requirement:** the two-process tests VP-111, VP-115 and VP-118 (concurrent case) must pass before release. They include a forced-deadlock case showing a retried `T1` ends in exactly one row, or a replay or sanitized `409`, and never a database error in the response.

## Billing cycles (Q63 — unresolved, founder decision required)

**The gap.** `class_fee_versions.billing_cycle` is `monthly|term|one_time` (G5.2). Contract §11.2 takes any `billing_period` and creates an invoice whenever the enrolment is active. `invoices_period_unique (enrollment_id, billing_period, billable_flag)` allows **one non-cancelled invoice per enrolment per period**. For a `one_time` class, an invoice for `2026-11` and another for `2026-12` have **different** `billing_period` values, so both are accepted: **a one-time fee could be billed twice** (or monthly). For `term`, no term calendar exists anywhere in the sources, so "one invoice per term" cannot be expressed.

**Choices (Q63 stays Open until the founders select one):**

- **A. Monthly only (smallest).**
  - E4 invoices only enrolments whose fee version in force on `issued_on` has `billing_cycle = 'monthly'`.
  - A `term` or `one_time` fee version → `422` on `billing_period`, with the message "Invoicing for this billing cycle is not available in the demo".
  - The schema above is sufficient. Tests: VP-119 (A).
- **B. Monthly plus one-time.**
  - **Monthly** is as A.
  - **`one_time`:** at most **one non-cancelled invoice per enrolment for its lifetime**, checked under the institute → class → enrolment locks, where every invoice writer holds them. A plain read for any non-cancelled `one_time` invoice of that enrolment happens before insert; one exists → `409` `reason: already_invoiced` (a new code, contract amendment). `billing_period` stores the month of issue.
  - **Optional DB backstop (PROPOSAL, only under B):** a snapshot column `billing_cycle_snapshot` plus a generated `period_key = IF(billing_cycle_snapshot = 'one_time', 'ONCE', billing_period)` in the unique key instead of `billing_period`. Without it, the rule is service-only and tested.
  - Tests: VP-119 (B).
- **`term` (both choices):** **deferred**. Term invoices are refused like A, because no term calendar or term-to-period mapping is approved. No production term-billing behaviour is invented here.

**Recommended selection for founder confirmation: Option A — monthly-only demo billing.** This is a recommendation only; Q63 stays **Open** until the founders confirm it.
- Monthly fee versions are supported. A `term` or `one_time` fee version in force on `issued_on` → `422` on `billing_period` ("Invoicing for this billing cycle is not available in the demo").
- **No** `billing_cycle_snapshot` column and **no** lifetime invoice-uniqueness rule. `invoices_period_unique` stays as specified, and is sufficient because only monthly invoices can exist.
- One-time and term billing semantics are **deferred**. No advance, partial or pro-rated billing is added.
- **After approval:** contract §11.2 gains the monthly-only rule and its `422`; VP-119 uses its option A form; E4's `InvoiceService` refuses non-monthly fee versions before insert.

## Billing date eligibility (future-dated enrolments; Q77 — PROPOSED)

**The gap.** An enrolment can have `status = 'active'` while its `enrolled_on` is still in the future (D-024: `enrolled_on` may be in the future, and the place counts from creation). Contract §11.2 only says "Enrolment must be active", so as written it would let the institute bill for a month before the student starts.

**Proposed rule** (PROPOSAL; Q77, pending founder confirmation). An invoice can be created only when **all** of these hold, checked **after** the institute → class → enrolment locks, on the locked rows:
1. the class has `status = 'active'` (otherwise `409 not_active`, Q73);
2. the enrolment has `status = 'active'` (otherwise `409 not_active`);
3. the enrolment is **effective on `issued_on`**, by the G5.3 rule 3 definition: `enrolled_on <= issued_on AND (withdrawn_on IS NULL OR issued_on < withdrawn_on)`. Under rule 2, `withdrawn_on` is NULL, so this reduces to `enrolled_on <= issued_on`.

**Response for an ineligible billing date:** `422` keyed on `issued_on`, with `{"message":"The enrolment is not effective on this invoice date.","errors":{"issued_on":["The enrolment is not effective on this invoice date."]}}`. It is an invalid request value detected by a business rule, so it uses the §1.3 `422` shape with no `reason`. The checks run in the order 1 → 2 → 3, then the fee-version lookup (`422` if none).

| Case (class active, enrolment active, `enrolled_on = 2026-12-01`) | `issued_on` | Result |
|---|---|---|
| Future-dated enrolment, invoice dated today, before it starts | `2026-11-15` | `422` on `issued_on`; no invoice |
| Invoice date before `enrolled_on` | `2026-11-30` | `422` on `issued_on`; no invoice |
| Invoice date equal to `enrolled_on` | `2026-12-01` | Allowed (`201`), subject to the fee version and Q63 |
| Invoice date after `enrolled_on` | `2026-12-15` | Allowed (`201`), subject to the fee version and Q63 |

- **No advance billing** is introduced: a parent cannot be invoiced before the enrolment takes effect. Advance billing would need its own decision.
- **Test:** VP-123.

## Money arithmetic and precision

- **Authoritative amounts:** every amount is computed and stored by the server (contract §1.1). Client amounts are rejected (`422`). Money travels as strings with exactly two decimals (contract §1.5) and is read from `DECIMAL(15,2)` columns as strings, never as floats.
- **Range:** `DECIMAL(15,2)` holds up to `9,999,999,999,999.99`.
  - `total = subtotal + platform_fee` can exceed that when the subtotal is near the maximum. The service checks `total` against the maximum before insert and refuses an out-of-range result with `422` on the fee (it never truncates).
  - A class fee that large is not realistic, but the boundary is defined and tested (VP-120).
- **No binary floating point:** no `float`, `round()`, `*` or `/` on money anywhere. That includes PHP floats, JSON numbers and JavaScript `Number` arithmetic in the frontend, which displays API strings only.
- **Percentage, half-up:**
  - `fee = subtotal × percentage / 100`, rounded half-up at 2 decimals. In integer terms: `fee_cents = floor((subtotal_cents × percentage_hundredths + 5000) / 10000)`; every operand is non-negative.
  - Implemented with **bcmath** strings: `bcdiv(bcadd(bcmul($subtotalCents, $pctHundredths, 0), '5000', 0), '10000', 0)`, then formatted with two decimals.
  - Examples:
    - `333.33 × 2.50 %` = `8.33325` → `8.33`;
    - `1.00 × 0.50 %` = `0.005` → `0.01`;
    - `1234.50 × 12.50 %` = `154.3125` → `154.31`;
    - `2500.00 × 100.00 %` = `2500.00`.
- **Runtime check:**
  - The money services fail closed, as a configuration error (`500`, logged without amounts), if `extension_loaded('bcmath')` is false. The check runs once per process, and a boot-time check is also reported by a health check.
  - **Runtime check, 2026-11-02 (R4 final review):** the project's CLI PHP is `C:\php85\php` (PHP **8.5.12**, NTS x64, loaded configuration `C:\php85\php.ini`). There, `extension_loaded('bcmath')` returns **`true`**, and `bcadd('9999999999999.99','0.01',2)` returns `10000000000000.00`. That is the runtime used by `php artisan test` and the development server.
  - The staging and production PHP are **not verified**; they must pass the same check before E4 is deployed. No extension or package is installed by this specification.
- **Fallback:**
  - **Native integer arithmetic is not proven safe for the full range.** `subtotal_cents` can reach `999,999,999,999,999` (≈ 1.0 × 10¹⁵), and multiplied by `percentage_hundredths` up to `10,000` that is ≈ 1.0 × 10¹⁹, above PHP's `PHP_INT_MAX` (≈ 9.22 × 10¹⁸).
  - An integer fallback would be allowed **only** with an approved, validated cap of `subtotal_cents ≤ 922,337,203,685,477` (`subtotal ≤ 9,223,372,036,854.77` LKR), under which `subtotal_cents × 10,000 + 5,000` stays below `PHP_INT_MAX`. That cap is below the `DECIMAL(15,2)` maximum. No cap is proposed, so **bcmath is required and there is no fallback**.
  - Note: `brick/math` (1.0.0) is already installed as a dependency of `laravel/framework`. Laravel's `decimal:2` cast uses it with half-up rounding. Using it directly would be an alternative arbitrary-precision engine, but depending on it directly needs approval (Q75).
- **Tests:**
  - VP-120: boundaries and rounding, including `.xx5` ties, the minimum `0.01`, `100 %` and the maximum total.
  - VP-121: a missing bcmath fails closed.

## R4 migration order (M4)

Appended after `2026_10_29_000018` (append-only, D-014). Every FK points to an earlier table; there are no cycles or deferred FKs.

| # | Migration | Creates | FKs to |
|---|---|---|---|
| 21 | `2026_10_29_000019_add_g7_parent_keys.php` | **ALTER, index only:** `class_enrollments_id_tenant_unique (id, class_id, student_id, institute_id)` and `class_fee_versions_id_class_unique (id, class_id)` (Q57) | — |
| 22 | `2026_10_29_000020_create_commission_rules_table.php` | `commission_rules` (+ `scope_target_id`, `active_flag`, CHECKs) | `institutes`, `classes` (composite), `users` |
| 23 | `2026_10_29_000021_create_invoices_table.php` | `invoices` (+ `billable_flag`, `(id, institute_id)` unique, CHECKs) | `class_enrollments` (composite), `class_fee_versions` (composite), `guardian_profiles`, `commission_rules`, `users` |
| 24 | `2026_10_29_000022_create_invoice_items_table.php` | `invoice_items` (+ CHECKs) | `invoices` |
| 25 | `2026_10_29_000023_create_payments_table.php` | `payments` (+ `pending_flag`, `succeeded_flag`, `(id, institute_id)` unique, CHECKs) | `invoices` (composite), `users` |
| 26 | `2026_10_29_000024_create_payment_transactions_table.php` | `payment_transactions` (+ CHECKs) | `payments` |
| 27 | `2026_10_29_000025_create_platform_commissions_table.php` | `platform_commissions` (+ CHECKs) | `payments` (composite), `commission_rules` |
| 28 | `2026_10_29_000026_create_boost_products_table.php` | `boost_products` (+ CHECKs) | — |
| 29 | `2026_10_29_000027_create_boost_purchases_table.php` | `boost_purchases` (+ CHECKs) | `classes` (composite), `boost_products`, `users` |

Seeders (not migrations): `CommissionRuleSeeder` (one platform rule), `BoostProductSeeder` (one demo product). Both are idempotent and are added to `DatabaseSeeder`.

**Composite FK parent keys**, verified against §0.C-4: the parent has a **unique** index whose first columns are the referenced columns, in the same order, with identical `BIGINT UNSIGNED` types.

| Child FK | Parent unique key | Exists? | Named child index |
|---|---|---|---|
| `commission_rules (class_id, institute_id)` | `classes_id_institute_unique (id, institute_id)` | **yes** (G5.1) | `commission_rules_class_institute_index` |
| `invoices (enrollment_id, class_id, student_id, institute_id)` | `class_enrollments_id_tenant_unique` | **new** in #21 (Q57) | `invoices_enrollment_tenant_index` |
| `invoices (fee_version_id, class_id)` | `class_fee_versions_id_class_unique` | **new** in #21 (Q57) | `invoices_fee_version_class_index` |
| `payments (invoice_id, institute_id)` | `invoices_id_institute_unique` | created in #23 | `payments_invoice_institute_index` |
| `platform_commissions (payment_id, institute_id)` | `payments_id_institute_unique` | created in #25 | `platform_commissions_payment_institute_index` |
| `boost_purchases (class_id, institute_id)` | `classes_id_institute_unique` | **yes** (G5.1) | `boost_purchases_class_institute_index` |

- **No `CASCADE`, `SET NULL` or `SET DEFAULT` anywhere.**
  - The generated columns use `status` (invoices, payments) and `effective_to` (rules), none of which has an FK. `commission_rules.scope_target_id` uses `institute_id`/`class_id`, whose FKs are `RESTRICT` only (§0.C-3).
  - The CHECKs that name FK columns (`commission_rules_scope_target_chk`) are allowed with `RESTRICT` (§0.C-2, VP-1).
- **Migration #21 alters two applied tables** (`tuitionhub` batch 3) by adding unique indexes only. Existing rows already satisfy them, because `id` is unique. `down()` drops the two indexes.
- **`down()` drops each table;** rolling back M4 reverses #29 → #21 and leaves G1–G6 data intact.
- **The first run goes only to `tuitionhub_test`,** through the normal suite (`RefreshDatabase`). Applying M4 to `tuitionhub` needs its own approval and a confirmed backup, as for M3.

### M4 migration readiness review (R4 corrections, 2026-11-02)

Re-checked for `2026_10_29_000019`–`000027` (#21–#29). Documentation review only; nothing was run.

| Check | Result |
|---|---|
| **#21: index-only changes to applied tables** | Two `ADD UNIQUE INDEX` statements; no column, type, default or data change. `id` is the primary key, so every superset of `id` is already unique: the build cannot fail on existing rows. InnoDB builds a secondary unique index in place. The existing composite FKs that reference these tables (`attendances_enrollment_class_fk` → `class_enrollments_id_class_unique`) keep their own parent keys untouched. |
| **#21 approval** | Applying #21 to the **development database** `tuitionhub` changes two tables that already hold data. It requires **its own approval and a confirmed backup**, separately from running M4 on `tuitionhub_test`. |
| **Composite FK parent keys** | All six listed above. Each parent is a **unique** index whose leading columns match the FK's columns in order, all `BIGINT UNSIGNED` (§0.C-4). Two parents are new (#21); two are created in #23/#25 before their children (#25/#27); two exist (G5.1). |
| **Generated columns** | `scope_target_id`, `active_flag` (rules), `billable_flag` (invoices), `pending_flag`, `succeeded_flag` (payments): all `STORED`, deterministic, and referencing no `AUTO_INCREMENT` column. Only `scope_target_id` reads FK columns, whose FKs are `RESTRICT` (§0.C-3). Each sits in a unique index with its NULL-means-inactive meaning (§0.9). The model guard `GuardsGeneratedColumns` covers them (VP-104). |
| **CHECK constraints** | Use only same-row columns and built-in deterministic functions. The FK columns named in `commission_rules_scope_target_chk` have `RESTRICT` FKs only (§0.C-2, VP-1). The `REGEXP` CHECKs (`billing_period`, `invoice_no`, `payment_no`) must be confirmed on MySQL 8.4 (VP-105). If MySQL refuses them, the fallback is a service rule plus a test, not a weaker CHECK. |
| **FK actions** | Every FK is `ON DELETE RESTRICT` with `ON UPDATE` unset. No `CASCADE`/`SET NULL`/`SET DEFAULT` (the draft's `invoice_items` `CASCADE` is removed). |
| **Backward dependencies** | `down()` must run strictly #29 → #21. #21's indexes are FK parents of `invoices` (#23); MySQL refuses to drop an index an FK needs, so #21 can only be rolled back after #23. Rolling back to batch 3 removes only G7/G8 tables and the two added indexes. |
| **Destructive changes to existing rows** | None. No existing column is altered and no row is updated or deleted. Seeders insert two rows (one rule, one product), idempotently. |

**M4 readiness: BLOCKED — on decisions, not on defects.** No technical defect was found in the corrected specification. M4 cannot start until:
- D-029 approves Part 4;
- Q57 (the #21 ALTER, or the no-ALTER alternative) and Q63 (billing cycles) are decided;
- the remaining Q58–Q77 proposals are accepted or changed;
- #21 has its separate development-database approval and backup before it reaches `tuitionhub`.

## R4 validation plan (VP-84 onward; to run in M4/E4, not executed)

"DB" cases are direct inserts asserting the MySQL error code and the constraint name. "Model" and "Service" cases need the models or the E4 services.

| ID | Kind | Test | Expected |
|---|---|---|---|
| VP-84 | DB | **Cross-tenant invoice:** an invoice whose enrolment belongs to another class, student or institute (each column varied) | 1452 `invoices_enrollment_tenant_fk` |
| VP-85 | DB | **Foreign fee version:** a fee version of another class | 1452 `invoices_fee_version_class_fk` |
| VP-86 | DB | **Duplicate period:** two `open` invoices for one enrolment and period; then cancel one and insert again; then two cancelled | 1062 `invoices_period_unique`; accepted; accepted |
| VP-87 | DB | **Money CHECKs:** `total ≠ subtotal + platform_fee`; `subtotal 0`; negative fee; payment `amount ≠ net + fee`; `net_to_institute 0`; commission `amount −1` | 3819, each naming its CHECK |
| VP-88 | DB | **Demo pins:** `is_demo = 0` on invoices, payments, commissions and products; `provider = 'stripe'`; `gateway = 'payhere'`; `currency = 'USD'` | 3819, each naming its CHECK |
| VP-89 | DB | **Vocabularies:** invoice `overdue`/`draft`; payment `refunded`/`processing`; commission `settled`; boost `demo_paid`; item type `platform_fee`; transaction event `refund` | 3819 |
| VP-90 | DB | **Second pending attempt** for one invoice; then complete the first and insert another pending | 1062 `payments_pending_unique`; accepted |
| VP-91 | DB | **Second succeeded payment** for one invoice | 1062 `payments_succeeded_unique` |
| VP-92 | DB | **Idempotency keys:** a duplicate payment key; a duplicate boost key | 1062 `payments_idempotency_key_unique`; 1062 `boost_purchases_idempotency_key_unique` |
| VP-93 | DB | **Double accrual:** a second commission for one payment; a commission whose institute differs from the payment's | 1062 `platform_commissions_payment_unique`; 1452 `platform_commissions_payment_institute_fk` |
| VP-94 | DB | **Payment completion CHECK:** `succeeded` without `paid_at`; `pending` with `completed_at`; `failed` with `paid_at` | 3819 `payments_completion_chk` |
| VP-95 | DB + Service | **One tuition item:** a second `tuition` item | 1062 `invoice_items_invoice_type_unique`; the service writes exactly one, with `amount = subtotal` |
| VP-96 | DB + Service | **Commission rules:** a second open platform rule; a second open rule for one institute or class; a class rule for a class of another institute; `flat` with a percentage; `percentage` of 0 or 101; `platform` with an `institute_id` | 1062 `commission_rules_active_unique`; 1452 `commission_rules_class_institute_fk`; 3819 for the rest. Service: no platform rule in force → `500` (fail closed) |
| VP-97 | Service (E4) | **Resolution and snapshot:** a class rule beats an institute rule, which beats the platform rule. Rs 2,500 + flat 100 = 2,600, owed 2,500. A percentage rounds half-up to 2 decimals. Changing the rule or the fee afterwards leaves the invoice, payment and accrual unchanged | As described |
| VP-98 | Service (E4) | **Payment idempotency:** a replay with the same key, invoice and payer → `200` with the original; the same key with another invoice or payer → `409`; client amounts → `422` | As described |
| VP-99 | Service (E4) | **Completion:** `succeeded` → payment `succeeded`, invoice `paid`, exactly one accrual. Repeating the same outcome → `200`; a different outcome → `409`. A second attempt on a paid invoice → `409` with the attempt unchanged | As described |
| VP-100 | Service (E4) | **Demo flag:** the completion route returns `404` when the flag is off and when `APP_ENV=production` | As described |
| VP-101 | Service (E4) | **Billing access (Q36):** any actively linked guardian can view and pay; a revoked guardian → `404`; `super_admin` and institute users cannot create attempts (`403`); `billed_guardian_id` never changes | As described |
| VP-102 | DB + Model | **Immutability and history:** changing an invoice's amount or snapshot, or a payment amount; deleting an invoice with items or payments, a payment with transactions or an accrual, a used rule or product | `LogicException` before any query; 1451 with the named FK |
| VP-103 | Service (E4) | **Boost:** a purchase stores the product price snapshot; changing the product price later leaves it unchanged; an inactive product → `422`; the catalogue (§7.5) ordering is identical before and after a purchase | As described |
| VP-104 | DB + Model | Writing `scope_target_id`, `active_flag`, `billable_flag`, `pending_flag` or `succeeded_flag` directly | 3105; the model refuses before any query |
| VP-105 | Structure | `DECIMAL(15,2)` on every money column and `DECIMAL(5,2)` on percentages; `DATETIME`/`DATE` types; no `deleted_at` and no settlement/payout/refund column anywhere; generated expressions; the three `REGEXP` CHECKs (`billing_period`, `invoice_no`, `payment_no`) accepted by MySQL 8.4; `invoice_no`/`payment_no` are `VARCHAR(48)`; every named index; FK rules `RESTRICT`; migration order | As specified |
| VP-106 | Model | Morph aliases `commission_rule`, `invoice`, `invoice_item`, `payment`, `payment_transaction`, `platform_commission`, `boost_product`, `boost_purchase`. Public UUIDs only where §11/§12 address rows. No internal ids serialized | As described |
| VP-107 | Service (E4) | **Lock order:** the executed SQL for each write in the lock table takes its explicit locks in the documented order (as D-027's lock-order tests) | As described |
| VP-108 | Logging | No invoice, payment or boost path logs amounts with personal data, payer phones or idempotency keys | Captured logs are clean |
| VP-109 | DB + Service | **Number assignment:** insert an invoice and a payment with a 40-character `TMP-<uuid>` placeholder, then finalise in the same transaction. Separately: a value of 49 characters; a malformed number (`INV-2026-13-000001`, `ABC`); a transaction that fails after the placeholder insert | Accepted and finalised to `INV-YYYY-MM-NNNNNN` / `PAY-YYYY-MM-NNNNNN`; 1406 (too long); 3819 `invoices_invoice_no_chk`/`payments_payment_no_chk`; after the failure no row exists. After every service call no committed row starts with `TMP-` |
| VP-110 | Service (E4) | **Active class required:** an active enrolment in a `draft` class, then in an `archived` class; a withdrawn enrolment in an active class | `409 not_active` each time, checked after the institute → class → enrolment locks; no invoice row |
| VP-111 | Service (E4), **two processes** | **Concurrent same-key attempts:** two processes send the same key for the same invoice and payer at once | One `201`, one `200` replay of the same payment; exactly one payment row and one `attempt_created` transaction |
| VP-112 | Service (E4) | **Same key, another invoice** (same institute, same payer) | Sanitized `409 idempotency_key_reused`; no new row; the body names nothing about the first payment |
| VP-113 | Service (E4) | **Same key at another institute:** the key exists for institute B; a payer at institute A reuses it | Sanitized `409`; the response, logs and audit name nothing from institute B. The executed SQL shows no explicit lock and no `FOR SHARE` on B's rows |
| VP-114 | Service (E4) | **Replay after the winner commits:** the second request arrives after the first committed (pre-check hit), and also via a forced 1062 in step B | `200` with the original payment in its current state, read in a fresh transaction (not the failed transaction's snapshot) |
| VP-115 | Service (E4), **two processes** | **No double success / accrual:** two processes complete the **same** pending attempt with `succeeded` at once. Afterwards, a new attempt is started on the now-paid invoice | Exactly one `succeeded` payment and exactly one `platform_commissions` row. The second completion returns the same result (`200`, same outcome); the new attempt → `409` (invoice not open) |
| VP-116 | Service (E4) | **Lock order on every idempotency path** (normal, pre-check hit, 1062 → resolve, completion), from the executed SQL | Locks are always own institute → invoice/class → payment/purchase; none reversed; never another institute's row locked by the service |
| VP-117 | Logging | **Keys never logged:** a 1062 on an idempotency key, plus replays and conflicts, with logs captured | No idempotency key, and no `Duplicate entry` message, in logs, audit rows or responses |
| VP-118 | Service (E4) | **Boost idempotency:** the same key, class and purchaser → `200` replay; another class → `409`; another institute → sanitized `409`; concurrent duplicate (two processes) | As described; exactly one purchase row |
| VP-119 | Service (E4) | **Billing cycles (per the Q63 choice).** A: `term` and `one_time` fee versions → `422`, no invoice. B: a second `one_time` invoice for the same enrolment in a later month → `409 already_invoiced`; after cancelling the first, allowed; `term` → `422` | As described |
| VP-120 | Service (E4) | **Money precision:** the rounding examples in "Money arithmetic and precision"; ties at `.xx5`; `0.01` subtotal; `100.00 %`; flat `100.00` (2500 → 2600); `subtotal` at the `DECIMAL(15,2)` maximum with a fee that would push `total` over it | Exact string results; the overflow case → `422`, nothing written; no float anywhere (amounts asserted as strings) |
| VP-121 | Service (E4) | **bcmath missing:** the extension check reports bcmath unavailable (injected checker) | Invoice and payment creation fail closed; no row written; the log has no amounts |
| VP-123 | Service (E4) | **Billing date eligibility (Q77):** class and enrolment active, `enrolled_on = 2026-12-01`; invoice `issued_on` `2026-11-15`, `2026-11-30`, `2026-12-01`, `2026-12-15`. Plus the check order: an archived class with a future enrolment → `409 not_active` (rule 1 before rule 3) | `422` on `issued_on` for the first two, nothing written; `201` for the last two (monthly fee version per Q63); `409 not_active` for the archived class |
| VP-122 | Service (E4) | **Demo labels:** every §11/§12 response, including errors that return a money object | Every money object has `demo: true`; every response carries a `demo_label` (after the Q76 contract amendment) |

## R4 open questions (Q57 onward)

| # | Topic | Proposal | Status |
|---|---|---|---|
| Q57 | Migration #21 adds unique keys to two **applied** tables (`class_enrollments`, `class_fee_versions`) so that one composite FK pins enrolment/class/student/institute and fee/class | Approve the ALTER (index only, non-destructive). **Alternative:** no ALTER; use the existing `(id, class_id)` and `(class_id, institute_id)` parents, and leave "the enrolment's student is the invoice's student" and "the fee belongs to the class" as tested service rules | **Open** |
| Q58 | X8: `fee_amount_snapshot` column | Not built; `subtotal` is the snapshot and `fee_version_id` its source. Contract §2.3 to be amended | **Open** |
| Q59 | Zero-fee rules (`flat_amount = 0`), e.g. a promotion | Not allowed (`> 0`); a zero rule would need a decision on C3 transparency | **Open** |
| Q60 | Percentage precision and range | `DECIMAL(5,2)`, `0 < p <= 100` (the draft had `DECIMAL(7,4)`) | **Open** |
| Q61 | Seed `effective_from` for the platform rule | `2026-01-01`, before any demo invoice | **Open** |
| Q62 | `invoice_no` / `payment_no` generation (unique, readable) | `VARCHAR(48)`. Insert with a 40-character `TMP-<uuid>` placeholder, then set `INV-YYYY-MM-` / `PAY-YYYY-MM-` + zero-padded `id` by primary key in the same transaction ("Invoice and payment numbers"); format CHECKs; VP-109. Numbers are global with gaps after rollbacks. **Alternative:** a counter table (new; extra lock) for gap-free per-month sequences | **Open** |
| Q63 | Billing cycles: `invoices_period_unique` lets a `one_time` enrolment be invoiced again in a different month; `term` has no calendar | **Founder choice between A (monthly only; `term`/`one_time` → `422`) and B (monthly plus one-time, with a lifetime one-time rule under the class/enrolment locks and an optional DB backstop)**. `term` is deferred under both. See "Billing cycles". **Recommended: A — monthly-only demo billing** (no snapshot column, no lifetime-uniqueness rule; one-time and term deferred) | **Open — founder decision required** |
| Q64 | `invoices.created_by_user_id` | Store the billing user (the audit row also records it) | **Open** |
| Q65 | Invoice cancellation | No endpoint in E4; the `cancelled` status and the unique key support a later one | **Open** |
| Q66 | Idempotency key format | A client UUID, stored as `CHAR(36)` and validated as a UUID (`422` otherwise); the draft had `VARCHAR(100)`. Keys are never logged, audited or echoed | **Open** |
| Q67 | Gateway JSON payload columns | Not built for the mock gateway; added with a real gateway after a PII review | **Open** |
| Q68 | Commission snapshot columns on payments | Not duplicated; payments copy only `platform_fee`, while invoices and accruals hold the rule snapshot | **Open** |
| Q69 | Demo boost product price | `1500.00` LKR, from the contract example | **Open** |
| Q70 | Boost product name snapshot | Not built; the response reads the (seeded, unchanged) product name | **Open** |
| Q71 | Commission `scope` snapshot column (`commission_scope_snapshot`), not listed in contract §2.3 | Add it, so `Invoice.commission.scope` and §11.8 never depend on the rule row | **Open** |
| Q72 | The institute lock serialises payments with all other writes of that institute | Accepted for the demo sprint (as Q56) | **Open** |
| Q73 | Invoice creation also requires an **active class** (`draft`/`archived` → `409 not_active`), checked after the institute → class → enrolment locks | Adopt; amend contract §11.2 (it states only the enrolment rule) | **Open** |
| Q74 | Idempotency collisions, including a key used at another institute | Adopt "Idempotency keys and duplicate-key resolution": authorization first; replay only for the same identity at the same institute; otherwise a sanitized `409 idempotency_key_reused` (new reason code, contract §1.3/§11.6/§12.2 amendment); resolve in a fresh transaction; never lock another institute's row explicitly; outermost transaction only | **Open** |
| Q75 | Arbitrary-precision engine | `bcmath` (enabled in development; required in production; fail closed if missing). **Alternative:** `brick/math`, already installed via `laravel/framework`, if approved as a direct dependency | **Open** |
| Q76 | `demo_label` on §12.1 products and the §11.7 completion payload | Add it, so every money response carries the label (contract amendment) | **Open** |
| Q77 | Billing a future-dated enrolment (`status = 'active'`, `enrolled_on` after `issued_on`) | Invoice only when the enrolment is **effective on `issued_on`** (`enrolled_on <= issued_on`), checked after the institute → class → enrolment locks; otherwise `422` on `issued_on`. No advance billing. See "Billing date eligibility"; VP-123 | **Open — PROPOSED** |

## M4 + E4 implementation outline (after approval)

- **M4 — schema only** (as M3): migrations #21–#29; models `CommissionRule`, `Invoice`, `InvoiceItem`, `Payment`, `PaymentTransaction`, `PlatformCommission`, `BoostProduct`, `BoostPurchase`.
  - Model behaviour: casts (`decimal:2` for money), hidden internal ids, `HasPublicUuid` where §11/§12 address rows, `GuardsGeneratedColumns`, immutability guards.
  - Wiring: morph aliases; the two seeders; `SchemaFixtures` helpers.
  - Tests: constraint tests VP-84–VP-96 (DB half), VP-102, VP-104–VP-106.
  - Verify on `tuitionhub_test` only. Applying to `tuitionhub` is a separate approval with a backup.
- **E4 — endpoints** (contract §11.2–§11.8, §12.1–§12.3):
  - Services: `CommissionResolver` (bcmath, half-up, runtime extension check), `InvoiceService` (active class and enrolment, numbers, billing-cycle rule per Q63), `PaymentService` (attempt + demo completion), `BoostPurchaseService`.
    - All run under `Transactions::retrying()` with the lock order above.
    - The idempotency resolution protocol applies, and these services are outermost transactions only.
  - Policies: billing user, any active linked guardian, `super_admin` read-only.
  - Routing: the demo-completion route registered only when `DEMO_PAYMENTS_ENABLED=true` and not in production.
  - Responses: `demo: true` and the labels on every response.
  - Tests: VP-97–VP-101, VP-103, VP-107, VP-108, VP-110–VP-123, plus authorization matrices and audit payloads. VP-111, VP-115 and VP-118's concurrent case need a **genuine two-process harness**, which is **required before release** (E2c).
  - Docs: contract changelog and a D-entry.
- **Frontend afterwards:** parent fees and the institute fees page move from sample data to §11 (the demo-fee helper `utils/demoFees.ts` is replaced by API snapshots).
