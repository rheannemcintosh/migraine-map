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
- SQL Server 2022 locally (Azure SQL Database in production)
- Pest for testing, Pint for formatting, PHPStan (Larastan) for static analysis
- Vite (via vite-plus) for the frontend build

## Requirements

Local development runs entirely in Docker via [Laravel Sail](https://laravel.com/docs/sail) —
no host PHP, Composer, Node or database driver setup is needed.

- Docker Desktop (a recent version — 4.29+)
- On Apple Silicon: Docker's **"Use Rosetta for x86/amd64 emulation"** must be on
  (Settings → General; on by default with the Apple Virtualization framework in
  current versions). The SQL Server container is amd64-only and crashes under the
  QEMU fallback. Run `softwareupdate --install-rosetta` if Rosetta itself is missing.

## Getting started

```bash
cp .env.example .env

# Install PHP dependencies using a throwaway Composer container (no host PHP needed).
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html \
  laravelsail/php84-composer:latest composer install

# Install Node dependencies (needed before the container starts Vite).
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html \
  node:22 npm ci

# First run also builds the app image (compiles the SQL Server driver), starts
# SQL Server, and creates the app + test databases via the mssql-init service.
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The container runs `artisan serve` **and** the Vite dev server under supervisor, so
the app is fully working at http://localhost:8000 once `sail up` settles — no separate
`npm run dev` process to babysit. Registration, sign-in and password reset are provided
by the starter kit out of the box.

If you install or change Node dependencies later, restart the app container so Vite
picks them up: `./vendor/bin/sail restart laravel.test`.

Add a shell alias so you can type `sail` instead of `./vendor/bin/sail`:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

## Testing

Tests run with Pest against the `migraine_map_testing` database on the same
SQL Server container. No extra setup is needed:

```bash
./vendor/bin/sail artisan test
```

## Checks

```bash
./vendor/bin/sail composer lint        # Pint (fix)
./vendor/bin/sail composer lint:check  # Pint (check only)
./vendor/bin/sail composer types:check # PHPStan
./vendor/bin/sail composer ci:check    # Format, lint, type checks and the test suite (used in CI)
```
