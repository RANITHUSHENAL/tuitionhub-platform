# TuitionHub — Local Setup Guide (API)

**For:** co-founders setting up the Laravel API in `api/` on their own machine.
**Last checked:** 2026-10-27, against branch `sprint/day1-api-scaffold`.
**Background:** the versions and decisions behind these steps are in `docs/DECISION_LOG.md` (D-002 to D-010).

Every password below is a placeholder such as `<APP_DB_PASSWORD>`. Choose your own and keep it in your password manager. Never paste a real password into this file, a commit, a chat or an issue.

> ⚠️ **Never run `composer run setup`.** The skeleton's `setup` script runs `php artisan migrate --force` against whatever database your `.env` points at, then runs `npm install` and `npm run build`. Migrations in this project are only run as a separately approved task. Also never run `composer create-project` here: the project is already scaffolded.

---

## 1. Prerequisites

| Tool | Version | Check with |
|---|---|---|
| Git | any recent | `git --version` |
| PHP (command line) | **8.3 – 8.5** (the founder machine uses 8.5.12) | `php -v` |
| Composer | 2.x | `composer --version` (it must report the same PHP as `php -v`) |
| MySQL Server | **8.4 LTS** (not MariaDB) | `SELECT VERSION();` in a MySQL client |

**PHP extensions** that must be enabled (`php -m`): `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`, `sodium`, `tokenizer`, `xml`, `ctype`, `bcmath`. On Windows, enable any missing ones in `php.ini`.

**Windows notes:**
- If XAMPP is installed, make sure `php` on PATH is the new PHP, not `C:\xampp\php` (8.2 is too old). Check with `where php` or `(Get-Command php).Source`.
- Keep XAMPP's MariaDB stopped. It and MySQL 8.4 both want port 3306.

## 2. Clone the repository

```bash
git clone <REPOSITORY_URL> tuitionhub-platform
cd tuitionhub-platform
git switch <SPRINT_BRANCH>      # e.g. sprint/day1-api-scaffold; never work on main
cd api
composer install
```

`composer install` only installs PHP packages into `api/vendor/`. It does not touch the database.

## 3. Create the databases and restricted users

Connect to MySQL as an administrator (for example `mysql -u root -p -h 127.0.0.1`) and run the SQL below with your own passwords:

```sql
CREATE DATABASE tuitionhub      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tuitionhub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Development app account: the tuitionhub database only.
CREATE USER 'tuitionhub_app'@'127.0.0.1' IDENTIFIED BY '<APP_DB_PASSWORD>';
GRANT ALL PRIVILEGES ON tuitionhub.* TO 'tuitionhub_app'@'127.0.0.1';

-- Test runner account: the tuitionhub_test database only.
CREATE USER 'tuitionhub_test_runner'@'127.0.0.1' IDENTIFIED BY '<TEST_DB_PASSWORD>';
GRANT ALL PRIVILEGES ON tuitionhub_test.* TO 'tuitionhub_test_runner'@'127.0.0.1';
```

Check the result. Each account should show `USAGE ON *.*` plus its one database, and nothing else:

```sql
SHOW GRANTS FOR 'tuitionhub_app'@'127.0.0.1';
SHOW GRANTS FOR 'tuitionhub_test_runner'@'127.0.0.1';
```

- The accounts are bound to host `127.0.0.1`. Always use `DB_HOST=127.0.0.1`. With `localhost`, MySQL may match a different account and refuse the login.
- Never put `root` in `api/.env` or `api/.env.testing`.

## 4. Development environment file: `api/.env`

From `api/`:

```bash
cp .env.example .env
```

Edit `api/.env` and set the password. The other database values already match step 3:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tuitionhub
DB_USERNAME=tuitionhub_app
DB_PASSWORD=<APP_DB_PASSWORD>
```

Then generate the development application key (this writes only to `api/.env`):

```bash
php artisan key:generate
```

Check the connection (read-only; it should show MySQL 8.4, database `tuitionhub`):

```bash
php artisan db:show
```

## 5. Test environment file: `api/.env.testing`

During tests (`APP_ENV=testing`), Laravel loads `api/.env.testing` **instead of** `api/.env`. The files are not merged, so `.env.testing` needs its own key and a complete database block.

Create `api/.env.testing` with exactly this content (your own test password):

```dotenv
APP_ENV=testing
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tuitionhub_test
DB_USERNAME=tuitionhub_test_runner
DB_PASSWORD=<TEST_DB_PASSWORD>
```

Generate the test-only key. This writes into `.env.testing` and does not change `api/.env`:

```bash
php artisan key:generate --env=testing
```

Confirm Git ignores both files. Both paths must be printed:

```bash
git check-ignore .env .env.testing
```

## 6. Run the tests

From `api/`:

```bash
php artisan test
```

Every test should pass. The connection tests check that you are on `tuitionhub_test`, in UTC (`+00:00`), as `tuitionhub_test_runner`.

If you see **`Refusing to run tests: …`**, the safety guard in `api/tests/TestCase.php` found the wrong environment, database, URL or username, and it stopped before touching any database. Read the message, fix `api/.env.testing`, and run `php artisan config:clear` in case a cached config is being used. Never weaken the guard to make tests pass.

## 7. Do not

- Run `composer run setup`, `php artisan migrate`, `migrate:fresh`, `db:seed` or `db:wipe` unless that task has been approved.
- Run `php artisan test --parallel`. It is not supported (`docs/DECISION_LOG.md` D-010).
- Run `php artisan config:cache` on your development machine.
- Commit `api/.env` or `api/.env.testing`, or share their contents.
- Use real student, parent or payment data anywhere. Demo and test data must be fictional.
