# migraine-map

## Description

Migraine Map is a migraine diary application.

## Product

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

Create the application and test databases, owned by the role you will use in
`.env`:

```bash
createdb migraine_map
createdb migraine_map_testing
```

### Application

Install dependencies, create `.env`, generate an app key, migrate and build the
frontend:

```bash
composer setup
```

`composer setup` copies `.env.example` to `.env`. If your database credentials
differ from the defaults, update `DB_USERNAME` and `DB_PASSWORD` in `.env` and
re-run `php artisan migrate`.

Run the application:

```bash
composer dev
```

This serves the app at http://localhost:8000 alongside the Vite dev server,
queue worker and log viewer. Registration, sign-in and password reset are
provided by the starter kit out of the box.

## Testing

Tests run with Pest against the `migraine_map_testing` database, reusing the
credentials from `.env`. No extra setup is needed:

```bash
php artisan test
```

To use different credentials for tests, copy `.env` and edit the `DB_*` values —
Laravel loads `.env.testing` instead of `.env` when running tests:

```bash
cp .env .env.testing
```

## Checks

```bash
composer lint        # Pint (fix)
composer lint:check  # Pint (check only)
composer types:check # PHPStan
composer ci:check    # Format, lint, type checks and the test suite (used in CI)
```
