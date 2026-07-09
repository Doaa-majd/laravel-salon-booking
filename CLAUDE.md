# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 13 · Livewire 4 · Flux UI · Laravel Fortify · Tailwind CSS v4 · Vite · Pest v4

## Commands

```bash
# First-time setup
composer setup              # install deps, copy .env, generate key, migrate, npm install + build

# Development (runs Laravel server + queue worker + Vite concurrently)
composer dev

# Tests (config:clear → lint check → PHPStan → Pest)
composer test

# Run tests directly (skip lint/types)
php artisan test
php artisan test --filter TestName    # single test by name

# Linting (Laravel Pint, preset: laravel)
composer lint               # apply fixes
composer lint:check         # check only (no writes)

# Static analysis (PHPStan level 7 via Larastan)
composer types:check

# Assets
npm run dev                 # Vite dev server
npm run build               # production build
```

## Architecture

### What's built vs. what's planned

The current codebase is the **Laravel Livewire starter kit scaffold** — auth flows are complete, but salon-specific features (booking, appointments, staff, clients) are not yet implemented. The full intended system is documented in [`docs/salon-booking-build-analysis.md`](docs/salon-booking-build-analysis.md) (written in Arabic).

### Authentication

Fortify handles all auth. Views are Livewire components wired in `app/Providers/FortifyServiceProvider.php`. Supports: email/password, 2FA (TOTP), email verification, passkeys (WebAuthn via `@laravel/passkeys`). Rate limiting: 5 req/min for login and 2FA, 10/min for passkeys.

### Routing

- `routes/web.php` — public routes (`/`, `/dashboard`) and `require`s settings routes
- `routes/settings.php` — `/settings/*` routes (profile, appearance, security), all behind `auth` middleware; security additionally requires `password.confirm`

### Livewire components

All interactive pages are Livewire components under `app/Livewire/`. Auth views live at `resources/views/livewire/auth/`. Settings pages follow the pattern: Livewire class in `app/Livewire/Settings/` + view in `resources/views/livewire/settings/`.

### Layouts

Two root layouts:
- `resources/views/layouts/app.blade.php` — authenticated app shell with sidebar (`layouts/app/sidebar.blade.php`) and header
- `resources/views/layouts/auth.blade.php` — auth shell with variants: `card`, `simple`, `split`

### Database

MySQL is the database for both development and production. Tests run against a dedicated MySQL test database (configured in `phpunit.xml`). Migrations live in `database/migrations/`.

### Planned salon schema (not yet migrated)

Key models to build per `docs/salon-booking-build-analysis.md`:
- `settings` (one row — salon config, working hours JSON, WhatsApp number)
- `service_categories`, `services` (duration, price, color)
- `staff`, `staff_services` (many-to-many), `staff_schedules` (weekly), `staff_time_off`
- `clients` (phone as unique identifier), `client_photos` (before/after)
- `appointments` + `appointment_services` (price/duration snapshot), `reminder_logs`
- `users.role` enum `admin|staff` + optional `staff_id` FK

Conflict detection for appointments is handled in application code (not DB constraints) using transactions with locks — see the availability algorithm in `docs/salon-booking-build-analysis.md` section 4.

### Frontend

Flux UI (`livewire/flux`) provides the component library — used directly in Blade templates as `<flux:*>` components. Tailwind CSS v4 (no config file; configured via CSS). Assets compiled by Vite with `laravel-vite-plugin`.

## Development Rules

Before implementing any feature:

1. Analyze existing code patterns first.
2. Follow Laravel conventions.
3. Keep Livewire components small.
4. Business logic should not live inside Livewire components.
5. Use Actions/Services for complex business logic.
6. Use Form Requests or validation classes where appropriate.
7. Do not introduce new packages without approval.
8. Do not modify existing auth flows unless required.

## Application Architecture

Use the following structure:

app/
 ├── Livewire/
 │    └── UI components only
 │
 ├── Actions/
 │    └── Single business operations
 │
 ├── Services/
 │    └── Complex domain logic
 │
 ├── Models/
 │
 └── Policies/

Livewire components should orchestrate actions, not contain business rules.

## Database Rules

- Always use foreign keys.
- Use soft deletes where historical data matters.
- Never delete appointments permanently.
- Appointment records must preserve price and duration snapshots.
- All booking changes must be logged.

## Booking Rules

When modifying appointment logic:

- Always check staff availability.
- Prevent overlapping appointments.
- Use database transactions for booking creation.
- Use locks when confirming availability.
- Never trust frontend availability checks.

## Avoid

Do not:

- Create unnecessary Livewire components.
- Add jQuery or Alpine unless required.
- Replace Livewire with JavaScript frameworks.
- Add packages without explaining why.
- Refactor unrelated files during feature work.

## Fortify Rules

- Do not modify Fortify authentication flow unless the task specifically requires it.
- Prefer extending through actions/events instead of editing vendor behavior.