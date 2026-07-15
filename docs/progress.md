# Project Progress

> Single source of truth for **where we are** in the 11-day plan. Update at the end of every working session.
> Plan of record: [`11-day-roadmap.md`](11-day-roadmap.md). Decisions: [`decisions.md`](decisions.md). Issues: [`known-issues.md`](known-issues.md).

**Status legend:** `Not Started` · `In Progress` · `Blocked` · `Done`
**Definition of Done (per day):** feature usable in-app · tests written & passing · `composer test` green (Pint + PHPStan L7 + Pest) · RTL renders · existing auth untouched.

_Last updated: 2026-07-15 (Day 10)_

---

## Overview

| Day | Focus | Modules | Status | Notes |
|----:|-------|---------|--------|-------|
| 1 | Foundation: schema, models, seeders, RTL shell | M0, X | **Done** | 58 tests green; DB moved to MySQL; `RefreshDatabase` enabled |
| 2 | Admin shell, role-based auth, Settings | X, M1 | **Done** | 70 tests green; `/admin` gated + role redirect; Settings + working-hours editor |
| 3 | Service Catalog | M2 | **Done** | 94 tests green; categories + services CRUD, reorder, active filter |
| 4 | Staff profiles + service linking | M3 | **Done** | 108 tests green; staff CRUD + service linking; `providesAllServices` scope for availability |
| 5 | Staff schedules & time-off | M3 | **Done** | 124 tests green; weekly schedule editor + time-off; all availability inputs now exist |
| 6 | Availability Engine (the heart) | M4 | **Done** | 144 tests green; `AvailabilityService` + `/api/available-slots`; exhaustive boundary suite |
| 7 | Clients + before/after photos | M5 | **Done** | 176 tests green; clients CRUD + search, profile aggregates, before/after gallery, shared phone rule |
| 8 | Appointments core (booking action) | M6 | **Done** | 207 tests green; `BookAppointment` (lock + re-check + snapshots), status transitions, admin appointments CRUD/filters, `AppointmentPolicy` |
| 9 | Public booking wizard + self-service | M7 | **Done** | 223 tests green; `/book` wizard, confirmation + `.ics`, WhatsApp link, `/my-booking` self-cancel |
| 10 | Dashboard, calendar, reminders | M8 | **Done** | 243 tests green; `/admin` dashboard KPIs, day/week calendar + server-driven reschedule, WhatsApp reminders + logs |
| 11 | Reports, hardening, release polish | M9, X | Not Started | — |

**Progress:** 10 / 11 days complete.

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

