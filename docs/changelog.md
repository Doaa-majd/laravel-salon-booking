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
- **Day 5 — Staff schedules & time-off (M3, part 2):**
  - **Staff schedule** page (Livewire) at `/admin/staff/{staff}/schedule`, reached via a **Schedule** action on each staff row.
  - **Weekly working-hours editor**: a 7-day grid (per weekday start/end + day-off toggle) upserted on the unique `(staff_id, weekday)` key, so saving is idempotent and one row per weekday is guaranteed. Open days validate `H:i` times with the end strictly after the start; days off skip the check.
  - **Time-off management**: add specific-date leaves (full-day when times are empty, partial-day when a window is given), list them, and remove them. Validation: date not in the past, both or neither time bound, end after start (see [ADR-0011](decisions.md)).
  - Business logic in Actions (`SaveStaffSchedule` upserts the week inside a transaction; `AddStaffTimeOff` creates a leave); validation in a reusable trait (`StaffScheduleValidationRules`).
  - `DemoSeeder` now also seeds a partial-day leave (alongside the existing weekly schedules + full-day leave) to exercise the Day 6 availability subtraction step.
  - Schedules/time-off are stored as salon-local wall-clock times, finalizing all inputs to the availability engine ([ADR-0011](decisions.md)).
  - Tests for admin authorization, schedule load/defaults, save + idempotent re-save, end-after-start (and day-off skip), full-day and partial-day time-off creation, past-date rejection, single-bound rejection, and staff-scoped removal.
- **Day 4 — Staff (M3, part 1):**
  - **Staff** admin page (Livewire): create/edit/soft-delete with name, phone, photo upload, bio, commission percentage, and active toggle. List shows service counts and an active/inactive status filter.
  - **Service linking**: per-staff `staff_services` many-to-many managed from the staff form (checkbox group of active services), synced on save.
  - `Staff::scopeProvidesAllServices([...])` — returns only staff who provide *every* listed service (candidate filter for the Day 6 availability engine); soft-deleted services are excluded via the relation scope.
  - `Staff::user()` (hasOne) exposes the optional `users` ↔ `staff` account link at the model level.
  - Business logic in an Action (`SaveStaff`); validation in a reusable trait (`StaffValidationRules`) enforcing required name, commission 0–100, image photo, and existing service ids.
  - Sidebar Administration group gains a **Staff** link (`admin.staff.index`).
  - Tests for admin authorization, staff CRUD, validation bounds (missing name, negative/over-100 commission), soft-delete, status filter, `providesAllServices` (incl. soft-delete exclusion), pivot cascade on service force-delete, and the user↔staff link.
- **Day 3 — Service Catalog (M2):**
  - **Service categories** admin page (Livewire): create/edit/delete, active toggle, and reordering. Reordering is server-driven (move up/down) and persists `sort_order` — no Alpine/JS framework (see [ADR-0009](decisions.md)). Deleting a category keeps its services and leaves them uncategorised (`category_id` set to null).
  - **Services** admin page (Livewire): create/edit/soft-delete with category, name, duration, price, description, color picker, and active toggle. List is grouped by category with an active/inactive status filter.
  - Business logic lives in Actions (`SaveServiceCategory`, `SaveService`, `ReorderServiceCategories`); validation in reusable traits (`ServiceCategoryValidationRules`, `ServiceValidationRules`) enforcing duration ≥ 1, price ≥ 0, and hex colors.
  - `ServiceCategory` gains `active` and `ordered` query scopes; sidebar Administration group gains **Categories** and **Services** links.
  - Tests for admin authorization, category/service CRUD, validation failures (missing name, non-positive duration, negative price, bad hex), reorder persistence, category-delete null-out, soft-delete, and active scope/filter.
- **Day 2 — Admin shell, roles, Settings (M1, X):**
  - Admin-only area at `/admin/*` guarded by an `EnsureUserIsAdmin` route middleware (`admin` alias); `/admin` redirects to `/admin/settings` until the Day 10 dashboard.
  - Role-based post-login redirect: a custom Fortify `LoginResponse` sends admins to the admin area and everyone else to the configured home (extends Fortify only — see [ADR-0008](decisions.md)).
  - Role-aware sidebar: an **Administration** navigation group is shown to admins only.
  - **Salon settings** page (Livewire, admin): salon name, logo upload, primary color, currency, timezone, week start, phone, WhatsApp number, address, reminder template.
  - **Working-hours editor**: 7-day open/close/closed grid persisted to `settings.working_hours` JSON via an `UpdateSalonSettings` action, with validation (valid `H:i` times, closing after opening on open days).
  - `Setting::current()` singleton accessor so later modules read salon config without ad-hoc queries.
  - Tests for admin authorization (view/403/redirect), settings update, working-hours JSON round-trip, validation failures, logo upload, and role-based login redirects.
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
