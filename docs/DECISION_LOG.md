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

## D-011 — C8 role model — **Approved by founders (2026-10-27)**

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
- **Effect:** closes C8 and unblocks migration group 3 onwards in the approved Day 1 plan. The `user_roles` vocabulary is limited to the three roles above.
- **Follow-ups (not done in this change; each needs its own approved edit):**
  - `docs/PRODUCT_DECISIONS.md` still lists C8 as **Open**. Open items 2 (teacher assignment consent) and 3 (who may bill) are answered by this decision.
  - `docs/API_CONTRACT.md` §3 is still marked "proposal pending C8". Two of its rules conflict with this decision and need a contract change with a changelog entry:
    - §6.2 says a teacher assignment also creates a `teacher` role row in `user_roles`.
    - §9.2 says accepting an invitation creates a global `guardian` role.
  - Under this decision, teacher and guardian access come from `teacher_institute` and `student_guardians`, not from `user_roles`.
- **Not decided by the founders (open questions):**
  - Whether the owner also receives an `institute_admin` row for their institute. The contract proposes that they do; without it, an owner who is not an admin could not create invoices.
  - Whether `super_admin` may create invoices. The contract's "billing user" does not include it.
  - Whether `user_roles` is a custom table or `spatie/laravel-permission` with teams. "A `user_roles` table" suggests a custom table; this is not yet confirmed.

---

## Pending founder decisions

None at technical level. C8 was approved as D-011. The open questions listed under D-011 still need answers before the related policies are written.

## Not yet done from the approved Day 1 plan (status as of 2026-10-27)

- Step 3: `install:api` (Sanctum), `statefulApi()`, the `/api/v1` route group, the CORS config, and the `config/otp.php` and `config/demo.php` files.
- Step 4: migration groups 0–2 (framework tables, lookups, `otp_challenges`, `audit_logs`). No migrations have been written or run.
- Step 5: OTP and auth tests.

Each of these needs its own approval before work starts.