### Day 6 — Availability Engine `Done`
- **Goal:** `AvailabilityService` implements §4 exactly and is proven by an exhaustive test suite; a debug/read endpoint returns real slots. No booking writes yet.
- **Deliverables:** given seeded data, the service and endpoint return correct available slots for specific-staff and "any staff" cases.
- **Checklist:**
  - [x] `AvailabilityService` in `app/Services/`: services → total duration D, staff (or "any"), date → available start times + suggested staff
  - [x] Full algorithm: weekday → salon hours → per-candidate schedule intersection → subtract time-off → subtract non-cancelled appointments → step-generate candidates → future+buffer filter → "any staff" resolution
  - [x] Overlap helper `intervalsOverlap()` (`A.start < B.end && B.start < A.end`; touching boundaries free)
  - [x] Internal read-only endpoint `GET /api/available-slots?service_ids=&staff_id=&date=` (public; backs Day 9 wizard)
  - [x] Pure service — no Livewire, fully unit-testable
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-12` — Implemented M4 (Availability Engine). New `App\Services\AvailabilityService` (constructor-injectable `stepMinutes`/`bufferMinutes`, defaults 15/60) implementing the §4 algorithm end to end, plus a static `intervalsOverlap()` half-open overlap helper (reused by Day 8's locked re-check). New invokable `App\Http\Controllers\Api\AvailableSlotsController` at route `api.available-slots` (public, JSON; accepts comma-separated or array `service_ids`). Candidate staff come from `Staff::active()->providesAllServices()` (Day 4 scope), ordered by id so "any-staff" resolution is deterministic (earliest-id free staff wins per time).
  - **Timezone handling (see [ADR-0012](decisions.md)):** all interval math runs in `settings.timezone`. Since `app.timezone` is UTC and Laravel does not convert datetimes on write, appointment datetimes are stored as naive salon-local wall clock; the engine matches them with `whereDate` and re-parses their raw `Y-m-d H:i:s` **as** salon-local rather than `setTimezone`-shifting them (the initial shift bug surfaced in the appointment-overlap and any-staff-fallback tests and was fixed here). Partial time-off is treated as an extra busy interval; full-day time-off (null times) skips the staff for that date.
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 144/144 (379 assertions)** (20 new: overlap helper, closed weekday, day-off, full/partial time-off, appointment overlap + adjacency, cancelled ignored, multi-service duration + over-window, any-staff resolution + busy fallback, buffer, window/step edges, unqualified/inactive staff, empty services, and 4 endpoint tests). Existing suites unchanged.
  - **Note:** the engine is read-only — no booking writes yet. Day 8's `BookAppointment` will re-run this check inside a locked transaction and must write appointment datetimes as salon-local wall clock to stay consistent with ADR-0012.

### Day 7 — Clients + photos `Done`
- **Goal:** Admin manages client records, cosmetic profile fields, and the before/after photo gallery; a phone rule is extracted for reuse by the public wizard.
- **Deliverables:** admin creates/edits clients, manages tags, and uploads categorized photos; profile aggregates (total spend, visits, last visit) render.
- **Checklist:**
  - [x] `clients` CRUD (Livewire) keyed on unique `phone`; list with search (name/phone), visit count, last visit
  - [x] Client profile page: cosmetic fields (skin_type, hair_type, notes, tags), appointment history, total spend (Σ price_snapshot of completed)
  - [x] `client_photos`: upload, classify before/after, optional link to a same-client appointment, note, taken_at; gallery view + remove
  - [x] Phone normalization/validation (+966/+971) extracted to `App\Rules\PhoneNumber` (reused on Day 9); unique phone counts soft-deleted rows (reuse-on-restore, ADR-0005)
  - [x] Seeder extended with a completed appointment + service snapshots + before/after photo pair
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-13` — Implemented M5 (Clients). New Livewire pages `App\Livewire\Admin\Clients` (list + search + CRUD modal) at `admin.clients.index` and `App\Livewire\Admin\ClientProfile` (cosmetic profile, appointment history, aggregates, before/after gallery + photo upload) at `admin.clients.show` (`/admin/clients/{client}`), reached via a **Clients** sidebar item and a **Profile** row link. Business logic in Actions `SaveClient` (tags parsed from a comma-separated field to a unique array) and `AddClientPhoto`; validation in a reusable trait `ClientValidationRules`. Extracted `App\Rules\PhoneNumber` — a `ValidationRule` enforcing canonical `+966`/`+971` form plus a static `normalize()` (strips separators, `00`→`+`); the component normalizes before the unique check so spacing variants can't create duplicates (see [ADR-0013](decisions.md)). `Client::scopeSearch()` (name/phone); the list decorates each row with `appointments_count` and a `last_visit_at` correlated subquery. Total spend sums `price_snapshot` of completed appointments only. Photos store on the `public` disk under `client-photos/`; the appointment-link select and its validation are scoped to the client's own appointments; `client_photos.appointment_id` is `set null` on appointment delete. `DemoSeeder` now seeds a completed appointment (with `appointment_services` snapshots) and a before/after photo pair so the profile aggregates render.
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 176/176 (437 assertions)** (32 new: client authz/CRUD, unique phone, phone-format + normalization, search, visit-count decorator; profile authz, photo upload+classify, same-client appointment link + cross-client rejection, `set null` on appointment delete, photo remove, completed-only total spend; plus the `PhoneNumber` rule suite). `migrate:fresh --seed` clean. Existing suites unchanged.
  - **Note:** client photos share the `storage:link` requirement as logos/staff photos ([KI-7](known-issues.md)). Appointment history renders read-only until Day 8 populates real bookings; the shared `PhoneNumber` rule is the dedupe contract for the Day 9 wizard.

