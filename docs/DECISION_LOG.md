# TuitionHub — Technical Decision Log

**Purpose:** Record of technical and environment decisions, with the evidence behind each one. Product scope decisions (C1–C9, open items) live in `docs/PRODUCT_DECISIONS.md`. This log records how those decisions were put into practice.
**Branch at time of writing:** `sprint/day1-api-scaffold`
**Recorded:** 2026-10-27

Each entry separates **Verified** facts (checked by a command, with its output on record) from **Pending** checks (not yet confirmed). An entry is not complete until its pending checks are closed.

> Naming note: the approved Day 1 plan used `D1`/`D2` for open decisions. Plan `D2` (root lint isolation) is recorded here as **D-008**. Plan `D1` is the C8 role question, approved by the founders and recorded as **D-011**.

---

## D-001 — Monorepo layout and branch discipline

- **Decision:** The React frontend stays at the repository root. The Laravel API lives in `/api`. Sprint work happens on `sprint/*` branches, never directly on `main`.
- **Verified:**
  - `api/` was created by `composer create-project` without changing any root frontend file (`git diff` was empty for root files after scaffolding).
  - Commits so far: `0c3b0fc` (docs), `fdba18a` (scaffold), `fdb6395` (lint), `1f3aaf5` (logs placeholder), `765843f` (MySQL/UTC config). None has been pushed or merged.
- **Pending:** none.

## D-002 — Local PHP runtime: manual PHP install instead of Laravel Herd

- **Decision:** Use a manually installed PHP 8.5 (non-thread-safe, x64) build at `C:\php84` as the command-line PHP. The folder name is historical; the version inside is 8.5. Laravel Herd was abandoned. XAMPP's PHP 8.2.12 is no longer the `php` on PATH.
- **Verified:**
  - `php -v` → PHP 8.5.12 (NTS, Visual C++ 2022 x64). `which php` → `C:\php84\php`.
  - Composer 2.8.12 runs on `C:\php84\php.exe`.
  - Extensions the plan requires are loaded: pdo_mysql, mbstring, openssl, fileinfo, curl, zip, intl, sodium, tokenizer, xml, ctype, bcmath.
  - The Herd 2.0.1 installer failed with "Error opening file for writing: …\HerdHelper.exe". The installer had a valid signature from Beyond Code GmbH. No process was locking the file. Bitdefender was the active antivirus and had quarantined files at the same times as the install attempts.
- **Not confirmed:** that Bitdefender quarantined `HerdHelper.exe` specifically. The quarantine entries could not be read.
- **Pending:** Windows PHP security build (Brotli). Confirm the installed Windows build is the intended security build for the Brotli item raised by the founder.

## D-003 — C9 runtime and framework version verification

- **Decision:** These versions are the verified local baseline for the sprint (closes C9 for the local environment, subject to the pending checks below).

| Component | Version | Evidence |
|---|---|---|
| PHP (command line) | **8.5.12** | `php -v` |
| Composer | 2.8.12 | `composer --version` |
| Laravel framework | **13.38.0** | `php artisan --version`; `composer.lock` |
| laravel/laravel skeleton | commit `06d016a` | Composer cache archive used for the integrity comparison |
| PHPUnit | 12.5.40 | `composer create-project` output |
| MySQL server | **8.4.13** | `php artisan db:show`; `SELECT version()` |
| Node / npm (frontend) | 24.11.1 / 11.6.2 | `node -v`, `npm -v` (2026-10-19) |

- **Verified:**
  - Laravel 13 officially supports PHP 8.3–8.5 (verified via official docs). Source: the Support Policy table in the Laravel 13.x release notes (`https://laravel.com/docs/13.x/releases`, checked 2026-10-27) lists "13 | 8.3 - 8.5" and states "Laravel 13.x requires a minimum PHP version of 8.3."
  - Laravel 13 requires `"php": "^8.3"`, and Composer resolved every dependency on PHP 8.5 without platform conflicts.
  - The default test suite passes on PHP 8.5.12 (2 tests, 2 assertions).
- **Pending:**
  - The PHP security-build item from D-002.
  - The `mysql` command-line client on PATH is 8.0.46. This doesn't affect the app, but manual tooling uses that older client.

## D-004 — Local database: MySQL 8.4 LTS with a dedicated app account

- **Decision:** Use MySQL 8.4 LTS on `127.0.0.1:3306`. The app connects as a dedicated account, not `root`. XAMPP's MariaDB 10.4 (end of life) is not used.
- **Verified:**
  - Connection `mysql`, database `tuitionhub`, host `127.0.0.1`, port `3306`, 0 tables (`php artisan db:show`, before any migrations).
  - `current_user()` → `tuitionhub_app@127.0.0.1`.
  - An earlier port-3306 conflict between two `mysqld.exe` processes was resolved by the founder outside this repo. The server Laravel now reaches is 8.4.13.
