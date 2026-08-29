# migraine-map

Migraine Map is a migraine diary application.

The product documentation lives in [`product/`](product): the
[vision](product/vision.md), [problem statement](product/problem-statement.md),
[personas](product/personas.md) and [capabilities](product/capabilities.md).

## Stack

- Laravel 13 (PHP 8.3+) with the Vue starter kit (Inertia.js + Vue 3, Tailwind
  CSS, shadcn-vue) and Laravel Fortify for session authentication
- PostgreSQL
- Pest for testing, Pint for formatting, PHPStan (Larastan) for static analysis
- Vite (via vite-plus) for the frontend build

## Requirements

- PHP 8.3 or 8.4 with the `pdo_pgsql` extension
- Composer 2
- Node.js 22 and npm
- PostgreSQL 14+

## Getting started

### Database

`createdb` ships with the PostgreSQL client tools, so PostgreSQL must be
installed, running, and reachable, and you need a login role that is allowed to
create databases.

macOS (Homebrew) — the install creates a superuser role named after your macOS
user, so `createdb` works as-is:

```bash
brew install postgresql@16
brew services start postgresql@16
createdb migraine_map
createdb migraine_map_testing
```

Ubuntu/Debian — only the `postgres` role exists after install, so create a login
role first (this matches the credentials in `.env.example`):

```bash
sudo apt install postgresql postgresql-client
sudo service postgresql start
sudo -u postgres psql -c "CREATE ROLE migraine_map LOGIN CREATEDB PASSWORD 'password';"
sudo -u postgres createdb -O migraine_map migraine_map
sudo -u postgres createdb -O migraine_map migraine_map_testing
```

Both databases must be owned by the user in `DB_USERNAME`: the test suite drops
and recreates tables in `migraine_map_testing`, which fails with
`must be owner of table ...` if they were created by another role.

### Application

Install dependencies, create `.env`, generate an app key, migrate and build the
frontend:

```bash
composer setup
```

`composer setup` copies `.env.example` to `.env` if it does not exist. Set the
database credentials there before running it, or re-run `php artisan migrate`
after editing them:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=migraine_map
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Run the application:

```bash
composer dev
```

This serves the app at http://localhost:8000 alongside the Vite dev server,
queue worker and log viewer. Registration, sign-in and password reset are
provided by the starter kit out of the box.

## Testing

Tests run with Pest against the `migraine_map_testing` database (configured in
`phpunit.xml`), using the credentials from `.env`.

```bash
php artisan test
```

## Checks

```bash
composer lint        # Pint (fix)
composer lint:check  # Pint (check only)
composer types:check # PHPStan
composer ci:check    # Format, lint, type checks and the test suite (used in CI)
```
