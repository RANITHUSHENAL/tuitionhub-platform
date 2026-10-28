# TuitionHub — Sprint Schema (migration specification)

**Status:** APPROVED (D-013) — Migration Specification. Part 1 covers groups **G1–G3** (`CLAUDE.md` source order, item 4). Part 2 (R2: `audit_logs` and G4) is approved separately under D-015. Approval is conditional on VP-1: these migrations run first only on the disposable `tuitionhub_test` database (D-013).
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
  - The CHECK is only a backstop. The guard keeps concurrent verifies from passing the limit. Other OTP details stay open (Q19).
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
| Q19 | OTP details: `code_hash` method (the contract fixes HMAC-SHA256 only for invitations); `max_attempts` with no DB default; `last_sent_at` NOT NULL; resend = new challenge or update; purge of expired challenges | **Fixed now:** the service increments attempts only through a guarded atomic update, `… SET attempts = attempts + 1 WHERE id = ? AND consumed_at IS NULL AND attempts < max_attempts`, and treats 0 affected rows as "attempts exhausted". `otp_challenges_attempts_chk` is the backstop. The rest is decided in the OTP task. | **Open** |
| Q20 | Does MySQL 8.4.13 accept a CHECK on FK columns with explicit `ON DELETE RESTRICT`, or only with `ON DELETE` unset (`user_roles.role_scope`, `user_roles.institute_id`)? | **Explicit `ON DELETE RESTRICT` is accepted** with `user_roles_scope_chk` on MySQL 8.4 (VP-1, `tuitionhub_test`, 2026-10-28). No error; the fallback was not used (D-014). | **Resolved (VP-1)** |
| Q21 | Full lookup seed lists (codes, English/Sinhala/Tamil names, `level`, `sort_order`, exam `year` and dates) | Founders to supply or approve; only the contract examples are fixed | **Open** |
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
     - **An invalid or missing IP is never silently replaced** (no placeholder, no truncation, no guessed value). The handling policy (for example store NULL, or reject the operation) is **not yet decided** and must be approved before the AuditLogger is built (D-016).
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
- **Application rules** (Decided where marked "contract"; otherwise PROPOSAL):
  1. **Create** (§9.1; staff or admin of the managing institute). Everything runs in one transaction, plus the audit row:
     - Lock the student row and the pending invitations for (student, phone) with `SELECT … FOR UPDATE`.
     - Mark any pending invitation whose `expires_at` has passed as `expired`, which clears its `pending_flag`.
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
       - Otherwise compute the HMAC and select the invitation by `code_hash` `FOR UPDATE`.
     - Redemption is rate-limited (contract §9.2: proposed 5 attempts per user per 15 min; `429`).
     - **Recheck under the lock:** status is `pending`, now < `expires_at`, the caller has `phone_verified_at` set, and the caller's phone equals `guardian_phone` (contract).
       - If the invitation has expired, it is marked `expired`.
       - Unknown, expired or used codes and phone mismatches all return one **generic `422`** (contract).
     - If an active link already exists for this student and guardian → `409` (contract; also the DB unique key).
     - Create `guardian_profiles` if missing (contract).
     - **Lock the student row** (`SELECT … FOR UPDATE`). This serialises concurrent acceptances for the same student (Q25).
     - Insert the `student_guardians` row with `linked_by_user_id` = **inviter**, `linked_at` = now, `invitation_id` (contract).
       - `is_primary = 1` if the student has **no active primary** link; otherwise `0` (Q25).
     - Set the invitation to `accepted` / `accepted_at` / `accepted_by_user_id`.
     - Audit `guardian_invitation.accepted` and `guardian_link.created` (contract).
     - Link creation and invitation consumption commit together or not at all.
     - In `log` mode, `phone_verified_at` is **not** real phone verification (C6).
  3. **Revoke** (§9.3): lock the row. Only `pending` → `revoked`, setting `revoked_at`/`revoked_by_user_id`; otherwise `409` (contract). Audited.
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
    - **Lock the student row first** (`SELECT … FOR UPDATE`), then the link row.
    - Set `revoked_at` = now and `revoked_by_user_id`, which clears `active_flag` and `primary_flag`.
    - Promote a new primary if needed (below).
    - Audit `guardian_link.revoked`.
  - **Access** is checked against an active row on **every request** (contract §3, §9.4: "lost immediately"). Nothing is cached in the session.
  - **Primary guardian** (Q25, approved D-015; contract v0.3 §9.2, §9.4):
    - **The student row is locked** (`SELECT … FOR UPDATE`, taken before any link row) for **every** primary-guardian creation, revocation and promotion. The student row is always locked first, so concurrent operations on the same student run one at a time and cannot deadlock.
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
| — | AuditLogger handling of an invalid or missing IP | Must be decided before the AuditLogger is built; never silently substituted (R2.0 rule 9, D-016) | **Unresolved (before AuditLogger)** |