- **Constraint:** the account is bound to host `127.0.0.1`, so `DB_HOST` must stay `127.0.0.1`. With `localhost`, MySQL may match a different account.
  - **MySQL privileges — VERIFIED (2026-10-27).** The founder reviewed both accounts. `SHOW GRANTS FOR CURRENT_USER()` was then run read-only through each connection:
    - `tuitionhub_app@127.0.0.1`: `USAGE ON *.*`; `ALL PRIVILEGES ON tuitionhub.*`; `ALL PRIVILEGES ON tuitionhub_test.*`.
    - `tuitionhub_test_runner@127.0.0.1`: `USAGE ON *.*`; `ALL PRIVILEGES ON tuitionhub_test.*`.
    - Neither account has global privileges or rights on any other database.
  - **Test database — VERIFIED.** `tuitionhub_test` exists and is reachable as `tuitionhub_test_runner` (see D-009, D-010).
- **Observation:** `tuitionhub_app` can also reach `tuitionhub_test`. The app does not need this, and the test guard (D-010) refuses to run tests as `tuitionhub_app`. Revoking it is optional and has not been done. `docs/SETUP_GUIDE.md` grants the app account `tuitionhub` only.
- **Secrets:** `DB_PASSWORD` values exist only in the local, ignored `api/.env` and `api/.env.testing`. They are never committed or printed.
- **Pending:** none.

## D-005 — UTC timezone for the database session

- **Decision:** The MySQL connection uses UTC (`'timezone' => env('DB_TIMEZONE', '+00:00')` in `api/config/database.php`, `mysql` connection only). The application timezone stays UTC. Class schedules are interpreted in `Asia/Colombo` in application code (per `docs/API_CONTRACT.md` §1.5), not by the database.
- **Verified:** `SELECT @@session.time_zone` → `+00:00`. `php artisan about` → Timezone `UTC`. Committed in `765843f`.
- **Pending:** none.

## D-006 — Scaffold method and skeleton integrity

- **Decision:** Scaffold with `composer create-project "laravel/laravel:^13.0" api --prefer-dist --no-scripts`. Skipping the post-create scripts avoids their automatic SQLite database and migration run. `.env` and `APP_KEY` were created by hand afterwards.
- **Verified:**
  - Every tracked `api/` file was compared byte for byte with the cached skeleton archive. The only differences are:
    - `AGENTS.md` and `CLAUDE.md` removed on purpose (D-007).
    - `composer.lock` added (generated at install).
    - `.env.example`, `.gitignore` and `config/database.php` changed (approved configuration).
  - The root `.gitignore` (Vite template) rule `logs` had excluded `api/storage/logs/.gitignore` from the scaffold commit. It was force-added in `1f3aaf5`. A test log file was confirmed to stay ignored.
  - `api/.env.example` line 1 contained `ppAPP_NAME=Laravel`: a stray edit on 2026-10-25, after scaffolding, that went into `fdba18a`. It was restored to `APP_NAME=Laravel` in `765843f`.
- **Pending:** none.

## D-007 — Laravel Boost agent files removed

- **Decision:** Delete the skeleton's `api/CLAUDE.md` and `api/AGENTS.md` (two identical files). Laravel Boost is not installed.
- **Reason:**
  - The files told AI agents to run `composer require laravel/boost --dev` and `php artisan boost:install` before any change.
  - If PHP was missing, they told agents to run a remote install script (`iex (… DownloadString('https://php.new/…'))`).
  - Both conflict with the approval gate on package installs and with running remote code.
- **Verified:** neither file is in any commit. The root `CLAUDE.md` is the only Claude instruction file in the repo.
- **Note:** the root `AGENTS.md` (Codex instructions) is a separate, founder-reviewed file. It is deliberately left untracked.
- **Pending:** none. Adding Boost later would need its own approval.

## D-008 — Root ESLint ignores `api/`

- **Decision:** Add `'api/'` to `ignorePatterns` in the root `.eslintrc.cjs`, so frontend lint does not scan Laravel's `vendor/` JavaScript.
- **Verified:**
  - Before: `npm run lint` failed with 837 problems. 738 errors and 84 warnings came from `api/vendor/**/*.js`; `src/` alone had 0 errors and 15 warnings.
  - After: 0 errors, 15 warnings, exit code 0.
  - `npm run build` passes. Its chunk-size warning was already there and is unrelated.
  - Committed in `fdb6395`.
- **Pending:** none.

## D-009 — Environment files and test database isolation

- **Decision:**
  - `api/.env` and `api/.env.testing` are local only and ignored by Git. `api/.env.example` is committed with safe placeholders.
  - Tests run on **MySQL `tuitionhub_test`** (commit `deb615d`), not SQLite. MySQL-specific behaviour (generated columns, row locks) gets real coverage. Session, cache and mail stay in memory (`phpunit.xml`).
  - *Superseded:* until `deb615d`, tests used the skeleton's SQLite in-memory database (`DB_DATABASE=:memory:`).
- **Verified:**
  - `git check-ignore`: `api/.env` → `api/.gitignore:3`, `api/.env.testing` → `api/.gitignore:6`, `api/.env.example` not ignored.
  - No `DB_*`/`APP_*` Windows environment variables exist that could override `phpunit.xml`.
  - Staged `.env.example` checked: `APP_KEY=` and `DB_PASSWORD=` are empty.
  - `api/.env.testing` has its own test-only `APP_KEY` (`php artisan key:generate --env=testing`). The SHA-256 of `api/.env` was the same before and after, so the development key was not changed.
  - `php artisan test`: 11 passed, 11 assertions (after `d03a376`).
