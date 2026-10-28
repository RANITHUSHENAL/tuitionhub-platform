# TuitionHub — Sprint Schema (migration specification)

**Status:** APPROVED (D-013) — Migration Specification. Part 1 covers groups **G1–G3** only (`CLAUDE.md` source order, item 4). Approval is conditional on VP-1: these migrations run first only on the disposable `tuitionhub_test` database (D-013).
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