### Day 8 — Appointments core `Done`
- **Goal:** Appointments are created safely (transaction + lock, re-checked availability, immutable price/duration snapshots) and managed by admin (manual creation, filtering, status transitions); conflicts are impossible under concurrency.
- **Deliverables:** admin books manual appointments with conflict protection; status changes and filters work; every appointment carries immutable price/duration snapshots.
- **Checklist:**
  - [x] `BookAppointment` action: transaction + staff-row `lockForUpdate` + authoritative availability re-check → writes `appointment` + `appointment_services` price/duration snapshots; `datetime_end` = start + ΣD; links/creates client; resolves "any staff"
  - [x] `ChangeAppointmentStatus` action with allowed-transition rules (on the `AppointmentStatus` enum) + logging; cancellation is a status change, never a delete
  - [x] `/admin/appointments` Livewire page: filters (status, date range, staff, client phone) + row actions (status change, notes edit, soft-delete)
  - [x] Manual creation flow (source=`manual`) reusing `BookAppointment` with live availability slots
  - [x] `AppointmentPolicy`: admin sees all; staff sees only their own (reused by the Day 10 staff calendar)
  - [x] Snapshots stay fixed after later service price/duration edits
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-13` — Implemented M6 (Appointments core). New Actions `App\Actions\Appointments\BookAppointment` (the single authoritative writer — `DB::transaction` → `Staff::lockForUpdate()` as the first read so the transaction's InnoDB read view forms *after* the lock, then re-runs `AvailabilityService::availableSlots()` and rejects with `SlotUnavailableException` unless the requested `H:i` is still offered; writes price/duration snapshots, computes `datetime_end`, resolves null "any" staff to the earliest free, finds-or-creates client by normalized phone) and `ChangeAppointmentStatus` (enum-driven transitions + logging). New `App\Exceptions\{SlotUnavailableException,InvalidStatusTransitionException}`, `App\Policies\AppointmentPolicy` (auto-discovered), `App\Concerns\AppointmentValidationRules`, and Livewire `App\Livewire\Admin\Appointments` + view at `admin.appointments.index` (filters, manual booking modal with live slots, per-row allowed-status dropdown, notes editor, soft-delete). `AppointmentStatus` gains `allowedTransitions`/`canTransitionTo`/`isTerminal`/`label`/`color`. Sidebar gains an **Appointments** link; `DemoSeeder` seeds two upcoming appointments with snapshots. See [ADR-0014](decisions.md) for the lock + re-check design.
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 207/207 (505 assertions)** (31 new: `BookAppointment` writes/rejections/any-staff/inline-client/snapshot-integrity/empty-guard; status action + enum rules; `AppointmentPolicy` ownership; component authz/manual-booking/taken-slot-error/status-change/notes/soft-delete/filters). `migrate:fresh --seed` clean. Existing suites unchanged.
  - **Note:** the availability lead-time buffer applies uniformly, so booking a manual appointment *inside* the buffer window is currently rejected (accepted limitation — a future admin override could bypass it for `manual` sources; see [ADR-0014](decisions.md)). `/admin/appointments` stays admin-gated; the `AppointmentPolicy` ownership rule is ready for Day 10's staff-facing calendar. Booking changes are logged via the `Log` facade (no dedicated audit table in the schema).

### Day 9 — Public booking wizard `Done`
- **Goal:** A guest can book online end-to-end on mobile, land on a confirmation with an `.ics` link, and later look up / cancel their booking by phone.
- **Deliverables:** public online booking works start to finish; confirmation + `.ics`; clients self-cancel within policy.
- **Checklist:**
  - [x] `/book` multi-step Livewire wizard: (1) services grouped by category, (2) staff or "any" (filtered to those providing all selected services), (3) date → live slots, (4) name + phone, (5) confirm
  - [x] Submit calls `BookAppointment` (source=online, status=pending) with the transactional locked re-check; slot-filled-between-selection-and-submit returns the guest to the time step
  - [x] `/book/confirmed/{appointment}` summary + salon WhatsApp `wa.me` link + `.ics` calendar download
  - [x] `.ics` generation (`BuildAppointmentIcs` + `BookingIcsController`): salon-local wall clock emitted as absolute UTC instants, escaped text, CRLF
  - [x] `/my-booking` phone lookup → upcoming active appointments → self-cancel within the lead-time window (default 2 h)
  - [x] Mobile-first RTL public layout; phone validation/dedupe reuses `App\Rules\PhoneNumber`
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-13` — Implemented M7 (Public Booking). New public root layout `layouts.public` (mobile-first, RTL, salon-branded header) and three guest Livewire components under `App\Livewire\Public\`: `BookingWizard` (5-step wizard at `/book`; orchestrates `AvailabilityService` for live slots and `BookAppointment` for the write — source=online/status=pending; drops a now-unqualified staff when services change; catches `SlotUnavailableException` and returns to the time step), `BookingConfirmation` (`/book/confirmed/{appointment}` — summary + `wa.me` deep link + `.ics` link), and `MyBooking` (`/my-booking` — phone-scoped lookup + lead-time-gated self-cancel via `ChangeAppointmentStatus`). New `App\Actions\Appointments\BuildAppointmentIcs` (RFC 5545 `VEVENT`, salon-local→UTC instants per [ADR-0012](decisions.md), escaped text, CRLF) served by `App\Http\Controllers\BookingIcsController` (`text/calendar`) at `/book/{appointment}/calendar.ics`. Routes registered in `routes/web.php` (public, outside `auth`). Guest dedupe reuses the shared `PhoneNumber` rule/normalization ([ADR-0013](decisions.md)); the public read/`.ics` pages are capability-by-id and self-service is phone-scoped (see [ADR-0015](decisions.md)).
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 223/223 (557 assertions)** (16 new: guest access to `/book` + `/my-booking`; wizard happy path incl. online/pending write, service link, client find-or-create, redirect; staff-step qualification filter; any-staff resolution on submit; drop-unqualified-staff; slot-filled re-check rejection with no double-book; invalid-phone step guard; confirmation renders publicly; `.ics` endpoint + builder UTC `DTSTART`/`DTEND` + escaped `LOCATION` + CRLF; my-booking upcoming-only listing; cancel outside/inside window + cross-client rejection). Existing suites unchanged.
  - **Note:** the `.ics`/confirmation pages authorize by appointment id (capability), and self-service authorizes by looked-up phone only — acceptable for this single-tenant, non-billing scope; a future OTP step could harden self-service (see [ADR-0015](decisions.md)). The cancellation lead time is a component constant (`MyBooking::CANCELLATION_LEAD_MINUTES`, default 120), a candidate for a salon setting later.

### Day 10 — Dashboard, calendar, reminders `Done`
- **Goal:** The daily operational cockpit — KPI dashboard, a day/week calendar with availability-re-checked rescheduling, and WhatsApp reminders.
- **Deliverables:** admin runs the day from dashboard + calendar; reschedules safely; sends logged WhatsApp reminders.
- **Checklist:**
  - [x] `/admin` dashboard cards: today's appointment count, expected revenue (Σ `price_snapshot` non-cancelled), no-shows, next-up; today's timeline list
  - [x] `/admin/calendar` day/week view; blocks colored by service (status badge); click to open; create from an empty slot (reuses `BookAppointment`)
  - [x] **Reschedule** with authoritative re-check: `RescheduleAppointment` re-runs `AvailabilityService` inside a locked transaction (excludes the moved appointment's own interval), preserving price/duration snapshots — drag-drop degrades to a server-driven slot picker ([ADR-0016](decisions.md))
  - [x] Reminders: `SendAppointmentReminder` builds a client `wa.me` link from `settings.reminder_template` variables and writes a `reminder_logs` row (row action on `/admin/appointments`)
  - [x] Staff-role calendar shows only their own appointments (via `AppointmentPolicy`; calendar sits behind `auth`, scoped in-component)
  - [x] Tests written & `composer test` green
- **Session log:**
  - `2026-07-15` — Implemented M8 (Admin Ops). New Livewire pages `App\Livewire\Admin\Dashboard` (KPI cards + today's timeline, salon-local "today", revenue/counts exclude cancelled) at `/admin` (replacing the Day 2 redirect-to-settings) and `App\Livewire\Admin\Calendar` (day/week views, service-colored blocks via a `calendar-block` partial, create-from-slot modal reusing `BookAppointment`, and a reschedule modal). New Actions `RescheduleAppointment` (locked transaction + `AvailabilityService` re-check with the new **exclude-self** parameter, preserves snapshots, optional staff move) and `SendAppointmentReminder` (logs `reminder_logs`, returns the client `wa.me` deep link with `{client}/{salon}/{date}/{time}` substituted). Extended `AvailabilityService::availableSlots()` with an optional `$excludeAppointmentId`. `Appointments` page gains a **Remind** row action + modal. Calendar route sits behind `auth` (not the `admin` gate) and authorizes `viewAny` in-component so staff-role users reach it scoped to their own `staff_id` (admins see all). Sidebar gains **Dashboard** + **Calendar** (admins) and a **My calendar** item (staff). `DemoSeeder` now seeds a today appointment + a sample reminder log. See [ADR-0016](decisions.md).
  - **Verification:** `composer test` green → Pint pass · PHPStan L7 0 errors · **Pest 243/243 (603 assertions)** (20 new: dashboard authz + today/revenue/no-show/next-up aggregates incl. cancelled-exclusion; calendar authz for admin/staff/guest, staff-only scoping, reschedule to free slot, conflict rejection + unchanged, create-from-calendar; reschedule action to free slot / into another appointment / nudge-within-own-interval / move-staff / snapshot-duration preserved; reminder action log + wa.me substitution + default-template fallback; component reminder). `migrate:fresh --seed` clean. Updated the Day 2 `/admin` redirect test to expect the dashboard. Existing suites unchanged.
  - **Note:** the calendar's "create from empty slot" is a per-column/day **+ button** (not pixel-drop) and reschedule is a slot picker, both server-driven per [ADR-0016](decisions.md) — no JS drag library. Reminders are logged but not sent server-side (no WhatsApp API in scope). Reschedule validates the new slot against live service durations while writing the snapshot duration; a booked service edited/soft-deleted afterward is an accepted edge case ([ADR-0016](decisions.md)).

### Day 11 — Reports & hardening `Not Started`
- **Session log:** _(none yet)_

---

## How to update this file
1. Set the day's **Status** in the Overview table (and the day-detail heading).
2. Tick checklist items as they land; add a dated line under **Session log**.
3. Bump **Progress** count and **Last updated** date.
4. Cross-link anything notable: new decision → [`decisions.md`](decisions.md); new bug/risk → [`known-issues.md`](known-issues.md); shipped change → [`changelog.md`](changelog.md).