- **Pending:** none.

## D-010 — Test database safety

- **Decision:** Tests may only ever run against `tuitionhub_test`, as a restricted account. Three independent layers enforce this, so one mistake (a stray env variable, a cached config, a copied `.env`) cannot point tests at the development database.
- **Layers:**
  1. **`api/phpunit.xml` forcing.** `DB_CONNECTION=mysql`, `DB_DATABASE=tuitionhub_test` and `DB_URL=` (empty) are set with `force="true"`, so shell or OS environment variables cannot override them. The file holds no usernames or passwords.
  2. **Fail-closed guard in `api/tests/TestCase.php`.** `createApplication()` boots the app, then calls `assertSafeTestDatabase()`. It throws a `RuntimeException` unless all of these hold: `APP_ENV=testing`, default connection `mysql`, database `tuitionhub_test`, empty connection URL, and username `tuitionhub_test_runner`. It runs before `setUpTraits()`, so `RefreshDatabase` and migrations never reach a database that failed the check. The error message never includes the URL, because a URL can contain a password.
  3. **Restricted runner account.** `tuitionhub_test_runner@127.0.0.1` has rights on `tuitionhub_test` only (D-004). Even if the first two layers failed, it could not touch `tuitionhub`.
- **How env files load during tests:** with `APP_ENV=testing`, Laravel loads `api/.env.testing` **instead of** `api/.env`; the two files are not merged. `.env.testing` therefore needs its own `APP_KEY` and full `DB_*` block. The `phpunit.xml` forced values then override what is in it.
- **Verified:**
  - `tests/Unit/TestDatabaseGuardTest.php`: the guard allows (`testing`, `mysql`, `tuitionhub_test`, no URL, `tuitionhub_test_runner`). It rejects a non-testing environment, the `tuitionhub` database, a non-empty URL and a different username. It never echoes the URL's password.
  - `tests/Feature/TestDatabaseConnectionTest.php` (read-only SQL): `SELECT DATABASE()` → `tuitionhub_test`; `SELECT @@session.time_zone` → `+00:00`; the user part of `SELECT CURRENT_USER()` → `tuitionhub_test_runner` (host-independent since `d03a376`).
  - A real run with `APP_ENV=local` stopped in `createApplication()` with the guard's error before any database access.
  - Commits: `deb615d`, `d03a376`.
- **Not supported:** `php artisan test --parallel`. Parallel testing renames the database per process (for example `tuitionhub_test_test_1`) **after** the guard has run, and the runner account has no rights to create those databases. Supporting it would need its own decision and guard change.
- **Pending:** none.

## D-011 — C8 role model — **Approved by founders (2026-10-27; open questions decided 2026-10-28)**

- **Approved by:** Ranithu Shenal, Lahiru Kavya on 2026-10-28.
- **Decision:** A hybrid role model. There is **no `users.type` column.**

| Access | Source of truth |
|---|---|
| `super_admin`, `institute_admin`, `institute_staff` | Rows in a `user_roles` table. Institute roles are scoped by `institute_id`. |
| Institute owner | `institutes.owner_user_id` |
| Teacher | An **active** `teacher_institute` assignment for that institute |
| Guardian (parent) | An **active** `student_guardians` link to that student |

- **Rules:**
  - Institute staff **can** create students and invite parents (guardian invitations).
  - **Only institute admins** can create invoices.
  - Teachers are assigned to an institute **immediately** by an admin. There is no invite or acceptance step in the sprint.
- **Decided (2026-10-28):**
  1. **Owner gets admin.** The institute owner automatically receives `institute_admin` for their institute, server-assigned when the institute is created. So the owner can do everything an admin can, including creating invoices.
  2. **`super_admin` billing is read-only.** `super_admin` may read invoices, payments and commission accruals. It may **not** create invoices or payment attempts.
  3. **Custom `user_roles` table.** `user_roles` is a custom table; `spatie/laravel-permission` is not used.
  4. **Enrolment and link revocation.** Institute staff **may** enrol students in classes. Revoking a guardian link stays **admin-only** (institute admin, or `super_admin`).
- **Effect:** closes C8 and unblocks migration group 3 onwards in the approved Day 1 plan. The `user_roles` vocabulary is limited to the three roles above.
- **Documents aligned (2026-10-28):**
  - `docs/PRODUCT_DECISIONS.md`: C8 moved next to C1–C7 as **Approved**; open items 2 (teacher consent) and 3 (who may bill) marked resolved.
  - `docs/API_CONTRACT.md` v0.2 draft:
    - §3 is no longer a proposal. Ended or revoked relationships grant nothing.
    - §6.2 no longer creates a `teacher` role row, and §9.2 no longer creates a global `guardian` role.
    - `super_admin` is read-only on invoices and payments.
    - §4.2/§4.4 `roles` lists stored roles only. Derived access is returned separately as `teaching_at` and `active_child_link_count`; these are display hints only.
    - §5.3 includes teaching institutes and returns `my_access`.
  - `CLAUDE.md`: the approval-gate example no longer names C8.
- **Still open:** none.

## D-012 — R0 schema reference review: resolutions — **APPROVED by founders (2026-10-28)**

