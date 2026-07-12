# Project Progress

> Single source of truth for **where we are** in the 11-day plan. Update at the end of every working session.
> Plan of record: [`11-day-roadmap.md`](11-day-roadmap.md). Decisions: [`decisions.md`](decisions.md). Issues: [`known-issues.md`](known-issues.md).

**Status legend:** `Not Started` · `In Progress` · `Blocked` · `Done`
**Definition of Done (per day):** feature usable in-app · tests written & passing · `composer test` green (Pint + PHPStan L7 + Pest) · RTL renders · existing auth untouched.

_Last updated: 2026-07-12 (Day 5)_

---

## Overview

| Day | Focus | Modules | Status | Notes |
|----:|-------|---------|--------|-------|
| 1 | Foundation: schema, models, seeders, RTL shell | M0, X | **Done** | 58 tests green; DB moved to MySQL; `RefreshDatabase` enabled |
| 2 | Admin shell, role-based auth, Settings | X, M1 | **Done** | 70 tests green; `/admin` gated + role redirect; Settings + working-hours editor |
| 3 | Service Catalog | M2 | **Done** | 94 tests green; categories + services CRUD, reorder, active filter |
| 4 | Staff profiles + service linking | M3 | **Done** | 108 tests green; staff CRUD + service linking; `providesAllServices` scope for availability |
| 5 | Staff schedules & time-off | M3 | **Done** | 124 tests green; weekly schedule editor + time-off; all availability inputs now exist |
| 6 | Availability Engine (the heart) | M4 | Not Started | — |
| 7 | Clients + before/after photos | M5 | Not Started | — |
| 8 | Appointments core (booking action) | M6 | Not Started | — |
| 9 | Public booking wizard + self-service | M7 | Not Started | — |
| 10 | Dashboard, calendar, reminders | M8 | Not Started | — |
| 11 | Reports, hardening, release polish | M9, X | Not Started | — |

**Progress:** 5 / 11 days complete.

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

### Day 3 — Service Catalog `Done`
- **Goal:** Admin fully manages service categories and services (name, duration, price, color, active, ordering) — the first input source for availability/booking.
- **Deliverables:** admin creates/edits/reorders categories and services; only active services will later surface in booking.
- **Checklist:**
  - [x] `service-categories` CRUD (Livewire) with active toggle
  - [x] Category reordering persisting `sort_order` (server-driven move up/down — see [ADR-0009](decisions.md))
  - [x] `services` CRUD (Livewire): category, name, duration, price, description, color, is_active
  - [x] Validation classes (`ServiceValidationRules`, `ServiceCategoryValidationRules`): duration ≥ 1, price ≥ 0, hex color
  - [x] Services list grouped by category with active/inactive filter
  - [x] Soft-delete handling (services soft-deleted; category delete leaves services with null category)
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-11` — Implemented M2 (Service Catalog). New Livewire pages `App\Livewire\Admin\ServiceCategories` and `App\Livewire\Admin\Services` (thin — orchestrate actions only), backed by Actions `SaveServiceCategory`, `SaveService`, `ReorderServiceCategories`, and validation traits `ServiceCategoryValidationRules` / `ServiceValidationRules`. Added `scopeActive`/`scopeOrdered` to `ServiceCategory`. Routes `admin.service-categories.index` + `admin.services.index`; sidebar Administration group gains Categories + Services items. Forms use Flux modals; category order via server-driven move up/down (no Alpine, per ADR-0009).
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 94/94 (251 assertions)** (24 new: 12 categories + 12 services). Existing auth/settings suites unchanged.

### Day 4 — Staff profiles + service linking `Done`
- **Goal:** Admin manages staff and assigns which services each performs (`staff_services`), gating who can be booked for a given service.
- **Deliverables:** admin manages staff and their service offerings; the "provides all selected services" query is available and tested.
- **Checklist:**
  - [x] `staff` CRUD (Livewire): name, phone, photo upload, bio, commission_percent, is_active
  - [x] `staff_services` many-to-many management (attach/detach services per staff, synced on save)
  - [x] Active/inactive status filter on the staff list
  - [x] Optional `users` ↔ `staff` account link (`Staff::user()` hasOne; `User::staff()` already present)
  - [x] `Staff::scopeProvidesAllServices([...])` — candidate filter for availability (soft-deleted services excluded)
  - [x] Validation class (`StaffValidationRules`): name required, commission 0–100, image photo, services exist
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-12` — Implemented M3 part 1 (Staff profiles + service linking). New Livewire page `App\Livewire\Admin\Staff` (thin — orchestrates the action; model aliased as `StaffModel` to avoid the class-name clash), backed by `App\Actions\Staff\SaveStaff` (fills + `services()->sync()`) and validation trait `StaffValidationRules`. `Staff` model gains `user()` hasOne and a `providesAllServices` scope (per-service `whereHas`, so it respects the `Service` soft-delete scope — see [ADR-0010](decisions.md)). Route `admin.staff.index`; sidebar Administration group gains a **Staff** item. Photo uploads reuse the Day 2 `public` disk pattern.
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 108/108 (284 assertions)** (14 new: authz, CRUD, validation bounds, soft-delete, filter, `providesAllServices` incl. soft-delete exclusion, pivot cascade on force-delete, user link). Existing suites unchanged.
  - **Note:** staff photos store on the `public` disk under `staff/` — same `storage:link` requirement as logos ([KI-7](known-issues.md)). A full staff-user account-creation UI is out of scope for Day 4; the link is exposed at the model level (`staff_id` FK + relations).

### Day 5 — Staff schedules & time-off `Done`
- **Goal:** Admin sets each staff member's weekly working hours and specific-date leaves. After today, **all inputs to the availability algorithm exist.**
- **Deliverables:** admin defines weekly availability and leaves per staff; demo data supports realistic slot generation.
- **Checklist:**
  - [x] `staff_schedules` weekly editor: 7-day grid (per weekday start/end + `is_off`), unique per `(staff, weekday)` via upsert
  - [x] `staff_time_off` management: date, optional start/end (empty = full day), reason; list/add/remove
  - [x] Validation: open-day end > start; time-off both-or-neither time bounds + end > start; date not in the past
  - [x] Weekday grid structurally prevents overlapping/duplicate schedule rows
  - [x] Seeder extended with a sample partial-day time-off (weekly schedules + full-day leave already seeded)
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-12` — Implemented M3 part 2 (schedules & time-off). New Livewire page `App\Livewire\Admin\StaffSchedule` (thin — orchestrates actions; `Staff` aliased as `StaffModel`) at route `admin.staff.schedule` (`/admin/staff/{staff}/schedule`), reached via a **Schedule** button on the staff index. Backed by Actions `SaveStaffSchedule` (transactional `updateOrCreate` per weekday on the unique key) and `AddStaffTimeOff`, with validation trait `StaffScheduleValidationRules` (closure rules for end-after-start on both forms, `required_with` for partial-day bounds, `after_or_equal:today` for the leave date). `DemoSeeder` gains a partial-day leave. Established the schedule timezone convention — salon-local wall-clock times; null time-off times = full day (see [ADR-0011](decisions.md)).
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 124/124 (334 assertions)** (16 new: authz view/403/redirect, schedule load+defaults, save, idempotent re-save, end-after-start + day-off skip, full/partial time-off, past-date reject, single-bound reject, remove + staff-scoped remove). `migrate:fresh --seed` clean. Existing suites unchanged.
  - **Note:** all availability-engine inputs (salon hours, staff schedules, time-off, appointments) now exist — Day 6 can build `AvailabilityService`.

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
