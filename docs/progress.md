# Project Progress

> Single source of truth for **where we are** in the 11-day plan. Update at the end of every working session.
> Plan of record: [`11-day-roadmap.md`](11-day-roadmap.md). Decisions: [`decisions.md`](decisions.md). Issues: [`known-issues.md`](known-issues.md).

**Status legend:** `Not Started` · `In Progress` · `Blocked` · `Done`
**Definition of Done (per day):** feature usable in-app · tests written & passing · `composer test` green (Pint + PHPStan L7 + Pest) · RTL renders · existing auth untouched.

_Last updated: 2026-07-10_

---

## Overview

| Day | Focus | Modules | Status | Notes |
|----:|-------|---------|--------|-------|
| 1 | Foundation: schema, models, seeders, RTL shell | M0, X | **Done** | 58 tests green; DB moved to MySQL; `RefreshDatabase` enabled |
| 2 | Admin shell, role-based auth, Settings | X, M1 | **Done** | 70 tests green; `/admin` gated + role redirect; Settings + working-hours editor |
| 3 | Service Catalog | M2 | Not Started | — |
| 4 | Staff profiles + service linking | M3 | Not Started | — |
| 5 | Staff schedules & time-off | M3 | Not Started | — |
| 6 | Availability Engine (the heart) | M4 | Not Started | — |
| 7 | Clients + before/after photos | M5 | Not Started | — |
| 8 | Appointments core (booking action) | M6 | Not Started | — |
| 9 | Public booking wizard + self-service | M7 | Not Started | — |
| 10 | Dashboard, calendar, reminders | M8 | Not Started | — |
| 11 | Reports, hardening, release polish | M9, X | Not Started | — |

**Progress:** 2 / 11 days complete.

---

## Day detail

Each day is checked off against its roadmap deliverables. Copy the block format when adding progress notes.

### Day 1 — Foundation `Done`
- **Goal:** Full schema, models, relationships, factories, demo seeder; app boots in RTL with roles wired.
- **Deliverables:** `migrate:fresh --seed` populates demo DB · models resolvable · RTL renders · auth untouched.
- **Checklist:**
  - [x] Migrations for all 12 domain tables
  - [x] `role` + `staff_id` added to `users`
  - [x] FKs, unique constraints, indexes per spec
  - [x] Models + relationships + casts + soft-deletes
  - [x] Factories for every model
  - [x] `DemoSeeder`
  - [x] Global RTL (`dir="rtl"` / `lang="ar"`)
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-09` — Implemented full M0 foundation. 12 domain migrations + `users.role`/`staff_id` (FK-safe order), 11 models with relationship generics/casts/soft-deletes, 6 backed enums (`UserRole`, `Currency`, `AppointmentStatus`, `AppointmentSource`, `ClientPhotoType`, `ReminderChannel`), 11 factories + `UserFactory` states (`admin()`/`staff()`), idempotent `DemoSeeder`, and global RTL shell (`dir="rtl"` + `APP_LOCALE=ar`).
  - **DB infra:** switched dev + test to MySQL 8 (per ADR-0001). Dev DB `salon_booking`, test DB `salon_booking_test` (utf8mb4). Updated `.env`/`.env.example`/`phpunit.xml`.
  - **Test infra:** enabled `RefreshDatabase` in `tests/Pest.php` — this also fixed the pre-existing broken baseline (auth suite was erroring with `no such table: users`).
  - **Tooling:** added `--memory-limit=1G` to `composer types:check` (PHPStan OOM'd at the env's 128M default).
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 58/58 (153 assertions)**. `migrate:fresh --seed` clean. Home route renders `200` with `dir="rtl"`/`lang="ar"` (regression test added).

### Day 2 — Admin shell, roles, Settings `Done`
- **Goal:** Admins log in, route by role, edit all salon settings incl. per-day working hours.
- **Deliverables:** admin logs in and lands by role; `/admin/*` gated to admins; salon settings + per-day working hours save and re-render; existing auth untouched.
- **Checklist:**
  - [x] `admin` route middleware (`EnsureUserIsAdmin`) + `/admin/*` group behind `auth`
  - [x] Role-based post-login redirect via custom Fortify `LoginResponse` (admin → `admin.dashboard`, else → `fortify.home`)
  - [x] Role-aware sidebar nav (Administration group visible to admins only)
  - [x] `Settings` Livewire page (name, logo, primary color, currency, timezone, week_start, phone, whatsapp, address, reminder template)
  - [x] 7-day working-hours editor persisted to `settings.working_hours` JSON via `UpdateSalonSettings` action
  - [x] `Setting::current()` singleton accessor (guards zero/multiple rows)
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-10` — Implemented M1 (Settings) + X (admin gating/roles). New: `EnsureUserIsAdmin` middleware (`admin` alias), `routes/admin.php` (`/admin` → redirect to settings, `/admin/settings` Livewire page), `App\Http\Responses\LoginResponse` bound in `FortifyServiceProvider::register()` for role-based redirect (extends Fortify per ADR-0003/0008), `App\Livewire\Admin\Settings` + view, `App\Actions\Settings\UpdateSalonSettings`, `App\Concerns\SalonSettingsValidationRules`, and `Setting::current()`/`Setting::defaultWorkingHours()`. Sidebar gains a role-aware Administration group.
  - **Gotchas handled:** avoided Livewire's reserved `hydrate*` hook name (renamed the working-hours mapper to `mapWorkingHours`); made `Setting::current()` robust to MySQL not rolling back auto-increment between transactional tests (returns lowest-id row / creates one, rather than keying on `id = 1`). Working-hours cross-field rule (close > open) enforced for open days.
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 70/70 (194 assertions)** (12 new: 10 settings/accessor + 2 role-redirect). Existing auth suite unchanged.
  - **Note:** logo uploads store on the `public` disk — run `php artisan storage:link` once for uploaded logos to be web-visible (see [KI-7](known-issues.md)).

### Day 3 — Service Catalog `Not Started`
- **Session log:** _(none yet)_

### Day 4 — Staff profiles + service linking `Not Started`
- **Session log:** _(none yet)_

### Day 5 — Staff schedules & time-off `Not Started`
- **Session log:** _(none yet)_

### Day 6 — Availability Engine `Not Started`
- **Session log:** _(none yet)_

### Day 7 — Clients + photos `Not Started`
- **Session log:** _(none yet)_

### Day 8 — Appointments core `Not Started`
- **Session log:** _(none yet)_

### Day 9 — Public booking wizard `Not Started`
- **Session log:** _(none yet)_

### Day 10 — Dashboard, calendar, reminders `Not Started`
- **Session log:** _(none yet)_

### Day 11 — Reports & hardening `Not Started`
- **Session log:** _(none yet)_

---

## How to update this file
1. Set the day's **Status** in the Overview table (and the day-detail heading).
2. Tick checklist items as they land; add a dated line under **Session log**.
3. Bump **Progress** count and **Last updated** date.
4. Cross-link anything notable: new decision → [`decisions.md`](decisions.md); new bug/risk → [`known-issues.md`](known-issues.md); shipped change → [`changelog.md`](changelog.md).