- Approved by: TuitionHub founders on 2026-10-28. Individual approver names pending documentation.
- **Scope:** how the two schema references are used for sprint migrations. They are `docs/reference/TuitionHub_ER_Schema.md` (canonical, historical starting model) and `docs/reference/TuitionHub_Sprint_Schema_Draft.md` (column-level draft, written before C8 and contract v0.2). Both were reviewed in full on 2026-10-28. No credentials, keys or personal data were found. The documents themselves are not edited.
- **Verified (2026-10-28):** SHA-256 of the committed reference files:
  - ER schema (43,524 bytes): `65ca6b89a07e97b2b1028c823a710f224ab0a271e6e0ff66d0d6b4af633c3ade`
  - Sprint draft (41,517 bytes): `8c53666af70b4d2ae8dc95ee3a5a5bfeaf2fe511f0a763175c7ffad8ff566eb5`

### Resolutions

| # | Topic | Decision |
|---|---|---|
| F1 | `users.phone` / `users.email` after soft delete | Plain unique keys. A soft-deleted account's phone or email is **not** reused in the sprint. No `active_flag` on `users`. |
| F2 | `student_profiles.gender` | **Not built.** Less data is collected about minors (C6). |
| F3 | `student_guardians.receives_attendance_sms`, `receives_payment_sms`, `receives_marks_notifications` | **Not built.** SMS is not a baseline dependency and marks are excluded. |
| F4 | `class_sessions.substitute_teacher_id` | **Not built.** It would be a teacher access path that C8 does not define. |
| F5 | `institute_settings` | **Table not built** in the sprint. `parent_pays_commission` and `platform_commission_pct` are never copied (C3). |
| F6 | Schema reference documents | Committed unchanged. Precedence is recorded in `CLAUDE.md`, not by editing the references. |
| F7 | Other `docs/reference/` files | Stay local and untracked: `TuitionHub_ER_Schema (1).html`, `TuitionHub_Business_Plan.md`, `TuitionHub_Details.md`, `TuitionHub_Claude_Workflow_Guide.md`, `TuitionHub_Setup_Guide.md`. `docs/SETUP_GUIDE.md` supersedes the legacy setup guide. |
| — | `roles.guard_name` | **Not built.** There is no Spatie and no guard-based lookup (D-011). |
| — | QR and card attendance fields | **Not built:** `class_enrollments.qr_token`, `class_enrollments.student_card_no`, and any device, geo or scan columns on `attendances`. |
| — | Attendance method | **Manual attendance only** during the sprint (`attendances.method = manual`). |

- **Pending:** individual approver names.

## D-013 — Sprint schema G1–G3 approved — **APPROVED by founders (2026-10-28), conditional on VP-1**

- Approved by: TuitionHub founders on 2026-10-28.
- **Decision:** `docs/SPRINT_SCHEMA.md` Revision 2, groups **G1–G3** (lookups and `roles`; `users`, `user_roles`, `otp_challenges`, `teacher_profiles`, `guardian_profiles`; `institutes`, `institute_staff`, `teacher_institute`), is the approved migration specification for those groups (`CLAUDE.md` source order, item 4).
- **Verified (2026-10-28):** SHA-256 of the approved file: `f95a96ec6b148a2664708e61a4c15199770a9aef1333fc41d8bdfc58fda60089`.
  - **Hash note:** this hash refers to the file as committed in `90e28d2`. Two edits have been made since, so the current file hash differs:
    - The status line now reads "APPROVED (D-013) — Migration Specification".
    - The Q20 outcome is recorded (D-014).
  - No other specification content changed.
- **Condition: VP-1.**
  - The G1–G3 migrations must first pass VP-1 on the disposable `tuitionhub_test` database only (`docs/SPRINT_SCHEMA.md` §0.B, §0.C). Only then may they run against `tuitionhub`.
  - If VP-1 rejects a form (CHECK on the CHECK-bearing `user_roles` FK columns, the composite role FK, `REGEXP_LIKE` or the district list in a CHECK), the affected design returns to the founders. It is never adapted silently.
- **Closed:** Q1–Q18 and Q22–Q24, with the outcomes recorded in the "Open questions" table of `docs/SPRINT_SCHEMA.md`. Q2 and Q7 hold subject to VP-1.
- **Still open:**
  - **Q19:** OTP details. Only the guarded attempt update is fixed.
  - **Q20:** whether explicit `ON DELETE RESTRICT` or an unset `ON DELETE` is accepted on `user_roles.role_scope` and `user_roles.institute_id`. Resolved by VP-1.
  - **Q21:** full lookup seed lists.
- **Prerequisite carried forward:** `audit_logs` is first in R2. No role, institute or staff flow may run before it is migrated.
- **Effect on earlier notes:** for G1–G3, `docs/SPRINT_SCHEMA.md` supersedes the "R1 design notes — not yet approved" below; for example, it uses explicit `dateTime()` rather than `datetimes()`. Those notes stay unapproved for later groups.
- **Header updates:** done in the M1 change. The `docs/SPRINT_SCHEMA.md` status line now reads "APPROVED (D-013)", and `CLAUDE.md` item 4 no longer says "(not yet created)".
- **Pending:** Q19 and Q21; individual approver names. VP-1 and Q20 are resolved in D-014.

