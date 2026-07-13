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
- **Day 8 — Appointments core (M6):**
  - `App\Actions\Appointments\BookAppointment` — the single authoritative booking writer. Opens a transaction, locks the target staff row (`lockForUpdate`) to serialize concurrent bookings, re-runs the availability engine as the authoritative re-check (never trusting a frontend-selected slot), then writes the `appointment` plus per-service `appointment_services` **price/duration snapshots** ([ADR-0006](decisions.md)). Computes `datetime_end` = start + ΣD and the total `price_snapshot`; resolves a null `staff_id` ("any") to the earliest-id free staff for the requested time; finds-or-creates the client by normalized phone (or takes an existing `client_id`). Datetimes are written as salon-local wall clock ([ADR-0012](decisions.md)). A slot filled between selection and submit is rejected with `SlotUnavailableException` (see [ADR-0014](decisions.md)).
  - `App\Actions\Appointments\ChangeAppointmentStatus` — moves an appointment through its allowed lifecycle (transition rules live on the `AppointmentStatus` enum: pending → confirmed/completed/cancelled/no-show; confirmed → completed/cancelled/no-show; completed/cancelled/no-show terminal). Illegal transitions throw `InvalidStatusTransitionException`; every change is logged. Cancellation is a status change, never a delete.
  - **Appointments** admin page (Livewire) at `/admin/appointments`: filter by status, staff, date range, and client phone; manual booking modal (source=`manual`) reusing `BookAppointment` with live availability slot generation; per-row status transitions (only allowed next-states offered), notes editor, and soft-delete (never hard-deleted). Sidebar Administration group gains an **Appointments** link.
  - `App\Policies\AppointmentPolicy` — admins manage every appointment; a staff-role user manages only appointments assigned to their linked staff profile (the ownership rule reused by the Day 10 staff calendar).
  - `AppointmentStatus` enum gains transition rules (`allowedTransitions`/`canTransitionTo`/`isTerminal`) plus display `label()`/`color()` helpers. `DemoSeeder` now seeds two upcoming appointments (confirmed + pending) with service snapshots so the list renders out of the box.
  - Tests (31): `BookAppointment` snapshot + computed-end writes, taken-slot / filled-slot / out-of-window rejection, adjacent-slot validity, any-staff resolution, inline-client find-or-create, snapshot integrity after later price change, empty-services guard; status-transition action + enum rules; `AppointmentPolicy` admin-vs-staff ownership; component authorization, manual booking, friendly taken-slot error, status change (legal + blocked illegal), notes edit, soft-delete, and status/phone filters.
- **Day 7 — Clients (M5):**
  - **Clients** admin page (Livewire): create/edit/soft-delete clients (name, unique phone, email, birthdate, skin/hair type, notes, tags). List supports live search by name or phone and shows each client's visit count and last-visit date.
  - **Client profile** page (Livewire) at `/admin/clients/{client}`: cosmetic profile fields, tags, read-only appointment history (with staff + service snapshots), and aggregate cards — **total spend** (Σ `price_snapshot` of *completed* appointments), visits, and last visit.
  - **Before/after gallery**: upload a photo, classify it `before`/`after`, optionally link it to one of the client's own appointments, add a note and taken-at time; grid gallery with per-photo removal. Photos store on the `public` disk under `client-photos/`.
  - `App\Rules\PhoneNumber` — a reusable validation rule enforcing canonical Saudi (`+966`) / Emirati (`+971`) phone form, plus a static `normalize()` (strips spaces/dashes/parentheses, converts a leading `00` to `+`). The client form normalizes before the unique-phone check so spacing variants can't create duplicates; the same rule is the client-dedupe contract for the Day 9 booking wizard (see [ADR-0013](decisions.md)).
  - Business logic in Actions (`SaveClient`, `AddClientPhoto`); validation in a reusable trait (`ClientValidationRules`). `Client::scopeSearch()` added; the unique-phone rule counts soft-deleted rows (reuse-on-restore, [ADR-0005](decisions.md)).
  - `DemoSeeder` now seeds a completed appointment (with `appointment_services` price/duration snapshots) and a before/after photo pair so the profile aggregates render out of the box. Sidebar Administration group gains a **Clients** link.
  - Tests for client authorization/CRUD, unique-phone enforcement, phone-format validation + normalization, search, and the visit-count decorator; profile authorization, photo upload + classification, same-client appointment linking (and cross-client rejection), `set null` on appointment delete, photo removal, and completed-only total-spend aggregation; plus a dedicated `PhoneNumber` rule suite.
- **Day 6 — Availability Engine (M4):**
  - `App\Services\AvailabilityService` — the booking engine's core. Given a set of services (summed to a total duration D), an optional specific staff member (or "any"), and a date, it returns the bookable start times with a suggested staff member for each, implementing the §4 algorithm: weekday → salon working hours → intersect each candidate staff's weekly schedule → subtract time-off → subtract non-cancelled appointments → step-generate candidates (default 15-min step) → drop anything within the lead-time buffer (default 60 min) → resolve "any staff" to the earliest-id free staff per time.
  - `AvailabilityService::intervalsOverlap()` — the half-open overlap rule `A.start < B.end && B.start < A.end`; touching boundaries (`end == start`) do not conflict, so a slot ending exactly when another begins stays valid.
  - **Internal read-only endpoint** `GET /api/available-slots?service_ids=&staff_id=&date=` (public — backs the Day 9 booking wizard) returning the slots as JSON; `service_ids` accepts a comma-separated list or an array.
  - All interval math runs in the salon timezone; appointment datetimes (stored as naive salon-local wall clock) are matched by date and re-labelled into that timezone, avoiding the UTC/salon offset shift (see [ADR-0012](decisions.md)). The service performs no writes and holds no Livewire state.
  - The deepest test suite so far (20 cases): closed weekday, staff day-off, full-day and partial time-off subtraction, appointment overlap with adjacent-slot validity, cancelled appointments ignored, multi-service duration summing, duration exceeding the window, "any-staff" resolution and busy fallback, buffer filtering, window/step edge boundaries, unqualified/inactive staff exclusion, empty service list, plus endpoint JSON + validation.
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
