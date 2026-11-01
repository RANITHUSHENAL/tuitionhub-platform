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

## D-016 — M2 R2 migrations verified — **Verified by automated tests 2026-10-28**

- **Scope:**
  - The four R2 migrations from `docs/SPRINT_SCHEMA.md` Part 2 (D-015): `2026_10_29_000010_create_audit_logs_table`, `…000011_create_student_profiles_table`, `…000012_create_guardian_invitations_table`, `…000013_create_student_guardians_table`.
  - Database-level tests in `api/tests/Feature/Schema/`.
  - No M1 migration was changed (append-only, D-014).
- **How it was run:**
  - Only `php artisan test` from `api/`, under the D-010 guard. `RefreshDatabase` ran `migrate:fresh` on **`tuitionhub_test` only**.
  - `migrate`, `migrate:fresh`, `db:seed` and `db:wipe` were never run directly. The `tuitionhub` development database was **not** touched, and no seeder was run.
- **MySQL version:** **8.4.x**, asserted by `SchemaStructureTest` (`SELECT VERSION()` starts with `8.4.`). The patch version was not re-checked; D-003 recorded 8.4.13.
- **VP-37 (R2 equivalent of VP-1): passed.** All 4 tables, 3 stored generated columns, 10 CHECKs, 19 approved named indexes and 17 FKs were created with **no MySQL error**.
  - All 17 FKs use **explicit `ON DELETE RESTRICT`** with `ON UPDATE NO ACTION`.
  - That includes FK columns that also appear in CHECKs: `guardian_invitations.accepted_by_user_id` and `revoked_by_user_id`, and `student_guardians.revoked_by_user_id`.
  - The two composite FKs were created as approved:
    - `guardian_invitations_student_institute_fk (student_id, institute_id) → student_profiles(id, managing_institute_id)`
    - `student_guardians_invitation_student_fk (invitation_id, student_id) → guardian_invitations(id, student_id)`
- **Additional named index (founder-approved, M2 Q3):** `guardian_invitations_student_institute_index (student_id, institute_id)` is the child index for the first composite FK, so MySQL does not create an unnamed one. It is recorded in `docs/SPRINT_SCHEMA.md` G4.2.
- **Results:** `php artisan test` reports **88 passed (514 assertions)**, with 0 failed and 0 skipped. That is the 46 earlier tests plus **42 new** ones:

  | Test file | New tests |
  |---|---|
  | `R2SchemaStructureTest` | 8 |
  | `AuditLogConstraintsTest` | 5 |
  | `StudentProfileConstraintsTest` | 3 |
  | `GuardianInvitationConstraintsTest` | 12 |
  | `StudentGuardianConstraintsTest` | 14 |

  All 46 earlier tests still pass.
- **Existing test change (M2 Q1):** in `SchemaStructureTest`, only the two whole-database assertions (all CHECK names; all FK names and rules) were limited to the G1–G3 tables. Their expected G1–G3 lists are unchanged. R2 is checked by `R2SchemaStructureTest`.
- **MySQL error codes confirmed:**

  | Code | Meaning |
  |---|---|
  | 1062 | duplicate key |
  | 3819 | CHECK violated |
  | 1452 | FK child has no parent |
  | 1451 | FK parent still referenced |
  | 1364 | required column missing |
  | 3140 | invalid JSON |
  | 3105 | write to a generated column |

  Every test asserts both the error code and the constraint or column name.
- **VP cases implemented (database level):**
  - VP-27, VP-28 (DB part), VP-29 (DB part), VP-31 (DB part), VP-32, VP-33, VP-37, VP-38, VP-43, VP-44
  - VP-50a–f, VP-51a–f, VP-52, VP-53a–b
- **VP cases deferred** to the future services and endpoints (not counted as passing):
  - VP-28 (service `409` and lazy expiry under lock), VP-29 (generic `422` on a second redemption), VP-30, VP-31 (API `409`), VP-34, VP-35, VP-36, VP-39, VP-40, VP-41, VP-42, VP-45, VP-46, VP-47, VP-48, VP-49.
  - From Part 1: VP-9, VP-17–VP-22, VP-26, and the VP-24 canonical-spelling check.
- **AuditLogger input rules** (documented in `docs/SPRINT_SCHEMA.md` R2.0 rule 9; not implemented):
  - `user_agent` is limited to 512 characters.
  - `ip_address` is **never truncated**. It is obtained through correctly configured trusted-proxy request handling, validated as IPv4 or IPv6 with `filter_var`, and stored intact when valid.
    - The column is `VARCHAR(45)` (`R2SchemaStructureTest`), which holds any valid textual IPv4 or IPv6 address in full, so a valid IP never needs truncating.
    - The validation and storage behaviour itself is a rule for the future AuditLogger. It is not yet implemented or tested.
  - **Handling of an invalid or missing IP remains a proposed service decision.** It is not approved, and must be decided before the AuditLogger is built. An invalid IP is never silently substituted.
- **Student-transfer limitation (verified by VP-50e):** `guardian_invitations_student_institute_fk` blocks any change to a student's `managing_institute_id` while an invitation references the student. There is no transfer flow in the sprint. A future transfer feature needs its own approved design and must not weaken this FK.
- **Rule:** the M2 migrations are **append-only** once committed, like M1 (D-014).
- **Pending:**
  - Applying the M2 migrations to `tuitionhub` needs its own approval, with the same `db:show` and `migrate --pretend` preflight as M1.
  - The invalid or missing IP handling decision for the AuditLogger. *(Decided in D-017.)*

## D-017 — S1 application foundations verified — **Verified by automated tests 2026-10-29**

- **Scope:** Eloquent models, public UUIDs, UTC date handling, the morph map, `AuditLogger`, `RoleAssignmentService`, `InstituteService`, `StaffService`, `TeacherAssignmentService` and `TenantAccess`.
  - No migrations, packages, routes, endpoints or Sanctum.
  - The `tuitionhub` development database was not touched.
  - Tests ran only through `php artisan test` (D-010 guard; `tuitionhub_test`).
- **Results:** **151 passed (795 assertions)**, with 0 failed and 0 skipped. That is the 88 earlier tests plus **63 new** ones:

  | Test file | New tests |
  |---|---|
  | `DistrictTest` | 4 |
  | `PhoneMaskerTest` | 2 |
  | `PublicUuidTest` | 5 |
  | `DateSerializationTest` | 3 |
  | `MorphMapTest` | 2 |
  | `GeneratedColumnGuardTest` | 2 |
  | `AuditLoggerTest` | 11 |
  | `AuditLoggerTransactionGuardTest` | 1 |
  | `AuditLogImmutabilityTest` | 1 |
  | `RoleAssignmentServiceTest` | 7 |
  | `InstituteServiceTest` | 5 |
  | `StaffServiceTest` | 7 |
  | `TeacherAssignmentServiceTest` | 3 |
  | `TenantAccessTest` | 8 |
  | `ArchitectureTest` | 2 |

  All 88 earlier tests still pass.
- **VP cases now covered:**
  - VP-9, VP-17, VP-18, VP-19, VP-20, VP-21, VP-22, VP-26, VP-36, VP-40.
  - New: VP-54 to VP-59, added to `docs/SPRINT_SCHEMA.md` Part 2.
  - The VP-24 canonical-spelling part is complete via VP-59.
  - VP-21's teacher part uses a fixture assignment until `TeacherAssignmentService::assign` exists.