## D-014 — M1 G1–G3 migrations verified (VP-1) — **Verified and committed (`02d43d4`); applied to `tuitionhub` on 2026-10-28**

- **Scope:**
  - The G1–G3 migrations from `docs/SPRINT_SCHEMA.md` (D-013), implemented as the edited skeleton `0001_01_01_000000_create_users_table.php` (`users` + `sessions`; no `password_reset_tokens`) plus `2026_10_29_000001` … `2026_10_29_000009`.
  - Database constraint tests in `api/tests/Feature/Schema/`.
- **How it was run:**
  - Only `php artisan test` from `api/`, under the D-010 guard. `RefreshDatabase` ran `migrate:fresh` on **`tuitionhub_test` only**.
  - `migrate`, `migrate:fresh`, `db:seed` and `db:wipe` were never run directly. The `tuitionhub` development database was not touched, and no seeder was run.
- **VP-1: passed** on `tuitionhub_test` (2026-10-28).
- **MySQL version:** **8.4.x** is what this run verified: `SchemaStructureTest` asserts that `SELECT VERSION()` starts with `8.4.`. The exact patch version was **not** re-verified here; D-003 recorded 8.4.13 on 2026-10-27.
- **FK form (Q20):**
  - `user_roles_role_scope_fk` and `user_roles_institute_fk` were created with **explicit `ON DELETE RESTRICT`**, and MySQL accepted them together with `user_roles_scope_chk`, with **no error**.
  - `information_schema.REFERENTIAL_CONSTRAINTS` reports `DELETE_RULE = RESTRICT` and `UPDATE_RULE = NO ACTION` for all ten G1–G3 FKs.
  - The approved fallback (unset `ON DELETE`) was **not used**, and the unset form was not tried.
- **Results:** `php artisan test` reports **46 passed (190 assertions)**, with 0 failed and 0 skipped. That is 11 pre-existing tests plus 35 new ones; all pre-existing tests still pass.
- **VP cases covered** (direct query-builder inserts, each asserting the MySQL error code and the constraint name):
  - **VP-1**, in 8 structure tests:
    - all tables present; `password_reset_tokens` absent
    - `DATETIME` instants and `DATE` calendar columns
    - `STORED GENERATED` `scope_key` (NOT NULL) and `active_flag`
    - all 20 named CHECKs present
    - all 10 FKs and their delete/update rules
    - identical type, charset and collation for `roles.scope` and `user_roles.role_scope`
    - exactly 3 seeded roles
  - **VP-2 to VP-8.**
  - **VP-10 to VP-16.**
  - **VP-23 to VP-25.**
  - VP-24 also documents that `'colombo'` passes `institutes_district_chk` (case-insensitive collation, `docs/SPRINT_SCHEMA.md` §0.1).
- **Deferred to the service tasks:** VP-9, VP-17 to VP-22, VP-26, and the VP-24 check that the server stores the canonical district spelling.
- **Companion changes:**
  - `database/factories/UserFactory.php`: `uuid`, a fictional phone matching `^[+]947[0-9]{8}$`, `password` null, no `email_verified_at`.
  - `database/seeders/DatabaseSeeder.php`: creates no default user. It was not run.
  - `phpunit.xml`: forces `OTP_DRIVER=log` (`force="true"`). This has no effect until the OTP task adds `config/otp.php`.
- **Rule:** no real SMS driver may ever be configured for testing. `phpunit.xml` forces the local `log` driver, and tests never send SMS.
- **Rule — migrations are append-only from this commit:**
  - Once committed, the G1–G3 migrations, including the edited skeleton `0001_01_01_000000_create_users_table.php`, are never edited.
  - Any schema change goes into a new migration, with a matching approved update to `docs/SPRINT_SCHEMA.md`.
- **Open item:** API date serialisation must match contract §1.5 (`"2026-10-19T08:30:00Z"`, no microseconds). Laravel's default output observed in VP-25 is `…T08:30:00.000000Z`. This must be fixed in the API resource / serialisation task.
- **Not changed:**
  - `app/Models/User.php`. It still casts `email_verified_at` and has `name`, `email` and `password` as fillable, which is harmless. It will be reviewed in the auth task.
- **Development database migrated (2026-10-28)**, after founder approval at each step:
  - **Preflight (read-only):** `php artisan db:show` confirmed database `tuitionhub`, MySQL **8.4.13**, account `tuitionhub_app`, and **0 tables** in `tuitionhub`.
  - **`php artisan migrate --pretend`** printed the full SQL for review.
    - With founder approval, its preparation step created the empty `migrations` table. In Laravel 13.38.0, `MigrateCommand::prepareDatabase()` creates it even with `--pretend`.
    - No other statement ran.
  - **`php artisan migrate`:** all 12 migrations completed (`DONE`) with no errors.
  - **Result:**
    - **22 tables** now exist in `tuitionhub`: the 15 G1–G3 tables, `sessions`, `migrations`, and the framework tables `cache`, `cache_locks`, `jobs`, `job_batches` and `failed_jobs`. There is no `password_reset_tokens` table.
    - **The 3 roles were seeded** by the roles migration: `super_admin` → `global`, `institute_admin` → `institute`, `institute_staff` → `institute`. Each has non-null UTC timestamps (checked with `php artisan tinker`, a read-only query).
  - **Not run:** `db:seed`, `migrate:fresh` and `db:wipe`. No other data was written.
  - **Observation:** `db:show` also lists the 22 `tuitionhub_test` tables, because `tuitionhub_app` still has access to `tuitionhub_test` (D-004). Revoking it is optional and has not been done.
