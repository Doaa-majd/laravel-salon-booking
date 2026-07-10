# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> Add every user-facing or notable change under **[Unreleased]** as it lands. On a release, rename the section to a version + date and start a fresh **[Unreleased]**.
> Categories: `Added` · `Changed` · `Deprecated` · `Removed` · `Fixed` · `Security`.

---

## [Unreleased]

### Added
- Project documentation set: `11-day-roadmap.md`, `progress.md`, `backlog.md`, `decisions.md`, `known-issues.md`, `changelog.md`.
- **Day 1 — Foundation (M0):**
  - Database schema for the full salon domain: `settings`, `service_categories`, `services`, `staff`, `staff_services`, `staff_schedules`, `staff_time_off`, `clients`, `appointments`, `appointment_services`, `client_photos`, `reminder_logs` (foreign keys, unique constraints, indexes, and soft deletes per spec).
  - `role` (`admin`/`staff`) and nullable `staff_id` foreign key added to `users`.
  - Eloquent models for all domain tables with relationships, casts, and soft deletes; `User` gains `staff()` relation and `isAdmin()`/`isStaff()` helpers.
  - Backed PHP enums: `UserRole`, `Currency`, `AppointmentStatus`, `AppointmentSource`, `ClientPhotoType`, `ReminderChannel`.
  - Factories for every model, plus `UserFactory` `admin()`/`staff()` states, and an idempotent `DemoSeeder` (salon settings, admin user, categories/services, 2 staff with weekly schedules + time off, demo clients).
  - Global RTL/Arabic document shell (`dir="rtl"` on root layouts, `APP_LOCALE=ar`).
  - Foundation test suite (schema, relationships, constraints, factories, seeder, RTL render).

### Changed
- Database standardized on **MySQL** for development, testing, and production (previously SQLite for dev/tests). See [ADR-0001](decisions.md). Dev DB `salon_booking`, test DB `salon_booking_test`.
- Enabled `RefreshDatabase` globally for the Feature suite in `tests/Pest.php`.
- `composer types:check` now runs PHPStan with `--memory-limit=1G`.

### Fixed
- Feature test baseline that previously failed with `no such table: users` (the `RefreshDatabase` trait was commented out).

---

## Baseline — Livewire starter kit (2026-07-09)

### Added
- Laravel 13 · Livewire 4 · Flux UI · Fortify scaffold.
- Authentication: email/password, 2FA (TOTP), email verification, passkeys (WebAuthn).
- Settings pages: profile, appearance, security.
- Infrastructure migrations: users, cache, jobs, passkeys, two-factor.

_No salon domain features implemented yet — see [`backlog.md`](backlog.md) and [`progress.md`](progress.md)._

---

## How to update this file
1. During development, append entries under **[Unreleased]** in the right category.
2. Keep entries concise and user-facing; link to ADRs or issues where useful.
3. On release: change `## [Unreleased]` to `## [X.Y.Z] — YYYY-MM-DD`, then add a new empty **[Unreleased]** block at the top.