- **Decisions (approved for S1):**
  1. **Public UUIDs:** a custom `HasPublicUuid` trait.
     - It generates a random RFC 4122 v4 UUID with `Str::uuid()` in the separate `uuid` column, through Laravel's unique-ID insert hook. That hook runs even for `saveQuietly()`.
     - It always overwrites any supplied value; updates throw; the route key is `uuid`; malformed values resolve to nothing without querying.
     - `id` stays the `BIGINT` auto-increment key.
     - Laravel's `HasUuids` (UUIDv7, applied to the primary key) and `HasVersion4Uuids` (timestamp-first COMB) were checked in the 13.38.0 source and rejected.
  2. **UTC instants:** the `SerializesUtcDates` trait.
     - Instants serialize as `YYYY-MM-DDTHH:MM:SSZ`, which closes the D-014 open item on microseconds.
     - **PHP/Laravel time-zone correction (found during S1):** when Eloquent writes a `DateTime` that is not in UTC (for example `14:00` in Asia/Colombo), it formats the local wall-clock time and stores it as if it were UTC (`14:00Z` instead of `08:30Z`). `SerializesUtcDates::setAttribute()` now converts datetime-cast values to UTC before they are stored. VP-55 tests this.
     - Calendar dates use `immutable_date:Y-m-d` casts and are never converted.
  3. **Strict mode:** `Model::shouldBeStrict(! app()->isProduction())`.
  4. **Morph map:** enforced with stable aliases (`AppServiceProvider`), so an unmapped audit target throws.
  5. **AuditLogger:**
     - It is insert-only: model hooks plus `AuditLogBuilder` refuse every Eloquent update or delete path. Raw `DB::table` writes remain a code-review rule (Q33).
     - It must run inside the business transaction: outside one, it throws before any query.
     - **A failed required audit insert propagates and rolls back the business operation.** Neither `AuditLogger` nor any caller catches it. Tested by forcing a database failure of the audit `INSERT` itself, which rolls back the business row.
     - A failing IP warning (the log channel throws) is swallowed and never fails the business transaction.
     - The denylist runs **before** the per-action allowlist and throws a `LogicException`. `institute.created` allows only `name`, `district`, `city` and `status`.
     - The user agent is limited to 512 characters.
  6. **Audit IP handling** (resolves D-016's pending item):
     - No request → NULL, no warning.
     - Missing or invalid IP from a request → NULL plus `Log::warning` without the raw value.
     - The business transaction never fails, and valid IPs are stored intact.
  7. **`RoleAssignmentService`** is the only writer of `user_roles`, enforced by `ArchitectureTest` (a source scan with comments stripped).
     - **Architecture rule — no quiet model writes:** `saveQuietly()`, `updateQuietly()` and `withoutEvents()` are not allowed anywhere in `app/`, enforced by `ArchitectureTest`. They skip the model events that carry the UUID-immutability and generated-column guards. Audit logs are protected even without events, because `AuditLogBuilder` refuses every Eloquent update or delete path.
     - `role_scope` always comes from `roles`, and role names must match exactly.
     - A duplicate grant throws `ROLE_ALREADY_GRANTED`; only the `user_roles_user_role_scope_unique` violation (1062) is mapped to it.
     - Revoking the owner's `institute_admin` throws `OWNER_ADMIN_REVOCATION_FORBIDDEN` (D-011).
  8. **Lock order** shared by all services: `users` → `institutes` → `institute_staff` / `teacher_institute` → `user_roles`.
     - Only existing rows are locked; inserts rely on the unique keys.
  9. **`StaffService::end`** revokes the `institute_staff` role first. A missing role therefore throws `ROLE_NOT_GRANTED` with no partial change. A separate `institute_admin` role is untouched.
  10. **`TenantAccess`** re-queries on every call, with no caching.
      - Staff access needs both the role and an active staff row.
      - Every institute-scoped check takes the institute explicitly.
      - Suspended users are denied everywhere, re-read from the database. Institute suspension is left to the endpoint policies.
  11. **District canonicalization** accepts `Moneragala` as an alias for `Monaragala`.
- **Not in S1:** `TeacherAssignmentService::assign`; the invitation, link and primary-guardian services (S2); HTTP middleware that fills `AuditContext` from requests; routes and API Resources (Day 1 Step 3, Sanctum, still pending); trusted-proxy configuration (needs a deployment target).
- **Pending:** applying M2 to `tuitionhub` (D-016).

## D-018 — Dependency security: Symfony Mime CVE-2026-45070 — **Patched and verified 2026-10-29**

- **Decision:** patch `symfony/mime` **v8.1.7 → v8.1.10** with an isolated `composer update symfony/mime` (no `--with-dependencies`).
  - Only `composer.lock` changed; `composer.json` is unchanged.
  - A before/after comparison of all 110 locked packages shows **`symfony/mime` is the only version change**.
- **Advisory:**
  - **CVE-2026-45070**, "Email Header Injection via Non-Token Characters in Mime Parameter Names", severity **medium**.
  - Packagist advisory `PKSA-wtxr-p26d-nn42`; sources GHSA-vqc8-7275-q272 and FriendsOfPHP `symfony/mime/CVE-2026-45070.yaml`.
  - Composer reported it on 2026-10-28T18:08:54Z.
- **Present before Sanctum:** `composer audit --locked` against the committed HEAD `composer.lock` (`a3e42cc`, before any Sanctum change) reported the same advisory. The A1 Sanctum install neither introduced nor changed it.
  - The uncommitted Sanctum install was reverted (`git restore` of `composer.json` and `composer.lock`) before patching. Composer then removed `laravel/sanctum` from `vendor/` to match the lock.
  - A1 restarts from a clean, patched baseline.
- **Exposure:** the sprint has **no email flow** (no mail sending; SMS and mail are out of scope). The vulnerable path builds MIME header parameters from user input. The package is in the tree through `laravel/framework` and `symfony/mailer`.
- **Conflicting public metadata, resolved by checking the source code:**
  - **The two sources disagree:**
    - symfony.com/cve-2026-45070 and GHSA (published 2026-05-20, updated 2026-05-27) list `>=8.0.0,<8.0.12` as affected and **8.0.12** as fixed.
    - Packagist / FriendsOfPHP (2026-10-28) list `>=8.0.0,<8.1.0` and `>=8.1.0,<8.1.10` as affected, and **8.1.10** (plus 5.4.55, 6.4.49, 7.4.22) as fixed.
  - **What the code shows:** the fix makes `Symfony\Component\Mime\Header\ParameterizedHeader::setParameters()` reject parameter **names** outside `TOKEN_REGEX`, throwing `RfcComplianceException`.
    - **Installed v8.1.7:** no name validation; only values are checked.
    - **v8.0.12:** no name validation either, despite being labelled "fixed".
    - **v8.1.10:** has the name validation.
  - The Packagist data matches the code; the May public metadata is outdated or incorrect.
- **Verified:**
  - `composer audit` → "No security vulnerability advisories found" (exit 0).
  - `composer audit --locked` → the same (exit 0).
  - `php artisan test` → **151 passed (795 assertions)**, all tests unchanged.
- **Policy:** **every dependency change must pass `composer audit` (installed) and `composer audit --locked` before it is committed.**
  - An advisory stops the work until it is resolved or explicitly accepted and recorded here.
  - Version changes are compared package by package, and any unexpected change stops the work.
- **Note:** the A1 plan referred to its future entry as "D-018". A1's entry will now be **D-019**.

## D-019 — A1 SPA authentication and OTP implementation verified — **Verified by automated tests 2026-10-29**

- **Scope:** Sanctum SPA cookie/session authentication and phone OTP login: `POST /api/v1/auth/otp/request`, `POST /api/v1/auth/otp/verify`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/session`. Contract updated to **v0.4** (§1.1–§1.4, §4.1–§4.4, §15, §16). **No migrations** were created or changed; the existing `otp_challenges`, `users` and `sessions` tables are used as built in M1.
- **Environment (verified):** Laravel Framework **13.38.0**, PHP 8.5.12, MySQL 8.4.13, **`laravel/sanctum` v4.3.3** (`^4`).
- **Dependency change (D-018 policy):**
  - `composer require "laravel/sanctum:^4"` added **only** `laravel/sanctum v4.3.3` to `composer.lock` (compared against `870df75`); no other package changed.
  - `composer audit` and `composer audit --locked`: "No security vulnerability advisories found" (both exit 0).
  - Only the Sanctum config was published (`sanctum-config` tag) plus the framework `cors` config. **`install:api` was not run**; there is no `personal_access_tokens` migration and no `HasApiTokens` trait.
- **Verified facts (installed source read, then tested):**
  - **Bearer tokens disabled:** `Sanctum::getAccessTokenFromRequestUsing(fn () => null)`. Sanctum's guard then never looks up a token; a bearer-only request is `401` and no `personal_access_tokens` query is issued (tested with the query log).
  - **Stateful detection:** `statefulApi()` adds `EnsureFrontendRequestsAreStateful`, which starts the session and runs CSRF validation only when `Origin`/`Referer` matches `sanctum.stateful`, and marks the request with the `sanctum` attribute. `RequireStatefulFrontend` uses that attribute. Non-stateful clients get **`403` from verify** (no session can be created outside CSRF protection) and **`401` from logout and session** (`auth:sanctum` runs first, and no session is started for them, so they are never authenticated). All three are tested.
  - **CSRF in tests:** Laravel 13's `PreventRequestForgery` skips validation when running unit tests. Sanctum reads its CSRF middleware class from `sanctum.middleware.validate_csrf_token` on every request, so `CsrfEnforcementTest` swaps in `Tests\Support\EnforcingPreventRequestForgery` (returns `false` from `runningUnitTests()`). With it, a missing token, a wrong token and a `Sec-Fetch-Site: same-site` request are all `419` with no challenge created; the token from `/sanctum/csrf-cookie` passes; the full login flow works. A control test proves the default middleware skips CSRF in tests, so ordinary tests are not mistaken for CSRF coverage. The config uses `PreventRequestForgery` (Sanctum's published default `ValidateCsrfToken` is a deprecated subclass with identical behaviour).
  - **CORS:** with one allowed origin, `fruitcake/php-cors` always answers with that origin (`http://localhost:5173`) whatever the requesting origin, so a browser rejects any other origin (`127.0.0.1:5173`, other ports, other hosts). Credentials are allowed only for it.
- **OTP design as implemented:**
  - **Hashing:** `code_hash` = HMAC-SHA256(`OTP_HMAC_KEY`, `"{challenge uuid}|{phone}|{code}"`), compared with `hash_equals`; `request_ip_hash` = HMAC of the IP under the same key. The key is read lazily: missing or shorter than 32 bytes → `503` for OTP request/verify only (other endpoints unaffected). Tests use a throwaway random key per test; no key is committed (`.env.example` has an empty `OTP_HMAC_KEY=`).
  - **Drivers:** `log` (local only; writes `DEV MODE — no SMS is sent. OTP for <masked phone>: <code>` to the dedicated `otp` channel, `storage/logs/otp.log`, which is not in the default stack); `fake` (tests only, `phpunit.xml` forces `OTP_DRIVER=fake`); any other value → `UnavailableOtpDriver` (`503`). `log` and `fake` throw in production before any challenge is stored.
  - **Request:** 6 digits from `random_int`; per-phone serialisation with a cache lock; in one transaction the 60 s cooldown is checked, older pending challenges for the phone/purpose are expired, and the new challenge is stored. Rate limits (named limiter `otp-request`): 3 per phone per 15 min, 10 per phone per 24 h, 10 per IP per hour; phone keys are SHA-256 hashes of the phone. The endpoint never reads `users`, so responses and limits are identical for registered and unregistered phones.
  - **Verify:** the challenge row is locked; a wrong code is counted by the guarded update from Q19 (`attempts = attempts + 1 … WHERE consumed_at IS NULL AND attempts < max_attempts`; 0 rows → exhausted). The transaction **returns** an outcome and the exception is thrown **after commit**, so the increment is persisted before the `422`/`429` is sent. Consumption is a guarded update (unconsumed, unexpired, under the limit) → exactly once. A user is created on first verify (`name` null, no roles); a duplicate-phone race is resolved through `users_phone_unique`: on that violation the user is re-read with a **locking read** (`lockForUpdate()`). Correction made before commit: a plain re-read could miss the competing row, because under MySQL REPEATABLE READ an ordinary `SELECT` may reuse the transaction's earlier snapshot, while a locking read sees the latest committed row. A suspended user's challenge is consumed, the response is `403`, and no login happens. On success: `Auth::guard('web')->login()` then `session()->regenerate()`. Limit: 10 verifies per IP per 10 min.
  - **Responses:** `Cache-Control: no-store, private` on every `/api/*` response (global middleware, so it also covers framework errors and unmatched routes); `404` body is always `{"message":"Not found."}`; `DomainRuleException` maps to `409` + `reason`, `403` `not_eligible` + `reason`, or `422` `errors`.
- **Test harness notes (no effect on production):** the SPA test helper (`tests/Feature/Auth/Concerns/InteractsWithSpa.php`) runs on the `database` session driver (as `.env.example`) and, before every request, forgets resolved auth guards and the session store. Laravel's test client reuses one application, and without this a cached guard user or cached session attributes (`Store::loadSession()` merges into them) made logout and session-fixation tests pass or fail for the wrong reason. Headers are passed per request so an SPA `Origin` never leaks into a "non-stateful" request.
- **Tests:** 54 new (Feature/Auth: `CsrfEnforcementTest` 7, `OtpRequestTest` 14, `OtpVerifyTest` 12, `SessionEndpointTest` 6, `BearerAndCorsTest` 3, `ErrorMappingTest` 3; Unit/Otp: `OtpCodeHasherTest` 4, `OtpDriversTest` 5). Final run after the locking-read correction: `php artisan test` → **205 passed (1216 assertions)** (151 existing, unchanged, + 54). `composer audit` and `composer audit --locked` clean. The attempt-persistence test reads `otp_challenges.attempts` straight from the database after each `422`.
- **Q19 (D-013):** resolved by this entry except **the purge of expired challenges, which is explicitly deferred** (not decided, not built). Each resolved part traces to an approved source and closes no unapproved requirement:
  - `code_hash` method: HMAC-SHA256 under `OTP_HMAC_KEY` over `uuid|phone|code` (approved A1 task specification).
  - `max_attempts`: set by the service from `config('otp.max_attempts')` = 5, no DB default (the contract's proposed default, finalised in v0.4).
  - `last_sent_at`: always set; already `NOT NULL` in the M1 migration.
  - Resend: a new challenge, with older pending ones expired (approved A1 decision).
- **Not tested / pending (before any real-SMS or pre-launch use):**
  - **Cross-process concurrency testing is not yet done.** The per-phone cache lock, the guarded attempt/consumption updates and the first-login locking read are covered by single-process tests only. The cache lock relies on the cache store (`database` in `.env.example`, `array` in tests).
  - **Real SMS must not hold an open database transaction.** `send()` currently runs inside the request transaction (acceptable for the in-process `log`/`fake` drivers). A real provider must send after commit, or outside the transaction with compensation, so a slow network call never holds row locks. Pre-launch.
  - **The 10-second cache-lock lifetime** was sized for local drivers and must be reassessed for a real SMS provider's latency and timeouts.
  - **`otp.log` retention and rotation** are not configured (local development only).
  - **Trusted-proxy configuration** is pending: per-IP limits and `request_ip_hash` use `$request->ip()`, which behind a proxy or load balancer would be the proxy's address until trusted proxies are configured.
  - **Real-browser cookie and CORS testing** is pending: the React SPA is not yet connected, so CORS and cookies are verified through Laravel's test client only.
  - The `log` driver has not been run against the local `tuitionhub` database; nothing in A1 touched it.

## D-020 — E1a shared HTTP infrastructure, `PATCH /me`, institutes and lookups — **Verified by automated tests 2026-10-29**

- **Basis:** the founders approved FD-1 to FD-10 of the E1 plan as proposed (2026-10-29), and then specified the final 15-subject list and the targeted general rate limit before commit. Contract updated to **v0.5** (§16). **No migrations, no new packages**; the seeder ran only in the test database.
- **Founder decisions implemented:**
  - **FD-1:** `PATCH /api/v1/me` (name only; later edits allowed; response = `GET /auth/session` shape).
  - **FD-2:** new audit event `user.profile_updated` **without the name**: `old {name_was_set}`, `new {user_uuid, field, onboarding_completed}`.
  - **FD-3:** a suspended institute stays readable by its admins and staff; non-super-admin writes are refused.
  - **FD-4:** teacher-only callers get `403` on `GET /institutes/{uuid}` (and `PATCH`); callers with no relationship get `404`.
  - **FD-5:** unknown request fields are rejected with `422` on write endpoints (`RejectsUnknownFields`).
  - **FD-7 (Q21):** `LookupSeeder` values (see contract §4.6). **15 subjects**, as specified by the founders: `MATH` Mathematics, `COMBINED_MATH` Combined Mathematics, `SCI` Science, `PHY` Physics, `CHEM` Chemistry, `BIO` Biology, `ICT` Information and Communication Technology, `ENG` English, `SIN` Sinhala, `TAM` Tamil, `HIS` History, `GEO` Geography, `ACC` Accounting, `BUS` Business Studies, `ECON` Economics. Grades `G6`–`G11`, `AL1`, `AL2`; streams `SCI_MATH`, `SCI_BIO`, `COMMERCE`, `ARTS`, `TECH`; exam targets `OL_2026`–`OL_2028`, `AL_2026`–`AL_2028`; mediums `sin`, `tam`, `eng`; syllabi `local`, `edexcel`, `cambridge`. The contract's §7/§7.1 class examples now use `COMBINED_MATH`.
  - **FD-8:** public `GET /api/v1/lookups`, with the 25 canonical districts.
  - **FD-9:** reason code `institute_suspended` (`403`).
  - **FD-10:** name length 2–100; `contact_phone` uses the §1.5 mobile format. (`joined_on` and DOB rules apply to E1b.)
- **Shared HTTP infrastructure:**
  - **`BindAuditContext`** (route middleware after `auth:sanctum`, `RequireStatefulFrontend` and `EnsureActiveUser`): binds actor, a validated IP (invalid → `null`) and a user agent bounded to 512 characters for the request only, and removes the binding in `finally`, so the next request or a system write falls back to the system context. Services that audit are method-injected so they are built after the binding. Tested: two consecutive requests by different users record their own actors; a system write after a request has no actor, IP or user agent; an invalid `REMOTE_ADDR` is stored as `null`.
  - **404 rendering:** verified in Laravel 13 source that `Response::denyAsNotFound()` becomes a plain `HttpException(404)` (not `NotFoundHttpException`); the renderer now covers every 404 `HttpExceptionInterface` under `api/*`, so policy denials, missing models, malformed UUIDs and unmatched routes all return exactly `{"message":"Not found."}`. A malformed UUID is refused by `HasPublicUuid::resolveRouteBinding()` before any query (tested with the query log).
  - **`InstitutePolicy`:** order is no relationship → `404`; related but not allowed → `403`; allowed but institute suspended (non-super-admin write) → `403 institute_suspended`. FormRequests call the policy in `authorize()`, so authorization precedes validation. `InstituteService::update()` locks the institute row and calls the same abilities again with `Gate::forUser()` (tested: a role revoked or a suspension committed after the first check is still refused).
  - **Pagination:** `ValidatesPagination` (`page` ≥ 1, `per_page` 1–100, default 25) and `PaginatedResponse` (exactly `current_page`, `per_page`, `total`, `last_page`; no `links`). Every list ends its ordering with `id`.
  - **Text rules:** `App\Support\PersonName` + `App\Rules\PersonName` (NFC, space collapsing, Sinhala ZWJ conjuncts, control/bidi/format characters refused, 2–100 code points) and `App\Rules\PlainText` for institute free text.
  - **General rate limits (targeted, not `throttleApi()`):** named limiters in `AppServiceProvider`. `api-authenticated` = 60 requests/min keyed by authenticated user ID, applied only to the protected E1a group (`PATCH /me`, `GET /me/institutes`, `POST`/`GET`/`PATCH /institutes…`); all routes in the group share one budget per user. `api-public` = 60 requests/min keyed by client IP, applied only to `GET /lookups`. The `otp/*`, `logout` and `session` routes are untouched and keep their own limiters. Verified in the Laravel 13 kernel's middleware priority that `ThrottleRequests` runs after `AuthenticatesRequests`, so the authenticated limiter always sees the user. Tested: 60 calls pass, the 61st is `429` with `Retry-After` and `Cache-Control: no-store, private`; another user on the same IP is unaffected; the window resets after a minute; OTP requests are not counted. Per-IP keys depend on the pending trusted-proxy configuration (D-019).
- **Institute endpoints:** §5.1 create (existing `InstituteService::create`), §5.2 super-admin list (filters, literal search), §5.3 `/me/institutes` (`TenantAccess::myInstitutes()`/`accessLabels()`; reads of `user_roles` stay inside `TenantAccess`, so `ArchitectureTest`'s allowlist is unchanged), §5.4 show, §5.5 update. `institute.updated` records only `name`/`district`/`city` values and `*_changed` flags for description, contact phone, contact e-mail and registration number; `email`/`contact_email` were added to the audit denylist.
- **`LookupSeeder`:** checks that each table's only non-primary unique key is `code` (MySQL upsert matches on any unique key), writes only new or changed rows with explicit UTC timestamps, never overwrites `created_at`, never deletes, and a second run issues no writes (tested). Registered in `DatabaseSeeder`; **not run against `tuitionhub`** — that needs separate approval.
- **Tests:** 89 new; `php artisan test` → **294 passed (2609 assertions)** (205 existing unchanged + 89). Rollback on audit failure is covered for `PATCH /me`, institute create and institute update.
- **Not done / pending:** teacher and student endpoints (E1b); trusted proxies (D-019, still pending); real-browser SPA testing; Sinhala/Tamil lookup names and exam dates (founders to supply).

## D-021 — E1b teacher and student endpoints verified — **Verified by automated tests 2026-10-29**

- **Scope:** `POST /api/v1/me/teacher-profile` (§6.1), `POST /api/v1/institutes/{uuid}/teacher-assignments` (§6.2), `GET /api/v1/institutes/{uuid}/teachers` (§6.3), `POST` and `GET /api/v1/institutes/{uuid}/students` (§8.1, §8.2). Contract updated to **v0.6**. **No migrations, no new packages**; nothing ran against `tuitionhub`. §6.4 is **deferred** (needs the classes tables).
- **Basis:** the approved E1 plan and founder decisions FD-3, FD-4, FD-5, FD-6, FD-9 and FD-10 (D-020). No new policy decision was needed: every authorization outcome below is the one the plan and contract already state.
- **PersonName length:** the E1a rule now takes a maximum. Users and teachers keep **100** (`PersonName::MAX_LENGTH`); students' `full_name` uses **150** (`PersonName::STUDENT_MAX_LENGTH`), as the E1 plan proposed. Longer input is rejected, never truncated; the character rules are unchanged.
- **Teacher profile (§6.1):** `TeacherProfileService::upsert` locks the user row (users first), creates on the first call (`201`, `display_name` required) and updates afterwards (`200`, same UUID). Omitted fields are unchanged; explicit `null` clears `headline`/`bio`; `years_experience` (0–70) is not nullable. No `user_roles` row and **no audit event** (FD-6). An unchanged update writes nothing.
- **Teacher assignment (§6.2):** `InstitutePolicy::assignTeacher` (same rule as institute update: super_admin, or the institute's admin; staff/teacher-only `403`; unrelated `404`; suspended → `403 institute_suspended` except super_admin). `TeacherAssignmentService::assign` locks the institute row, authorizes again with `Gate::forUser()`, inserts, maps `teacher_institute_active_unique` to `409 already_active`, and records `teacher.assigned` (`teacher_uuid`, `institute_uuid`, `status`, `joined_on`) in the same transaction. It **never writes `user_roles`** (tested: role-row count unchanged, none for the teacher's user). `teacher_uuid` uses `bail|uuid|exists`, so a malformed value is refused before any query (tested with the query log).
- **Institute teachers (§6.3):** `InstitutePolicy::viewTeachers` = the §5.4 view rule (super_admin, admins, active staff; teacher-only `403` per FD-4; suspended institutes readable per FD-3). `status` filter; order `joined_on` desc, then `id` desc.
- **Students (§8.1, §8.2):** `InstitutePolicy::createStudent`/`viewStudents`: the institute's admins and active staff only; super_admin and teacher-only `403`; unrelated `404`; creation refused with `403 institute_suspended` while suspended, listing still allowed. `StudentService::create` locks the institute row, authorizes again, takes `managing_institute_id` from the route, `created_by_user_id` from the session, leaves `user_id` NULL, and records `student.created` (`student_uuid`, `institute_uuid`, `grade_code`, `medium_code`, `exam_target_code`) — the audit denylist refuses `full_name` and `date_of_birth`. The list query is scoped to the route institute before anything else; `active_guardian_count` uses `StudentGuardian::active()` (revoked links not counted; tested with directly inserted links).
- **Lookup codes are matched exactly:** the lookup tables use a case-insensitive collation, so Laravel's `exists` rule would accept `g11` for `G11`. New `App\Rules\LookupCode` and `App\Support\Lookups` compare with `COLLATE utf8mb4_bin`. Future DOBs are refused against today in `Asia/Colombo`.
- **Shared helper:** `App\Support\LikePattern` replaces E1a's private LIKE escaping in `InstituteController`; student search uses it too.
- **Routing:** all five routes are in the existing protected group (`auth:sanctum`, `RequireStatefulFrontend`, `EnsureActiveUser`, `BindAuditContext`, `throttle:api-authenticated`). OTP, logout and session routes are unchanged.
- **`joined_on` must not be in the future** (review finding, accepted before commit): an active assignment grants access as soon as it exists, so a future `joined_on` granted access before the assignment began, and `end()` could not close it until that date (`left_on` must not precede `joined_on`). The request (`before_or_equal:` today) and `TeacherAssignmentService::assign` (defence in depth for direct callers) both reject a date after today in `Asia/Colombo` with `422` on `joined_on`. Today and backdated dates are valid. Tested: today and yesterday `201`, tomorrow `422`; a direct service call with tomorrow is refused with nothing written; an assignment made today can be ended today.
- **Implementation choices accepted by the founders** (reported before commit):
  1. Lookup codes are matched **exactly** (`COLLATE utf8mb4_bin`): `g11` is not `G11`.
  2. `years_experience: null` is rejected (the column is not nullable); explicit `null` clears only `headline` and `bio`.
  3. `super_admin` may assign a teacher to a **suspended** institute (`assignTeacher` reuses the institute-update rule).
  4. `joined_on` accepts any real calendar date — **superseded** by the future-date rule above (today or earlier).
  5. `display_name` uses the PersonName rule (letters, marks, spaces, `.`, `'`, `’`, `-`; no digits or symbols).
  6. E1a's private LIKE escaping in `InstituteController` was replaced by the shared `App\Support\LikePattern`.
- **Service-safety review (all confirmed by tests, no fix needed):** `StudentService` locks the institute, re-authorizes, takes the institute from the route and the creator from the session, leaves `user_id` NULL, audits without name/DOB and rolls back on audit failure. `TeacherAssignmentService` locks the institute, re-authorizes, maps only `teacher_institute_active_unique` to `409`, writes no `user_roles` row, allows a new assignment after an ended one, and rolls back on audit failure. `TeacherProfileService` keeps the UUID, creates no role, writes no audit row, rejects `years_experience: null` and writes nothing on a no-op update. Policies: unrelated `404`, teacher-only `403`, `super_admin` `403` on student endpoints, suspended-institute rules as in FD-3/FD-9.
- **Tests:** 48 new; `php artisan test` → **342 passed** (294 existing unchanged + 48), about 3311 assertions. The assertion count can differ by one or two between runs because the query-log tests assert once per query, and the database session driver's garbage-collection lottery occasionally adds a query; the test count is stable. Covered: `401`, `403` (incl. `institute_suspended`), cross-tenant `404`, `409`, `422`, exact shapes, no internal IDs, audit actor/IP/user agent and context isolation across E1b writes, rollback on audit failure (assignment, student), and re-authorization under the row lock (revoked admin, ended staff row, suspension).
- **Not done / pending:** §6.4 (classes); trusted proxies (D-019); real-browser SPA testing.

## D-022 — H1 concurrency and logging hardening — **Verified by automated tests 2026-11-01**

- **Scope:** locking conventions, a bounded retry for lost concurrency races, fixes for two reachable deadlocks (C-1, C-2), an app-only lock wait timeout, and logging privacy. **No migrations, no new packages**; nothing ran against `tuitionhub`. Contract → **v0.6.1** (§1.4 `503` "service busy"; §4.1 send after commit). The E2 decision record planned as D-022 becomes **D-023**.
- **Verified facts (code and server read 2026-11-01):** MySQL 8.4.13 runs REPEATABLE READ, `innodb_lock_wait_timeout` 50 s (server default), `innodb_rollback_on_timeout` 0, deadlock detection on. `zend.exception_ignore_args` is Off in this PHP, so stack traces include argument values (strings up to 15 characters in `getTraceAsString()`). Laravel 13's `DB::transaction($callback, $attempts)` retries only an outermost transaction; a nested one throws `DeadlockException` (wrapping the original `QueryException`) for a deadlock **and for a lock wait timeout**, because its `ConcurrencyErrorDetector` matches both messages. `QueryException` messages include bound values unless the connection sets `mask_bindings_in_exception_messages`. Nothing in `bootstrap/app.php` stopped business-rule or OTP exceptions from being logged.
- **Locking conventions** (all services; full matrix in the E2 lock review):
  - **Pattern U (self-scoped):** lock the caller's own `users` row first (`UserProfileService`, `InstituteService::create`, `OtpService::verify` after the challenge). Rule: after taking an exclusive `users` lock, never wait on a row of an institute-scoped table that another transaction may hold.
  - **Pattern I (institute-scoped):** lock the `institutes` row first, then that institute's rows (`student_profiles` → `guardian_invitations` → `guardian_profiles` → `student_guardians`; or `institute_staff` / `teacher_institute` → `user_roles`), each **by primary key**. `users` rows are taken **only with shared locks**, last: InnoDB's foreign-key checks (`created_by_user_id`, `invited_by_user_id`, `linked_by_user_id`, `revoked_by_user_id`, `audit_logs.user_id`, …) take them implicitly, plus any explicit `FOR SHARE` re-check, in primary-key order.
  - **No range locking reads or range updates** in either pattern: sets of rows are found with a plain read taken after the first lock, then changed by primary key. Under REPEATABLE READ a range lock also locks index gaps, and two transactions holding the same gap deadlock on their inserts.
  - Why not "users first" everywhere: every committed Pattern I service already takes shared `users` locks after its institute lock, so users-first in a new path would deadlock against them (for example a staff member who is also a parent at the same institute). Making it consistent would mean rewriting every Pattern I service.
- **Defects found and their status:**
  - **C-1 (fixed):** `TeacherProfileService::upsert` held an exclusive `users` lock and then updated `teacher_profiles`, while `TeacherAssignmentService::assign` holds the institute and a shared lock on that profile row and waits for a shared lock on its actor. An admin assigning themselves while saving their profile could deadlock. Now: plain read of the profile id, lock the profile **by primary key**, then re-read the account status with a **current read** (`FOR SHARE`); a suspended account is still refused (tested with a stale in-memory model). A first-time insert relies on `teacher_profiles_user_id_unique` plus a locking re-read, never a gap lock.
  - **C-2 (fixed):** `OtpService::request` expired older challenges with a range `UPDATE` on (`phone`, `purpose`, …), then inserted; two first-time requests for different phones in the same index gap could deadlock. Now: plain read of the open challenge ids, then `UPDATE … WHERE id IN (…)`.
  - **L1 (documented, not fixed):** `RoleAssignmentService::revoke` locks a `user_roles` range (its condition is not a unique key). It could deadlock with `InstituteService::create` only when the revoking actor is the same user, and no endpoint calls revoke. To fix when the first role-revoke endpoint is built (lock by primary key or by `scope_key`).
  - **D-1 (fixed in `SPRINT_SCHEMA.md` G4.2/G4.3):** the accept rule ("select the invitation by `code_hash` FOR UPDATE", then lock the student) contradicted student-first, and create locked a pending-invitation range. Both are rewritten to Pattern I with primary-key locks only.
- **`App\Support\Transactions::retrying()`:** at most **3** attempts; retries **only** a deadlock (SQLSTATE `40001` / MySQL 1213), found by walking the exception chain. Everything else (validation, authorization, business rules, unique-key violations, any other error) is rethrown unchanged on the first attempt. A lock wait timeout (1205) is not retried. Exhausted retries or a lock wait timeout → `ServiceBusyException` → **`503 {"message":"The service is busy. Please try again."}` with `Retry-After: 1`**, plus one `db.service_busy` warning that holds only the reason and attempt count. Used by every write service (OTP request and verify, user name, teacher profile, institute create and update, students, teacher assignment and end, staff add and end, role grant and revoke). All of them write only to the database, so a retry is safe.
  - **Nested calls propagate; only the outermost call retries.** Inside an outer transaction (`InstituteService::create` → `grant`; `StaffService::add`/`end` → `grant`/`revoke`) the helper runs the callback once through Laravel's nested transaction and lets **every** exception propagate unchanged: no catch, no log, no conversion. The outermost `retrying()` classifies it by walking the chain (`DeadlockException` → `QueryException` → MySQL code), so a nested deadlock retries the whole outer transaction (≤ 3 attempts) and a nested 1205 is still a `503` without retry. Classification, the `db.service_busy` warning and `ServiceBusyException` happen only at the outermost level.
  - Correction found by the H1 pre-commit audit: the first draft converted a nested deadlock into `ServiceBusyException` inside the inner call, which the outer call could not recognise, so a deadlock in a nested service call was never retried (a 503 at once, nothing committed). Fixed and covered by regression tests through Laravel's real nested `DB::transaction()` machinery.
- **OTP send after commit:** the code is generated and its challenge committed inside `Transactions::retrying()`; the code is sent **after** it returns, so a rolled-back attempt's code is never sent. The per-phone cache lock now lasts `3 × lock wait timeout + 15 s` (45 s), so it outlives every retried attempt plus the send; with the old 10 s lease a second request for the same phone could have started while the first was still retrying.
  - **Failed delivery (compensation):** if the send fails, the committed challenge is retired by primary key (`expires_at` = now). The **original delivery exception is always rethrown**. If retiring itself fails, its error is swallowed and one `otp.retire_failed` warning is logged with the challenge UUID only (never the phone, the code or the database error); the unsent code then stays valid until its normal expiry (5 minutes), and nobody received it.
- **Lock wait timeout:** `innodb_lock_wait_timeout = 10` for this application's MySQL sessions only, via `Pdo\Mysql::ATTR_INIT_COMMAND` (`DB_LOCK_WAIT_TIMEOUT`, default 10); the server-wide default (50 s) is untouched.
- **Logging privacy:**
  - `mask_bindings_in_exception_messages => true` on the `mysql` connection: query exception messages show `?`, never the bound phone, name or hash. MySQL's own "Duplicate entry '…'" text still contains the duplicate key's values, which is why business-rule exceptions must not be logged.
  - `dontReport`: `DomainRuleException`, `InvalidOtpException`, `OtpThrottledException`, `AccountSuspendedException`, `ServiceBusyException` (expected outcomes, previously logged with their wrapped database errors on every occurrence). `OtpUnavailableException` is still reported (an operational fault).
  - `#[\SensitiveParameter]` on every OTP code and phone parameter (`OtpService`, `OtpCodeHasher` including the IP hash, all three drivers and the `OtpDriver` interface, `PhoneMasker::mask`). Tested: traces of failing calls contain neither the phone nor the code, and show `SensitiveParameterValue`.
- **Tests:** 23 new; `php artisan test` → **366 passed (3448 assertions)** on two consecutive runs (343 existing unchanged + 23). After the audit: nested deadlock retried by the outermost caller (one real commit, counted with `TransactionCommitting`, no warning); nested deadlock on every attempt (3 outer attempts, `503`, one warning); nested lock wait timeout (no retry); nested non-concurrency failures propagate unchanged; and a failed retirement after a failed send (original error rethrown, `otp.retire_failed` with the UUID only). Retry behaviour is tested at the outermost transaction level (no wrapping test transaction): a session-scoped temporary table proves a failed attempt's writes are rolled back and only the committed attempt remains, and an OTP request whose first attempt deadlocks commits exactly one challenge and sends exactly its code; the committed rows are deleted afterwards.
- **Residual risks and open items:**
  - **45 s OTP lease:** the lease is a design bound, not a hard one (`innodb_lock_wait_timeout` limits each statement's wait, not a whole attempt's). If issuance plus the send ever outlasts it, a second request for the same phone and purpose can take the lock while the first has not committed; both plain reads then see no open challenge and no cooldown, so two codes can be valid until expiry. Both go only to that phone, the request rate limits still apply, and the first request's release cannot free the second's lock (owner-checked). A real SMS driver must use an HTTP timeout well under 15 s.
  - **L1 open:** the `RoleAssignmentService::revoke` `user_roles` range lock (above).
  - **Two-process concurrency open:** deadlocks are simulated with MySQL-shaped exceptions passed through Laravel's real transaction machinery, not provoked; a real two-connection harness is E2c (PHP here has no `mysqli`, and PDO has no asynchronous queries, so it needs a second process).
  - **Production `zend.exception_ignore_args=On`** in `php.ini` (deployment item): defence in depth alongside `#[\SensitiveParameter]`.
  - **MySQL duplicate-entry messages may contain personal data:** "Duplicate entry '…' for key …" is MySQL's own message text, not a binding, so `mask_bindings_in_exception_messages` does not hide it. A unique violation that is not handled and mapped would log it.
  - **E2 requirement:** the guardian-invitation pending unique key (`(student_id, guardian_phone, pending_flag)`) must map to `409` **without chaining the database error** (no `previous` exception), because that duplicate entry contains the guardian's phone. Recorded here for D-023 (E2); D-023 stays reserved for E2.
  - A real SMS driver must still send only after commit (now enforced by `OtpService` itself); trusted proxies (D-019).

## D-023 — E2 guardian invitations, linking, revocation and children — **Verified by automated tests 2026-11-01**

- **Scope:** `POST /students/{uuid}/guardian-invitations` (§9.1), `POST /guardian-invitations/accept` (§9.2), `POST /guardian-invitations/{uuid}/revoke` (§9.3), `DELETE /students/{uuid}/guardian-links/{guardianUuid}` (§9.4) and `GET /parents/me/children` (§9.5). Contract → **v0.7**. **No migrations, no new packages**; the R2 tables were already applied. Nothing ran against `tuitionhub`.
- **Decisions E2-1 – E2-9** (approved in the E2 implementation brief, 2026-11-01):
  - **E2-1:** `active_enrollments` is always `[]` until the enrolment tables exist (contract note in §9.5); no class is invented.
  - **E2-2:** accepting for a suspended institute → `403 institute_suspended`.
  - **E2-3:** the children list includes students of suspended institutes (read-only).
  - **E2-4:** inviting your own phone → `422` on `guardian_phone`; the inviter accepting their own invitation → the generic `422`.
  - **E2-5:** an invitation may be revoked by its inviter (while still active staff there), **any admin of the same institute**, or `super_admin`.
  - **E2-6:** guardian phones are accepted as local `0771234567` or E.164 `+94771234567` and stored as E.164.
  - **E2-7:** accept is limited to **5 attempts per 15 minutes per user**, every attempt counting, on top of the general 60/min.
  - **E2-8:** `409` reasons `invitation_pending`, `invitation_not_pending`, `link_already_active`.
  - **E2-9:** an invitation from staff who later left stays valid until it expires (the inviter's current membership is not re-checked on accept; `linked_by_user_id` is still the inviter).
- **Lock order (D-022 Option A, Pattern I):** `institutes` → `student_profiles` → `guardian_invitations` → `guardian_profiles` → `student_guardians`, each by primary key; `users` **shared only**, never exclusive. Sets of rows (pending invitations for a student and phone, active links, the next primary) are found with **plain reads after the institute lock**, then changed by primary key: no range locks, no range updates (tested through the executed SQL).
  - **Create:** institute `FOR UPDATE` → re-authorize (`Gate::forUser`) → student `FOR UPDATE` → plain read of the pending row → an expired one is set to `expired` by primary key → insert → audit.
  - **Accept:** lock-free lookup of `id`, `student_id`, `institute_id` by `code_hash` (malformed codes never reach the database) → institute → student → invitation (`FOR UPDATE` by id) → **one `LOCK IN SHARE MODE` read of the caller and the inviter, ordered by id** → checks → guardian profile (plain read; insert if missing; on a duplicate, a locking re-read by `user_id`) → plain reads of the active link and active primary → link insert → invitation update → two audit rows. The explicit shared `users` read follows `SPRINT_SCHEMA` G4.2 rule 2 (after the three exclusive locks, before any write); the later inserts take their implicit shared locks on rows this transaction already holds.
  - **Revoke invitation:** institute → re-authorize → student → invitation. **Revoke link:** institute → re-authorize → student → the link by id → revoke (frees `primary_flag`) → the oldest remaining active link (`linked_at`, then `id`) by id → promote.
  - All four writes use `Transactions::retrying()` (≤ 3 attempts on deadlock; nested calls propagate, D-022). Create generates its code **outside** the transaction, so a deadlock retry reuses it; a `code_hash` collision (not a concurrency error) gets a new code and a new transaction, up to **5** times, then `500`.
- **Codes and the key:** `App\Guardians\GuardianInviteCodes`: 8 characters from `23456789ABCDEFGHJKMNPQRSTVWXYZ` with `random_int`, shown as `XXXX-XXXX`; normalised by upper-casing and removing spaces and hyphens; `code_hash` = HMAC-SHA256(normalised code, `GUARDIAN_INVITE_HMAC_KEY`). The key is read lazily (`base64:` supported, ≥ 32 bytes); missing or short → `503 {"message":"Guardian invitations are not available."}` for create and accept only (tested: another endpoint and `php artisan inspire` still work). `.env.example` has an empty `GUARDIAN_INVITE_HMAC_KEY=`; tests set a throwaway key.
- **Generic 422** (`InvalidInvitationCodeException`, one message, never chained, not reported): malformed, unknown, used, revoked or expired codes; a phone mismatch; an unverified phone; the inviter accepting. A lazily expired invitation is **committed** as `expired` before the `422` (outcome pattern), and likewise before the `409` of a revoke. Check order on accept: account suspended → `403`; invitation state and expiry; phone and verification; inviter; **only then** institute suspended → `403` (so a suspension is revealed only to the invited guardian); then `409 link_already_active`.
- **Primary guardian (Q25/Q37):** the first active link becomes primary under the student lock; revoking the primary promotes the oldest remaining active link in the same transaction; none left → no primary. `guardian_link.created` records `primary_link_id` old `null` → new id when the new link becomes primary; `guardian_link.revoked` records old → new (or `null`) when the primary changes. Internal link IDs only.
- **Privacy:**
  - `#[\SensitiveParameter]` on every parameter of `GuardianInviteCodes` (including the key), `GuardianInvitationService::create($guardianPhone)`, `accept($code)`, the private `insertInvitation($guardianPhone, $codeHash)`, and `SriLankanMobile::normalize`. Tested by reflection and through a real stack trace.
  - Reporting: `InvalidInvitationCodeException` is in `dontReport` (expected outcome); `GuardianInviteUnavailableException` is **reported** (a configuration fault; its message never names the key).
  - **Accepted timing difference:** a malformed code returns without any database query, while a well-formed code is looked up, and a known code with the wrong phone also takes the locking transaction, so response times differ between these cases. The response body is identical. Accepted: about 6.6 × 10¹¹ possible codes, 5 attempts per 15 minutes per user (E2-7), and a match also needs the caller's verified phone.
  - The pending-invitation and active-link unique keys map to `409` **without chaining** the database error (its duplicate-entry text contains the guardian's phone); tested by forcing each backstop.
  - Audit allowlists (§13): UUIDs, `relationship`, `status`, `expires_at`, `guardian_phone_masked` (`+9477*****67`), `is_primary`, `primary_link_id`. Tests scan the stored audit JSON and captured log output for the code, its hash, full phones and the student's name.
  - Throttle keys use the user id only. Creating a guardian profile is not audited (no contract event).
- **Implementation choices for founder review** (not stated by the contract or the decisions; reversible):
  1. `super_admin` gets `403` on create (not a member, as for §8.1); teacher-only callers get `404` on every student guardian endpoint (no student data outside class rosters).
  2. Revoke invitation: other staff of the institute `403`; an inviter who has left `404` (unrelated now).
  3. Phone normalisation accepts exactly the two E2-6 forms; spaces, hyphens and `94…` without `+` are `422`.
  4. Accept's `code` is a string of at most 32 characters; a missing, non-string or over-long value gets Laravel's normal `422` on `code`, not the generic message (it reveals nothing about any invitation).
  5. Self-invite is checked after authorization, under the lock, so a caller who may not invite still gets `403`/`404`.
  6. Revoke link resolves `guardianUuid` only after authorization; malformed, unknown, not a guardian and no active link are the same `404`.
  7. The children list is ordered by `linked_at`, then id, with the normal four-key pagination `meta` (§1.3), although the §9.5 example shows `"meta": {}`.
- **Tests:** 86 new; `php artisan test` → **452 passed** (366 existing unchanged + 86). Unit (codes, normalisation, key, redaction; 9), create (17), accept (22), revoke invitation (12, including an unknown query field), revoke link (11), children (6), lock order and re-authorization (9).
- **Open / not done:** real two-process concurrency (E2c; VP-42 is tested sequentially, the locks and unique keys are the backstop); guardian access for §10.5 and §11 comes with those endpoints; `active_enrollments` with the enrolment tables; in `log` OTP mode `phone_verified_at` is not real phone verification (C6); the D-022 items (L1, production `zend.exception_ignore_args=On`, trusted proxies) are unchanged.

## D-024 — R3 schema for G5 classes and G6 attendance — **APPROVED by TuitionHub founders (2026-11-01); not yet migrated**

- **Scope:** `docs/SPRINT_SCHEMA.md` Part 3 becomes the migration specification for `classes`, `class_fee_versions`, `class_sessions`, `class_enrollments` and `attendances` (M3: migrations `2026_10_29_000014`–`000018`). Contract → **v0.7.1**. Approved in the R3 corrections brief on 2026-11-01; Q38–Q56 are all **Decided (D-024)**. Nothing is migrated yet. M3 runs first only on `tuitionhub_test`, and applying it to `tuitionhub` needs its own approval.
- **Contradictions resolved:**
  - **X1:** `enrolled_on DATE` (contract), not the brief's `enrolled_at`.
  - **X2:** a cancelled session frees its slot (contract v0.7.1 §10.1).
  - **X3:** `max_students` NOT NULL, ≥ 1.
  - **X4:** fee amount > 0.
  - **X5:** fee versions are append-only except a single `effective_to` close.
  - **X6:** enrolments `active|withdrawn`, sessions `scheduled|cancelled`.
  - **X7:** withdraw and cancel endpoints proposed for E3 (contract §8.5, §10.6).
- **Tenant and history invariants (database-enforced):**
  - **Class and student share the institute:** `class_enrollments.institute_id` (new column) with two composite FKs: `(class_id, institute_id) → classes(id, institute_id)` and `(student_id, institute_id) → student_profiles(id, managing_institute_id)`.
  - **Session and enrolment share the class:** `attendances.class_id` (new column) with `(session_id, class_id) → class_sessions(id, class_id)` and `(enrollment_id, class_id) → class_enrollments(id, class_id)`.
  - **One active enrolment, unlimited history:** `active_flag = IF(status = 'active', 1, NULL)`, unique `(class_id, student_id, active_flag)`. Withdrawn rows are never reactivated, and re-enrolling creates a new row.
  - **One non-cancelled session per slot:** `slot_flag = IF(status <> 'cancelled', 1, NULL)`, unique `(class_id, session_date, start_time, slot_flag)`.
  - **One open fee version per class:** `current_flag = IF(effective_to IS NULL, 1, NULL)`, unique `(class_id, current_flag)`, plus unique `(class_id, effective_from)`. Non-overlap is a service rule under the class lock.
  - **One mark per session and enrolment:** unique `(session_id, enrollment_id)`.
  - Every FK is `ON DELETE RESTRICT` with no `CASCADE`, so sessions, enrolments and attendance history cannot be deleted from under each other.
- **Enrolment dates (D-024 corrections):**
  - `enrolled_on` may be in the future, but must lie within the class's `starts_on`–`ends_on`.
  - A withdrawal requested before `enrolled_on` is stored as `withdrawn_on = enrolled_on`: a zero-length enrolment, never active.
  - A withdrawal that would leave existing attendance outside the enrolment (attendance for a session dated `>= withdrawn_on`) → `409`.
  - "Active on date `d`" means `enrolled_on <= d < withdrawn_on`.
  - A future enrolment has status `active`, so it counts towards `max_students` from creation.
- **Other decisions:**
  - Q44: `attendances.notes VARCHAR(500)`.
  - Q45: whole-minute `TIME` CHECKs.
  - Q46: class transitions `draft → active → archived` (or `draft → archived`); `archived` is final.
  - Q47: enrolment and session creation need an `active` class; `session_date` within the class dates.
  - Q49: attendance audit payloads, with `notes_changed` only, never the note text.
  - Q50: a session with attendance cannot be cancelled.
  - Q51: guardians keep seeing past attendance after a withdrawal.
  - Q53: model `App\Models\TuitionClass`, morph alias `class`.
  - Q54: `current_fee` is the version in force today, otherwise the earliest future one.
  - Q55: no attendance for future sessions.
  - Q56: attendance serialised by the institute lock for the sprint.
  - Fee versions and attendance have no `uuid`.
- **Lock order** (extends D-022/D-023): `institutes` → `classes` → `class_fee_versions` → `class_sessions` → `class_enrollments` → `attendances` → `users` (shared only). Sessions always come before enrolments. Rows are locked by primary key; sets of rows come from plain reads after the class lock. G5/G6 never locks `student_profiles` explicitly.
- **Validation plan:**
  - VP-60–VP-83. M3 covers the database and model cases: VP-60–VP-75, VP-79, VP-80 and the database half of VP-81.
  - VP-76–VP-78, VP-82 and VP-83 are E3 service tests.
- **Pending:** M3 implementation and its verification record; the E3 plan, which finalises the §8.5/§10.6 permission and audit details; applying M3 to `tuitionhub` (separate approval).

## D-025 — M3 G5/G6 migrations and models verified — **Verified by automated tests 2026-11-01.**

- **Scope:** five new migrations, appended after R2 (D-014 append-only):
  - `2026_10_29_000014_create_classes_table`
  - `…000015_create_class_fee_versions_table`
  - `…000016_create_class_sessions_table`
  - `…000017_create_class_enrollments_table`
  - `…000018_create_attendances_table`

  They implement `docs/SPRINT_SCHEMA.md` Part 3 (D-024) exactly. Five models: `TuitionClass` (table `classes`), `ClassFeeVersion`, `ClassSession`, `ClassEnrollment`, `Attendance`. **No endpoints, services, seeders or packages.**
- **Tenant isolation through composite FKs:**
  - `class_enrollments (class_id, institute_id) → classes (id, institute_id)` and `(student_id, institute_id) → student_profiles (id, managing_institute_id)`: an enrolment's class and student always share the institute.
  - `attendances (session_id, class_id) → class_sessions (id, class_id)` and `(enrollment_id, class_id) → class_enrollments (id, class_id)`: an attendance row's session and enrolment always share the class.
  - Tested in all three mismatch directions (VP-60, VP-61). A class can't change institute while it has enrolments (`ON UPDATE NO ACTION`).
- **Generated flags (STORED, nullable):**
  - `class_enrollments.active_flag`: one active enrolment per class and student, with any number of withdrawn rows (VP-62, VP-63).
  - `class_fee_versions.current_flag`: one open fee version per class; plus unique `(class_id, effective_from)` (VP-68).
  - `class_sessions.slot_flag`: one non-cancelled session per class, date and start time; cancelled sessions free the slot and stay as history (VP-66).
  - Direct writes fail with MySQL 3105, and the models refuse them before any query (VP-72).
- **History preserved:**
  - Every FK is `ON DELETE RESTRICT`, with no cascades. Classes, sessions, enrolments, fee versions and markers with dependants can't be deleted (VP-67, VP-70, VP-73).
  - Re-enrolling creates a new row and leaves the old one untouched (VP-64).
  - Attendance survives a withdrawal (VP-70).
  - A zero-length enrolment (`withdrawn_on = enrolled_on`) is accepted and never active (VP-81, database half).
- **Manual attendance only:** `attendances_method_chk` allows only `manual`. `notes` is `VARCHAR(500)`, and longer input is an error, never truncated.
- **Fee versions are append-only in the model:**
  - `ClassFeeVersion` refuses any change to `class_id`, `amount`, `currency`, `billing_cycle`, `effective_from` and `created_by_user_id`.
  - It allows `effective_to` to be set once, from NULL, and **refuses deletion** (VP-75).
- **Future enrolments count toward capacity:** capacity counts `status = active`, and a future enrolment is `active` from creation (D-024; enforced by the E3 service).
- **Other checks:**
  - 17 CHECKs and 17 FKs, exactly as specified, all `RESTRICT`/`NO ACTION`.
  - Every named index with its columns in order; DATE/TIME/DATETIME/DECIMAL types; no `deleted_at`; migration order (VP-79).
  - Morph aliases `class`, `class_fee_version`, `class_session`, `class_enrollment`, `attendance`; public v4 UUIDs on classes, sessions and enrolments only; no internal ids or flags serialized (VP-80).
- **Tests:** 45 new (structure 8, classes 5, fee versions 6, sessions 6, enrolments 9, attendance 6, models 5), plus cases added to `MorphMapTest` and `GeneratedColumnGuardTest`. `php artisan test` → **497 passed**.
- **Schema verified only on `tuitionhub_test`** (D-010 guard, through `RefreshDatabase`). **The development database `tuitionhub` was not touched**; applying M3 there needs its own approval.
- **Deferred:** the Pint style findings in `tests/Feature/Schema/Concerns/SchemaFixtures.php` and `app/Audit/AuditLogBuilder.php` (`fully_qualified_strict_types`, `ordered_imports`). Both predate M3 and are left for a separate style commit.
- **Open:** E3 services and endpoints (VP-76–VP-78, VP-82, VP-83), including the proposed §8.5 withdraw and §10.6 cancel endpoints.

## D-026 — E3a classes, fee versions, enrolments, teacher classes and catalogue — **Verified by automated tests 2026-11-01**

- **Scope:** ten endpoints. Contract → **v0.8**. **No migrations, no packages**; nothing ran against `tuitionhub`.

  | § | Endpoint |
  |---|---|
  | §7.1 | `POST /institutes/{uuid}/classes` |
  | §7.2 | `GET /institutes/{uuid}/classes` |
  | §7.3 | `GET /classes/{uuid}` (public route) |
  | §7.4 | `PATCH /classes/{uuid}` |
  | §7.5 | `GET /public/classes` (public route) |
  | §6.4 | `GET /me/teacher/classes` |
  | §8.3 | `POST /classes/{uuid}/enrollments` |
  | §8.4 | `GET /classes/{uuid}/enrollments` |
  | §8.5 | `POST /enrollments/{uuid}/withdraw` |
- **Authorization:**
  - **Policies:** `InstitutePolicy::createClass` and `::viewClasses`; new `ClassPolicy` (`view`, `update`, `viewEnrollments`, `enroll`) and `ClassEnrollmentPolicy::withdraw` (delegates to `enroll`).
  - **Assigned teacher:** `TenantAccess::isAssignedTeacher` requires `classes.teacher_id` to be the caller's profile **and** an active assignment at the class's institute. An ended assignment loses access to private class data and rosters at once.
  - **Order — visibility first** (review fix, see below): a caller who cannot see the class gets `404`; one who can see it but may not act gets `403`; an allowed caller at a suspended institute gets `institute_suspended` (except super_admin where allowed). Every service locks the institute row and authorizes again.
- **Lock order (D-022/D-024):**
  - Class create locks `institutes` only (the class and fee rows are new).
  - Update: `institutes` → `classes`; the open fee version is closed by primary key.
  - Enrol: `institutes` → `classes`.
  - Withdraw: `institutes` → `classes` → `class_enrollments` (by id).
  - Teacher assignments, active counts, duplicates and attendance are plain reads after the locks.
  - All writes run in `Transactions::retrying()`. The active-enrolment unique key maps to `409 already_active` without the database error. Tested through the executed SQL.
- **Accepted choices** (founders, 2026-11-01, in the E3a corrections brief):
  1. **`super_admin`:** `403` on §7.1, §8.3 and §8.5 (not a member, as in §8.1). It may update classes (§7.4, contract) and read rosters (§8.4).
  2. **Teacher-only callers:** `403` on the §7.2 institute list (FD-4). `404` on every endpoint of a class they cannot see, including private class pages, rosters and writes (see the visibility fix below).
  3. **Public visibility** also needs the institute to be active (§3: suspension never reveals). The public class page reads an optional SPA session through the sanctum guard, and a suspended account counts as anonymous.
  4. **Statuses:**
     - Archived is final: any update → `409 class_archived`.
     - Other disallowed transitions → `409 invalid_status_transition`.
     - Enrolling into a draft or archived class → `409 not_active`.
  5. **Dates:**
     - Changing `starts_on`/`ends_on` must keep every active enrolment's `enrolled_on` inside the window (`422`).
     - `enrolled_on` defaults to today (`Asia/Colombo`), as the sprint draft says.
     - `withdrawn_on` is required and may not be in the future (`422`): a future date would free the place before the student leaves.
  6. **Limits:**
     - `max_students` is 1–10,000; a full class → `409 class_full`; a future enrolment counts at once (D-024).
     - The fee is a string with exactly 2 decimals, greater than 0, LKR only, with exactly four keys. A `fee` in §7.4 always appends a version, and `previous_effective_to` is audited.
  7. **Ordering:**
     - §7.2: `title`, then id.
     - §6.4: `starts_on`, then `title`, then id.
     - §8.4: student `full_name`, then id.
     - §7.5 is neutral: `starts_on`, `title`, `uuid`; no boost or rank.
  8. **`AuditLoggerTest`** (VP-57) used `class.created` as its example of an action without an allowlist. It now uses a fictional action name, since E3a gives `class.created` an allowlist.
- **Audit payloads** (§13): the class snapshot never includes the description, only `description_changed`; enrolment events never include the student's name.
- **Review fix — consistent hidden-resource 404 (visibility first):**
  - `ClassPolicy::update` and `::enroll`, and therefore `ClassEnrollmentPolicy::withdraw`, now check visibility (`canSeePrivateData`) first.
  - A caller who cannot see the class gets `404`, including an unassigned teacher at the same institute. Previously that caller got `403`, which revealed that the private class existed.
  - The assigned teacher and `super_admin` (on enrol and withdraw) can see the class, so they get `403`. Staff can see it, so they get `403` on update.
  - Tested as a matrix: unassigned teacher 404 on show, roster, update, enrol and withdraw; assigned teacher 200 on show and roster, 403 on the writes; unrelated users 404 everywhere, also with invalid bodies.
  - Contract §7.4, §8.3 and §8.5 wording updated within v0.8.
- **Review fix — authorization before validation:**
  - `EnrollmentService::withdraw` checked the `withdrawn_on` format and future date (`422`) before the transactional `Gate::authorize` (`403`/`404`). Both now run after authorization, inside the transaction. `EnrollmentService::enroll`'s `enrolled_on` format check moved the same way.
  - Tested: a hidden enrolment with a future or malformed `withdrawn_on` is `404` over HTTP, and an `AuthorizationException` (404), not a `ValidationException`, from a direct service call. An allowed caller still gets the `422`.
- **Carried forward:**
  - **E3b (sessions and attendance):** the D-024 date boundaries apply.
    - Session creation needs an `active` class, and `session_date` must lie within the class's `starts_on`–`ends_on`.
    - Attendance is allowed only for enrolments active on the session's date (`enrolled_on <= d < withdrawn_on`), never for cancelled or future sessions.
  - **E4 (invoices):** an invoice needs an **active** enrolment (contract §11.2). A withdrawn, or zero-length, enrolment cannot be invoiced.
- **Tests:** 66 new; `php artisan test` → **563 passed (7054 assertions)** on two consecutive runs (497 existing + 66):
  - create class 11, show 6, institute list 4, update 10, catalogue 3 (including the 60/min limit), teacher classes 2, visibility and validation order 5;
  - enrol 10, roster 2, withdraw 7;
  - lock order, re-authorization and the unique-key backstop 6.

  `AuditLoggerTest` was adjusted as in item 8.
- **Open:** E3b (sessions §10.1–§10.2, cancel §10.6, attendance §10.3–§10.5); real two-process concurrency (E2c); applying M3 to `tuitionhub` (separate approval).

## D-027 — E3b sessions, cancellation, attendance and the guardian attendance view — **Verified by automated tests 2026-11-01**

- **Scope:** contract → **v0.9**. **No migrations, no packages**; nothing ran against `tuitionhub`. With this, the core journey runs end to end on the API: institute → class → teacher → student enrolled → session → attendance recorded → parent sees attendance.

  | § | Endpoint |
  |---|---|
  | §10.1 | `POST /classes/{uuid}/sessions` |
  | §10.2 | `GET /classes/{uuid}/sessions` |
  | §10.6 | `POST /sessions/{uuid}/cancel` |
  | §10.3 | `POST /sessions/{uuid}/attendance` |
  | §10.4 | `GET /sessions/{uuid}/attendance` |
  | §10.5 | `GET /parents/me/children/{uuid}/attendance` |

  §9.5 `active_enrollments` is now filled.
- **Authorization (visibility first, D-026):**
  - New `ClassPolicy::manageSessions`: the assigned teacher or an institute manager. Also new: `viewSessions`, `markAttendance` (assigned teacher, admins, active staff) and `viewAttendance`.
  - `ClassSessionPolicy` (`cancel`, `markAttendance`, `viewAttendance`) applies them to the session's class.
  - Callers who cannot see the class → `404`; visible but not allowed → `403`; suspended institute → `institute_suspended` for writes.
  - The guardian view requires `TenantAccess::hasActiveGuardianLink`, re-read on every request, else `404`.
- **Lock order (D-022/D-024):**
  - Session create: `institutes` → `classes`.
  - Cancel and mark: `institutes` → `classes` → `class_sessions` (by id).
  - Enrolments and existing attendance are plain reads after the class lock. Attendance rows are corrected by primary key.
  - All writes run in `Transactions::retrying()`. The session-slot unique key maps to `409 session_slot_taken` without the database error. Tested through the executed SQL, with re-authorization after an assignment ends.
- **Rules settled here** (from the contract and D-024; reported for founder review):
  1. **Sessions:** the class must be `active` (`409 not_active`), and `session_date` must be within the class dates (`422`). Times are `HH:MM`. `super_admin` and non-manager staff → `403`.
  2. **Cancel:** `scheduled` only (`409 session_cancelled`); not with attendance (`409 session_has_attendance`, Q50); not for an archived class (`409 class_archived`).
  3. **Attendance:**
     - Future sessions → `409 session_in_future` (Q55 decided); cancelled sessions → `409 session_cancelled`; archived class → `409 class_archived`.
     - Eligibility is `enrolled_on <= d < withdrawn_on` (Q48), with per-record `422`s.
     - Upsert: new → `attendance.recorded` (one audit row per mark); changed → `attendance.corrected` with old and new `status`/`method` and `notes_changed` only (Q49); unchanged → nothing.
     - `method` is required (`manual`); `notes` up to 500 characters.
  4. **Attendance sheet (§10.4):** rows are the enrolments active on the date, plus enrolments already marked for the session. Not paginated. Readable while the institute is suspended. `super_admin` → `403` (not listed in the contract).
  5. **Guardian view (§10.5):**
     - The window defaults to the 30 days ending today; at most 366 days inclusive.
     - Ordered newest first.
     - Past attendance stays visible after a withdrawal (Q51).
     - No notes or marker are shown to guardians.
  6. **§9.5 `active_enrollments`:** enrolments with status `active` (future ones included), each with class and institute name.
  7. **Carry-forward from D-026:** a class's date change must keep every non-cancelled session inside the new dates (`422`). Cancelled sessions do not hold the dates.
  8. **New 409 reasons:** `session_slot_taken`, `session_cancelled`, `session_has_attendance`, `session_in_future`. **New events:** `session.created`, `session.cancelled`; `attendance.recorded` and `attendance.corrected` now have payloads.
- **Review fixes (before commit):**
  - **Notes preservation:**
    - An omitted `notes` key keeps the stored note on a correction; an explicit `null` clears it; a string replaces it; a first mark without notes stores none.
    - `AttendanceService::mark` builds the mark from the submitted keys only, so a correction compares only status, method and submitted notes. If none changed, the row is not updated: `marked_by`/`marked_at` never change on their own.
  - **Session list privacy:**
    - `ClassPolicy::viewSessions` allows only super_admin, the assigned teacher (active assignment) and the institute's admins and active staff. It never uses the public catalogue rule: a publicly listed class does not expose its schedule.
    - Any other signed-in caller gets `404`. An unauthenticated caller gets `401`, because the route is authenticated and contract §1.4 rule 1 puts `401` before any resource lookup. The brief asked for `404` there too; it was kept at `401` to stay within the contract and reported.
- **Request limit:** an attendance submission holds 1–500 records (`422` beyond), each enrolment at most once.
- **The core C1 backend journey is complete on the API:** institute → class → teacher → student enrolled → session → attendance recorded → parent sees attendance.
- **Tests:** 37 new; `php artisan test` → **600 passed (7758 assertions)** on two consecutive runs (563 existing + 37):
  - session create 9, list and cancel 6 (including the public-class schedule privacy test);
  - mark attendance 9 (including notes semantics), attendance sheet 2, guardian attendance and children enrolments 5;
  - lock order, re-authorization and the slot backstop 5;
  - class dates and sessions 1.
- **Open:** E4 (invoices, demo payments, commission; an invoice requires an active enrolment, D-026); real two-process concurrency (E2c); applying M3 to `tuitionhub` (separate approval); frontend wiring of the core journey.

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
- D-013: the open schema questions Q19 (OTP details; only the purge of expired challenges remains, explicitly deferred, after D-019) and Q21 (lookup seed lists; resolved by D-020 except Sinhala/Tamil names and exam dates). VP-1 and Q20 are resolved (D-014).
- D-015 (approved): the audit IP retention period (pre-launch privacy review) and the real-money guardian billing policy (separate pre-launch review).
- D-016: separate approval to apply M2 to `tuitionhub`. (The invalid or missing IP handling was decided in D-017.)
- "R1 design notes — not yet approved", for groups after G1–G3. For G1–G3 they are superseded by `docs/SPRINT_SCHEMA.md` (D-013).

C8 was approved as D-011, and its open questions were decided on 2026-10-28.

## Not yet done from the approved Day 1 plan (status as of 2026-10-27)

- Step 3: `install:api` (Sanctum), `statefulApi()`, the `/api/v1` route group, the CORS config, and the `config/otp.php` and `config/demo.php` files.
- Step 4: migration groups 0–2 (framework tables, lookups, `otp_challenges`, `audit_logs`). No migrations have been written or run.
- Step 5: OTP and auth tests.

Each of these needs its own approval before work starts.