- **Pending:** none for M1.

## D-015 — R2 decisions: audit log, guardianship and guardian billing access (Q25–Q37) — **APPROVED (2026-10-28)**

- **Approved by:** Ranithu Shenal on 2026-10-28.
- **Scope:**
  - `docs/SPRINT_SCHEMA.md` Part 2 (R2): `audit_logs`, `student_profiles`, `guardian_invitations`, `student_guardians`.
  - `docs/API_CONTRACT.md` v0.3.
- **Implementation:** this approves the R2 specification and contract v0.3. Writing and running the R2 migrations is still a separate approved task (`CLAUDE.md` approval gate). Their first run happens only on `tuitionhub_test` (VP-37).
- **Approved resolutions:**

| # | Topic | Approved resolution | Contract v0.3 |
|---|---|---|---|
| Q25 | Primary guardian | The first active guardian becomes primary. When the primary is revoked, the oldest remaining active link (`linked_at`, then `id`) is promoted in the same transaction. **The student row is locked** during primary creation, revocation and promotion. No active guardian → no primary. Existing invoices keep `billed_guardian_id`. No manual primary-selection API | §2.3, §9.2, §9.4, §11.2 |
| Q26 | Audit events | Added `role.granted`, `role.revoked`, `staff.added`, `staff.ended`, `teacher_assignment.ended`, `student.created` | §8.1, §13 |
| Q27 | Phones in audit | No full phone numbers; masked form (`+9477*****67`) only | §13 |
| Q28 | Audit IPs | Raw IP in the sprint, with restricted access (no endpoint exposes `audit_logs`). **The retention period stays pending a pre-launch privacy review** | §13 |
| Q29 | Invitation codes | 8 characters from `23456789ABCDEFGHJKMNPQRSTVWXYZ`, secure random source, displayed `XXXX-XXXX`. **Normalisation upper-cases and removes spaces AND hyphens** before HMAC-SHA256 with the dedicated `GUARDIAN_INVITE_HMAC_KEY`. A missing key fails closed (`503`) for invitation flows only; unrelated commands are unaffected. Redemption is rate-limited; the key is never printed or committed | §2.3, §9.1, §9.2 |
| Q30 | Expiry | Lazy, no scheduler. Every read checks `expires_at`; a stored `pending` never overrides an expired timestamp | §9.1, §9.3 |
| Q31 | Invitation/student integrity | Composite FK `student_guardians (invitation_id, student_id)` → `guardian_invitations (id, student_id)`, with parent unique `guardian_invitations_id_student_unique` and child index `student_guardians_invitation_student_index`. A NULL `invitation_id` is not checked (`MATCH SIMPLE`) | schema only |
| Q32 | Audit tenant | `institute_id` may be NULL for platform-level events | §13 |
| Q33 | Append-only | Application-enforced for the sprint; no triggers | — |
| Q34 | Student removal | No student-removal endpoint, status, archive or soft delete in the sprint | — |
| Q35 | Audit targets | Stable short aliases (morph map), never class names | §13 |
| Q36 | Guardian billing access | **Any actively linked guardian** may view a student's invoice and start a demo payment, **regardless of `billed_guardian_id`**. `billed_guardian_id` is only the immutable addressee snapshot. A revoked link grants nothing | §11.2, §11.5, §11.6, §15 |
| Q37 | Auditing primary changes | Primary-guardian changes are recorded **within `guardian_link.created`** (initial primary) **or `guardian_link.revoked`** (promotion after revocation), with the **old and new primary guardian-link IDs**. No new event name and no personal data | §9.2, §9.4, §13 |

- **Related contract change in v0.3:** §11.7 demo completion is atomic. It rejects already-paid invoices (`409`) and guarantees at most one `succeeded` payment and one commission accrual per invoice. This is worded as **demo payment completion**, because the contract has no settlement concept (C2).
- **Still pending:**
  - **Audit IP retention period:** pending a pre-launch privacy review.
  - **Real-money guardian billing policy:** Q36 applies to the **demo** flow only. Who may pay when real money is collected (C2) needs a **separate pre-launch review** and is not decided here.
  - **`GUARDIAN_INVITE_HMAC_KEY`:** to be added to local ignored `.env` files and as an empty placeholder in `.env.example` in the implementation task. It is never committed, printed or logged.
- **Individual approver names:** recorded as supplied: Ranithu Shenal, 2026-10-28. No other approver names have been supplied for D-015, so none were added.

## R1 design notes — not yet approved

**Nothing in this section is approved.** These are proposals and cautions for the upcoming R1 task (reconciled `docs/SPRINT_SCHEMA.md` and migrations). They are not decisions and must not be built until R1 approves them. Items marked **R1 proposal** go beyond both the contract and the sprint draft. The MySQL facts below were checked against the MySQL 8.4 Reference Manual (CHECK constraints, FOREIGN KEY constraints, generated columns, CREATE INDEX, date and time types) on 2026-10-28.

**MySQL facts that shape the design (verified):**
- A `CHECK` constraint cannot reference columns in other tables, subqueries or stored functions. It **cannot** check `roles.name` through `user_roles.role_id`.
- `CHECK` constraints are prohibited on columns used in FK referential actions, and the reverse. A column in a `CHECK` must therefore have an FK with **no** `CASCADE`/`SET NULL`.
- The base column of a **stored** generated column cannot have an FK with `CASCADE`, `SET NULL` or `SET DEFAULT` (on update or delete). An FK cannot reference a **virtual** generated column.
- A generated column cannot use an `AUTO_INCREMENT` column. It may only reference generated columns defined earlier in the table.
- A `UNIQUE` index permits multiple `NULL`s. This is why the "active" pattern below works: an inactive row has `NULL` in the flag and never collides.
- `TIMESTAMP` only goes up to `2038-01-19 03:14:07` UTC and is converted through the session time zone. `DATETIME` and `DATE` are not converted.

**Foreign keys:** any FK whose column feeds a generated column or a `CHECK` would have to use `RESTRICT` (`restrictOnDelete()`, no cascade). The contract already forbids hard deletes of teacher assignments and guardian links.

**Generated columns and unique indexes.** In Laravel, write the generated columns with `->storedAs('…')` and declare them before the unique key.

| Table | Generated column (all `STORED`) | Unique index | Source |
|---|---|---|---|
| `users` | — | `uuid`; `phone`; `email` (nullable) | F1 |
| `roles` | — | `name` | D-011 |
| `user_roles` | `scope_key BIGINT UNSIGNED AS (IFNULL(institute_id, 0))` — `0` never collides because ids start at 1 | `(user_id, role_id, scope_key)` | Sprint draft, compatible |
| `institute_staff` | `active_flag TINYINT AS (IF(status = 'active', 1, NULL))` | `(institute_id, user_id, active_flag)` — **R1 proposal**; the contract does not state it | Contract §3 |
| `teacher_institute` | `active_flag TINYINT AS (IF(status = 'active' AND left_on IS NULL, 1, NULL))` | `uuid`; `(teacher_id, institute_id, active_flag)` | Contract §6.2 (`409`) |
| `student_guardians` | `active_flag TINYINT AS (IF(revoked_at IS NULL, 1, NULL))` | `(student_id, guardian_id, active_flag)` | Contract §2.3, C6 |
| `guardian_invitations` | `pending_flag TINYINT AS (IF(status = 'pending', 1, NULL))` | `uuid`; `code_hash`; `(student_id, guardian_phone, pending_flag)` | Contract §9.1 (`409`) |
| `class_enrollments` | `active_flag TINYINT AS (IF(status = 'active', 1, NULL))` | `uuid`; `(class_id, student_id, active_flag)` | Contract §8.3 |
| `class_sessions` | — | `uuid`; `(class_id, session_date, start_time)` | Contract §10.1 |
| `class_fee_versions` | — | `(class_id, effective_from)` | Contract §7.4 |
| `attendances` | — | `(session_id, enrollment_id)` | Contract §2.3 |
| `invoices` | `open_flag TINYINT AS (IF(status <> 'cancelled', 1, NULL))` | `uuid`; `(enrollment_id, billing_period, open_flag)` | Contract §11.2 |
| `payments` | `pending_flag TINYINT AS (IF(status = 'pending', 1, NULL))` | `uuid`; `idempotency_key`; `(invoice_id, pending_flag)` | Contract §11.6 |
| `platform_commissions` | — | `uuid`; `payment_id` | Contract §2.3 |
| `boost_purchases` | — | `uuid`; `idempotency_key` | Contract §12.2 |
| `otp_challenges` | — | `uuid` | Contract §2.3 |

Notes on the table:
- `teacher_institute` uses both columns in its flag. Add a `CHECK ((status = 'active' AND left_on IS NULL) OR (status = 'former' AND left_on IS NOT NULL))` so the two can never disagree.
- `guardian_invitations` and `payments`: a row that is past its expiry but still `pending` keeps its flag. The service must mark it `expired` or `cancelled`, inside the same transaction and under a row lock, before it inserts the replacement. Otherwise the replacement gets a false `409`.
- Unique indexes back up the transactions and row locks in contract §1.6. They do not replace them.

**Role/scope consistency (`user_roles`).** `super_admin` must have `institute_id IS NULL`, and institute roles must have it set. A `CHECK` cannot see the role name, so R1 must choose an explicit strategy:
- **Proposed minimum:** one backend service, `RoleAssignmentService`, is the only code that writes `user_roles`. It rejects role/scope mismatches and runs inside the institute-creation transaction to grant the owner `institute_admin`. Feature tests cover rejecting a scoped `super_admin` and an unscoped institute role. Seeders call the service.
- **R1 proposal (optional, database-level backstop):**
  - Add `roles.scope` (`global|institute`) with `UNIQUE (id, scope)`, and `user_roles.role_scope` with a composite FK `(role_id, role_scope) → roles(id, scope)` using `RESTRICT`.
  - Add `CHECK ((role_scope = 'global' AND institute_id IS NULL) OR (role_scope = 'institute' AND institute_id IS NOT NULL))`.
  - These add columns that neither the contract nor the draft has. Build them only if R1 approves them, and only after a migration test on MySQL 8.4.13 confirms it.

**Historical rows must not keep granting access:**
- Every authorization check goes through one shared "active" definition per table (a model scope used by Policies). Never check row existence alone.
  - `teacher_institute`: `status = 'active' AND left_on IS NULL`.
  - `institute_staff`: `status = 'active'`.
  - `student_guardians`: `revoked_at IS NULL`.
- Institute staff access needs **both** the `institute_staff` role in `user_roles` **and** an active `institute_staff` row (contract §3). Ending a staff row must remove the matching `user_roles` row in the same transaction. Otherwise a later new staff row would silently bring back old access.
- Tests needed for each table: an ended assignment, a former staff row and a revoked link each return `403`/`404` on the next request, and a later new row grants access again.
- **Open for R1:** whether removing a role hard-deletes the `user_roles` row (history kept in `audit_logs`) or adds an `active_flag` to it. The contract has no role-removal or staff-management endpoint in the sprint, so staff rows come from seeders only. Do not invent one.

**Dates and times.** Do not default to `TIMESTAMP` for every date or time column.
- **`DATE`** (calendar dates in `Asia/Colombo`, contract §1.5; never time-zone converted):
  - `date_of_birth`, `joined_on`, `left_on`, `enrolled_on`, `withdrawn_on`
  - `session_date`, `effective_from`, `effective_to`, `issued_on`, `due_on`
- **`TIME`** (`Asia/Colombo` wall-clock time): `start_time`, `end_time`.
- **`billing_period`** (`"YYYY-MM"` in the API): **open for R1.** Either `CHAR(7)` with a `CHECK` on the format, or a `DATE` holding the first day of the month.
- **Instants** (UTC; D-005): `created_at`, `updated_at`, `deleted_at`, `expires_at`, `consumed_at`, `last_sent_at`, `linked_at`, `revoked_at`, `accepted_at`, `paid_at`.
  - **R1 proposal:** use `DATETIME` (`datetimes()`, `softDeletesDatetime()`, `dateTime()`) rather than Laravel's default `TIMESTAMP`. This avoids the 2038 limit and any dependence on the session time zone.
  - The app writes UTC, because the app time zone is UTC.

**Migration dependencies.** Each table depends only on earlier ones:

| Order | Tables | Depends on |
|---|---|---|
| 0 | `users` (edited default migration), `password_reset_tokens`, `sessions`, `cache`, `jobs` | — |
| 1 | `subjects`, `grades`, `streams`, `exam_targets`, `mediums`, `syllabi`, `roles` | — |
| 2 | `otp_challenges`, `audit_logs` | `users` |
| 3 | `teacher_profiles`, `guardian_profiles` | `users` |
| 4 | `institutes` | `users` (owner) |
| 5 | `user_roles`, `institute_staff`, `teacher_institute` | `users`, `roles`, `institutes`, `teacher_profiles` |
| 6 | `student_profiles` → `guardian_invitations` → `student_guardians` | `users`, `institutes`, `guardian_profiles` |
| 7 | `classes` → `class_fee_versions`, `class_enrollments`, `class_sessions` | `institutes`, `teacher_profiles`, lookups, `student_profiles` |
| 8 | `attendances` | `class_sessions`, `class_enrollments`, `users` |
| 9 | `commission_rules` → `invoices` → `invoice_items`, `payments` → `payment_transactions`, `platform_commissions` | `institutes`, `classes`, `class_enrollments`, `student_profiles`, `guardian_profiles`, `users` |
| 10 | `boost_products` → `boost_purchases` | `classes`, `institutes`, `users` |

- **Not decided here (left for R1 to confirm against the contract, not invent):**
  - The `institute_staff` status values other than `active`.
  - Whether a class-scoped `commission_rules` row also stores `institute_id`.
  - Whether a cancelled session should free its `(class_id, session_date, start_time)` slot. Under the contract as written it does not.

---

## Pending founder decisions

- D-012, D-013: individual approver names (approval by the TuitionHub founders is recorded).
- D-013: the open schema questions Q19 (OTP details) and Q21 (lookup seed lists). VP-1 and Q20 are resolved (D-014).
- D-015 (approved): the audit IP retention period (pre-launch privacy review) and the real-money guardian billing policy (separate pre-launch review).
- "R1 design notes — not yet approved", for groups after G1–G3. For G1–G3 they are superseded by `docs/SPRINT_SCHEMA.md` (D-013).

C8 was approved as D-011, and its open questions were decided on 2026-10-28.

## Not yet done from the approved Day 1 plan (status as of 2026-10-27)

- Step 3: `install:api` (Sanctum), `statefulApi()`, the `/api/v1` route group, the CORS config, and the `config/otp.php` and `config/demo.php` files.
- Step 4: migration groups 0–2 (framework tables, lookups, `otp_challenges`, `audit_logs`). No migrations have been written or run.
- Step 5: OTP and auth tests.

Each of these needs its own approval before work starts.
