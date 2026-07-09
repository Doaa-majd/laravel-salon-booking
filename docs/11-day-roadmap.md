# 11-Day Development Roadmap — Salon Booking System

> Prepared as Tech Lead. Based on [`salon-booking-build-analysis.md`](salon-booking-build-analysis.md) and the current codebase state.
> **No application code is written in this document.** It is the execution plan only.
> Stack: Laravel 13 · Livewire 4 · Flux UI · Fortify · Tailwind v4 · Pest v4. Single-tenant, RTL/Arabic, no billing.

---

## 1. System Analysis

### 1.1 Starting point (verified against the repo)

- The repo is the **Laravel Livewire starter kit**. Auth (email/password, 2FA, email verification, passkeys) is **complete and must not be broken**.
- Only infrastructure migrations exist: `users`, `cache`, `jobs`, `passkeys`, `two_factor`. **Zero salon domain tables/models exist.**
- `User` has **no `role` and no `staff_id`** yet — both are required by the doc (§users table).
- Livewire 4 conventions in use: routes via `Route::livewire(...)`, models use attribute-based `#[Fillable]`/`#[Hidden]`, settings pattern = Livewire class in `app/Livewire/...` + view in `resources/views/livewire/...`.
- MySQL is the database for development and production; tests run against a dedicated MySQL test database. Pest is the test runner. `composer test` = config:clear → Pint check → PHPStan (level 7) → Pest. **Every day's work must pass `composer test`.**

### 1.2 Module inventory

| # | Module | Core responsibility |
|---|--------|---------------------|
| M0 | **Foundation** | Migrations, models, relationships, factories, seeders, RTL shell, `role`/`staff_id` on users |
| M1 | **Settings** | Single-row salon config, working-hours JSON editor |
| M2 | **Service Catalog** | `service_categories` + `services` CRUD (duration, price, color, sort) |
| M3 | **Staff** | `staff` CRUD + `staff_services` (m2m) + `staff_schedules` (weekly) + `staff_time_off` |
| M4 | **Availability Engine** | `AvailabilityService` — the algorithm in §4 (the heart of the system) |
| M5 | **Clients** | `clients` CRUD, profile, cosmetic fields, `client_photos` before/after |
| M6 | **Appointments** | `appointments` + `appointment_services` snapshots, `BookAppointment` action (transaction + lock), status transitions |
| M7 | **Public Booking** | `/book` wizard, confirmation + `.ics`, `/my-booking` self-cancel |
| M8 | **Admin Ops** | Dashboard KPIs, calendar (day/week + drag-drop reschedule), reminders (wa.me + `reminder_logs`) |
| M9 | **Reports** | Top services, staff performance, commission, no-shows |
| X | **Cross-cutting** | RTL/Arabic, roles & policies, theming, mobile-first |

### 1.3 Dependency graph

```
M0 Foundation
 ├─> M1 Settings ─────────────┐
 ├─> M2 Service Catalog ──────┤
 ├─> M3 Staff (sched+timeoff)─┼─> M4 Availability Engine ─> M6 Appointments ─┬─> M7 Public Booking
 ├─> M5 Clients ──────────────┘                                              ├─> M8 Admin Ops (calendar/reminders/dashboard)
 └─> X Roles/Auth ────────────────────────────────────────────────────────> └─> M9 Reports
```

**Critical path:** `M0 → M3 → M4 → M6 → M7/M8`. The availability engine (M4) cannot be built correctly until settings hours, staff schedules, and time-off exist (M1/M3), so those are sequenced first. M4 gets a dedicated day because it is the highest-risk component and the doc flags it as "the hardest part."

### 1.4 Chosen build order & rationale

1. **Data before behavior.** Build the full schema + seeders on day 1 so every later module has real data (and factories for tests) instead of stubs.
2. **Inputs before the engine.** Settings → Services → Staff (incl. schedules/time-off) must land before Availability, because the algorithm intersects all of them.
3. **Engine before booking.** `BookAppointment` re-runs availability inside a locked transaction; it can't exist without M4.
4. **Admin data-entry before public booking.** The public `/book` wizard is only meaningful once services/staff/schedules are manageable and seeded — so admin CRUDs precede the public UI.
5. **Ops & reports last.** Calendar, reminders, dashboard, and reports are read/aggregate layers over appointments.

> **Working-app guarantee:** every day is merged in a state where `composer test` is green and the app boots. Early days keep the existing auth/dashboard reachable; each subsequent day adds a fully usable screen or a tested service.

---

## Day 1 — Foundation: schema, models, seeders, RTL shell

**Goal:** The entire database schema, Eloquent models with relationships, factories, and a demo seeder exist and migrate cleanly; the app boots in RTL with roles wired. No feature screens yet, but the data backbone is done.

**Modules:** M0 (Foundation), X (roles/RTL).

**Tasks:**
- Create migrations for all domain tables per §1: `settings`, `service_categories`, `services`, `staff`, `staff_services`, `staff_schedules`, `staff_time_off`, `clients`, `client_photos`, `appointments`, `appointment_services`, `reminder_logs`.
- Add `role enum('admin','staff')` and nullable `staff_id` FK to `users` (new migration; do not edit the original users migration).
- Add all FKs, unique constraints (`clients.phone`, `staff_schedules(staff_id,weekday)`), and indexes exactly as specified.
- Create Eloquent models with relationships, casts (`working_hours`→array/json, `tags`→array, enums), and `SoftDeletes` on `appointments`, `clients`, `services`, `staff` (historical data per Database Rules).
- Factories for every model; a `DemoSeeder` (one salon `settings` row, ~2 categories, ~6 services, 2 staff each with a weekly schedule and linked services, a few clients).
- Register `role`/`staff_id` on `User` fillable/casts and add `isAdmin()`/`isStaff()` helpers.
- Global RTL: set `dir="rtl"` + `lang="ar"` in both root layouts; confirm Tailwind v4 logical properties render.

**Deliverables:** `php artisan migrate:fresh --seed` produces a fully populated demo DB; all models resolvable in Tinker; app renders RTL; existing auth/dashboard untouched.

**Tests to write:**
- Migration smoke test: `migrate:fresh` runs green on the MySQL test database.
- Model relationship tests (appointment↔services, staff↔services, client↔photos, user↔staff).
- Constraint tests: duplicate `clients.phone` rejected; unique `(staff_id,weekday)` enforced.
- Factory tests: each factory creates a valid persisted row.

**Risks:**
- **MySQL enum/JSON handling** — prefer app-layer validation of enum values over relying solely on native `ENUM` columns (keeps values easy to extend); use the native `JSON` column type for `working_hours`/`tags` and assert round-trips via casts.
- **FK onDelete semantics** (`restrict` on appointments, `set null` on category/photo) must match the doc or later deletes fail unexpectedly.
- Soft-delete + unique(`phone`) interaction: a soft-deleted client still holds the phone. Decide policy now (documented: treat as reuse-on-restore).

---

## Day 2 — Admin shell, role-based auth, Settings module

**Goal:** Admins log into a protected admin area, are routed by role, and can edit all salon settings including per-day working hours. Staff role sees a restricted shell.

**Modules:** X (auth/roles), M1 (Settings).

**Tasks:**
- Admin route group `/admin/*` behind `auth` + a role gate; redirect-by-role after login (admin → dashboard, staff → their appointments) without modifying Fortify's core flow (extend via redirect/response, per Fortify Rules).
- Admin layout (reuse `layouts/app` sidebar) with nav placeholders for the coming modules; role-aware menu visibility.
- `Settings` Livewire page: salon name, logo upload, primary color, currency, timezone, week_start, phone, whatsapp_number, address, reminder_template.
- **Working-hours editor**: 7-day open/close/closed grid persisted to `settings.working_hours` JSON via a small Action/cast.
- A `Settings` singleton accessor (helper/service) so later modules read config without re-querying ad hoc.

**Deliverables:** Admin can log in, land by role, and save settings; working hours persist and re-render correctly.

**Tests to write:**
- Admin can view/update settings; non-admin is forbidden (403/redirect).
- Working-hours JSON round-trips (save → reload → same structure).
- Role redirect: admin vs staff land on correct route.
- Existing auth suite still passes (regression guard).

**Risks:**
- **Breaking Fortify redirects** — must extend, not replace. Keep changes to login-response only.
- Logo upload storage/symlink config (`storage:link`) needs to be set once; document it.
- Singleton settings row: guard against zero/multiple rows (seed guarantees one; add a `firstOrCreate` guard).

---

## Day 3 — Service Catalog module

**Goal:** Admin fully manages service categories and services (name, duration, price, color, active, ordering). This is the first input source for availability and booking.

**Modules:** M2.

**Tasks:**
- `service-categories` CRUD (Livewire): name, sort_order, is_active; drag-to-reorder persisting `sort_order`.
- `services` CRUD (Livewire): category, name, `duration_minutes`, price, description, color picker, is_active.
- Validation via Form Request / validation classes (duration > 0, price ≥ 0, color hex).
- List view grouped by category, with active/inactive filter; soft-delete handling.
- Keep business rules out of the component — thin CRUD, validation classes where useful.

**Deliverables:** Admin creates/edits/reorders categories and services; only active services will later surface in booking.

**Tests to write:**
- Service CRUD happy paths + validation failures (negative duration, bad hex, missing name).
- Category reorder persists `sort_order`.
- Category `set null` on delete leaves services intact with null category.
- Inactive services excluded from an "active" query scope.

**Risks:**
- Color/duration are consumed by the calendar and availability later — lock field formats now to avoid rework.
- Reordering UX with Livewire (no Alpine unless required) — keep it server-driven.

---

## Day 4 — Staff module (profiles + service linking)

**Goal:** Admin manages staff and assigns which services each staff member performs (`staff_services`). This gates who can be booked for a given service.

**Modules:** M3 (part 1).

**Tasks:**
- `staff` CRUD: name, phone, photo, bio, commission_percent, is_active.
- `staff_services` many-to-many management UI (attach/detach services per staff).
- Optional link of a `users` account to a `staff` row (staff_id) for staff-role login.
- Query scope: "staff who provide ALL of a given set of services" (needed by availability's candidate filter).

**Deliverables:** Admin manages staff and their service offerings; the "provides all selected services" query is available and tested.

**Tests to write:**
- Staff CRUD + validation.
- Attach/detach services; pivot integrity.
- "Provides all of services [A,B]" scope returns only fully-qualified staff.
- Deleting a service cascades cleanly from `staff_services`.

**Risks:**
- The "provides ALL services" query is subtle (HAVING COUNT / whereHas per service). Get it right here — availability depends on it directly.
- Commission_percent feeds reports (Day 11); keep precision (decimal 5,2).

---

## Day 5 — Staff schedules & time-off

**Goal:** Admin sets each staff member's weekly working hours and specific-date leaves. After today, **all inputs to the availability algorithm exist.**

**Modules:** M3 (part 2).

**Tasks:**
- `staff_schedules` weekly editor: per weekday start/end + is_off, unique per (staff, weekday).
- `staff_time_off` management: date, optional start/end (empty = full day), reason; list/add/remove.
- Validation: end > start; no overlapping schedule rows per weekday; time-off date not in the far past (policy).
- Extend seeder so demo staff have realistic schedules + one sample time-off (fuels Day 6 tests).

**Deliverables:** Admin defines weekly availability and leaves; demo data now supports realistic slot generation.

**Tests to write:**
- Schedule CRUD; unique (staff, weekday) enforced; end>start validation.
- Time-off full-day vs partial-day creation.
- Data-shape tests confirming the engine will receive well-formed windows.

**Risks:**
- **Timezone correctness** — schedules are wall-clock times; appointments are datetimes. Establish the timezone convention now (store salon-local, per `settings.timezone`) so Day 6 doesn't fight it.
- Partial vs full-day time-off representation (null times) must be unambiguous for the subtraction step in §4.

---

## Day 6 — Availability Engine (the heart)

**Goal:** `AvailabilityService` implements §4 exactly and is proven by an exhaustive unit-test suite. A debug/read endpoint returns real slots. **No booking writes yet.**

**Modules:** M4.

**Tasks:**
- `AvailabilityService` in `app/Services/`: inputs (service list → total duration D, staff S or "any", date) → available start times with a suggested staff per slot.
- Implement the full algorithm: weekday → salon hours → per-candidate-staff schedule intersection → subtract time-off → subtract existing appointments (status ≠ cancelled) → step-generate candidates (configurable step, e.g. 15 min) → future+buffer filter → "any staff" resolution.
- Overlap rule helper: `A.start < B.end AND B.start < A.end`.
- Internal read-only endpoint `GET /api/available-slots?service_ids=&staff_id=&date=` (used by Day 9 wizard).
- Keep it a pure service (no Livewire), fully unit-testable.

**Deliverables:** Given seeded data, the service and endpoint return correct available slots for specific-staff and "any staff" cases.

**Tests to write (the deepest suite of the project):**
- Salon closed that weekday → no slots.
- Staff `is_off` weekday → excluded.
- Full-day and partial time-off remove the right windows.
- Existing appointment blocks overlapping candidates (boundary-adjacent slots remain valid — `end == start` does not conflict).
- Multi-service duration summed correctly; D exceeding remaining window yields no slot.
- "Any staff" returns a slot if ≥1 qualified staff free; suggested staff recorded.
- Past/buffer filtering (freeze time in tests).
- Step boundary cases at window edges.

**Risks:**
- **Highest-risk day.** Off-by-one on interval overlap and inclusive/exclusive end-times causes double-booking or lost slots. Boundary tests are mandatory.
- Timezone/DST handling in candidate generation.
- Performance with many appointments — acceptable now, but keep the query set bounded per staff/date.

---

## Day 7 — Clients module

**Goal:** Admin manages client records, cosmetic profile fields, and the before/after photo gallery.

**Modules:** M5.

**Tasks:**
- `clients` CRUD keyed on unique `phone`; list with search (name/phone), visit count, last visit.
- Client profile page: cosmetic fields (skin_type, hair_type, notes, tags JSON), appointment history (read; populated fully once Day 8 lands), total spend (Σ price_snapshot of completed).
- `client_photos`: upload, classify before/after, optional link to an appointment, note, taken_at; gallery view.
- Phone normalization/validation (+966/+971) shared with booking (extract a rule/helper for reuse on Day 9).

**Deliverables:** Admin creates/edits clients, manages tags, and uploads categorized photos; profile aggregates render.

**Tests to write:**
- Client CRUD; unique phone enforced; phone-format validation.
- Photo upload stored + classified; appointment link nullable and `set null` safe.
- Total-spend/visit-count aggregates compute correctly (seed a couple completed appointments or assert zero-state).

**Risks:**
- Reuse the phone rule in the public wizard — diverging validation causes mismatched/duplicate clients.
- Image storage size/type limits; storage disk config consistent with Day 2 logo setup.

---

## Day 8 — Appointments core (booking action + admin management)

**Goal:** Appointments can be created safely (transaction + lock, re-checked availability, price/duration snapshots) and managed by admin: manual creation, filtering, and status transitions. Conflicts are impossible under concurrency.

**Modules:** M6.

**Tasks:**
- `BookAppointment` action in `app/Actions/`: opens a transaction, re-runs availability with a lock for the target staff/window, computes `datetime_end` = start + ΣD, writes `appointment` + `appointment_services` with **price & duration snapshots**, links/creates client.
- Status transition action (pending→confirmed→completed / cancelled / no_show) with allowed-transition rules; log all booking changes (per Database Rules).
- `/admin/appointments` table: filters (status, date range, staff, client phone search); row actions (status change, edit, soft-delete — never hard-delete).
- Manual creation flow from admin (source=manual) reusing `BookAppointment`.
- Policies: staff role sees only their own appointments; admin sees all.

**Deliverables:** Admin books manual appointments with conflict protection; status changes and filters work; every appointment carries immutable price/duration snapshots.

**Tests to write:**
- `BookAppointment` writes appointment + service snapshots; `datetime_end` correct.
- **Concurrency/double-book prevention**: second booking of the same locked slot fails (simulate via re-check path).
- Availability re-check rejects a slot that filled between selection and submit.
- Status transition rules (illegal transitions blocked); soft-delete keeps the row.
- Staff policy: staff user cannot see/modify others' appointments.
- Snapshots stay fixed after the underlying service price later changes.

**Risks:**
- **Race conditions** — use MySQL row locking (`lockForUpdate`) inside the transaction; ensure the booking runs on an InnoDB table so locks apply. Test the re-check logic against the MySQL test database.
- Snapshot discipline: never recompute price from live service on read.
- Cancelled appointments must be excluded from availability but retained (soft/status).

---

## Day 9 — Public booking wizard + self-service

**Goal:** A guest can book online end-to-end on mobile, land on a confirmation with an `.ics` link, and later look up / cancel their booking by phone.

**Modules:** M7.

**Tasks:**
- `/book` multi-step Livewire wizard: (1) pick services grouped by category, (2) pick staff or "any" (filtered to those providing all selected services), (3) pick date → available slots from the Day 6 endpoint/service, (4) client name + phone, (5) confirm.
- Submit path calls `BookAppointment` (source=online, status=pending) with the transactional re-check.
- `/book/confirmed/{id}` summary + salon WhatsApp link + **`.ics` calendar download**.
- `/my-booking`: phone lookup → upcoming appointments → cancel within the allowed policy window (status→cancelled).
- Mobile-first RTL styling; reuse phone validation from Day 7.

**Deliverables:** Public online booking works start to finish; confirmation + `.ics`; clients self-cancel within policy.

**Tests to write:**
- Full wizard happy path creates the correct appointment + services + client link.
- Staff filter shows only qualified staff; "any" resolves a staff on submit.
- Slot taken between load and submit → user sees a friendly re-pick (re-check fires).
- `.ics` contains correct start/end/summary.
- `/my-booking` cancel respects the policy window; out-of-window cancel blocked.

**Risks:**
- Livewire multi-step state integrity across steps; avoid trusting any frontend availability (server re-check is authoritative, per Booking Rules).
- Guest client dedupe by phone (link vs create) — reuse Day 7 normalization exactly.
- `.ics` timezone formatting.

---

## Day 10 — Admin operations: dashboard, calendar, reminders

**Goal:** The daily operational cockpit — KPI dashboard, a day/week calendar with drag-drop rescheduling (availability re-checked), and WhatsApp reminders.

**Modules:** M8.

**Tasks:**
- `/admin` dashboard cards: today's appointment count, expected revenue (Σ price_snapshot non-cancelled), no-shows, next-up; today's timeline list.
- `/admin/calendar` day/week view; appointment blocks colored by service/status; click to open; create from an empty slot (reuses `BookAppointment`).
- **Drag-drop reschedule**: on drop, re-run availability for the new window; accept or reject with feedback (never trust the client).
- Reminders: `POST /admin/appointments/{id}/reminder` builds a `wa.me` link from the salon number + reminder_template variables and writes a `reminder_logs` row.

**Deliverables:** Admin runs the day from dashboard + calendar; reschedules safely; sends logged WhatsApp reminders.

**Tests to write:**
- Dashboard aggregates match seeded data (revenue excludes cancelled).
- Reschedule to a free slot succeeds; to a conflicting slot is rejected and unchanged.
- Reminder creates a `reminder_logs` row and a correct wa.me URL with substituted variables.
- Staff-role calendar shows only their appointments.

**Risks:**
- Drag-drop in Livewire without a JS framework — keep drop → server re-validate authoritative; degrade gracefully.
- Week-view query volume; index usage on `(staff_id, datetime_start)`.
- WhatsApp template variable substitution correctness.

---

## Day 11 — Reports, hardening, and release polish

**Goal:** Reporting is complete and the whole system is production-hardened: full green test suite, static analysis, RTL/mobile polish, and edge-case cleanup.

**Modules:** M9 + cross-cutting.

**Tasks:**
- `/admin/reports`: most-requested services (count via `appointment_services`), staff performance (completed count/revenue), **commission per staff** = commission_percent × Σ completed service value, no-shows; date-range filter.
- License/domain guard using `settings.license_domain` (per doc's protection note).
- End-to-end pass: run `composer test` (Pint + PHPStan L7 + Pest) to green; fix lint/type debt.
- RTL/mobile audit across all screens; empty-states and error states; seed a realistic demo dataset for handoff.
- Review authorization matrix (admin vs staff) across every route/policy.

**Deliverables:** Complete, tested, lint-clean, type-clean salon system with reports; demo-ready seed; documented setup.

**Tests to write:**
- Report aggregates: top services ordering, staff revenue, commission math (with snapshots, not live prices).
- Date-range filtering boundaries.
- Full-suite regression: auth, settings, services, staff, availability, appointments, booking, calendar, reports all green.
- Authorization sweep: each admin route rejects staff/guest appropriately.

**Risks:**
- Commission/revenue must use **snapshots**, not current service prices — verify explicitly.
- PHPStan level 7 across new code may surface type gaps accumulated over the sprint — budget time.
- Report query performance on larger datasets; add indexes/scopes if needed.

---

## Cross-cutting standards (apply every day)

- **Architecture:** Livewire = UI orchestration only; business logic in `app/Actions` (single operations) and `app/Services` (complex domain, e.g. `AvailabilityService`). Validation via Form Requests / validation classes. Policies for authorization.
- **Booking rules:** always check staff availability; prevent overlaps; transactions for creation; locks on confirm; never trust frontend availability.
- **Database rules:** always FKs; soft-deletes where history matters; never hard-delete appointments; preserve price/duration snapshots; log all booking changes.
- **Fortify:** do not modify the auth flow; extend via actions/events/redirects only.
- **Packages:** none added without explicit approval and justification. No jQuery/Alpine unless strictly required. No refactoring of unrelated files during feature work.
- **Definition of Done (per day):** feature usable in-app + tests written and passing + `composer test` green (Pint + PHPStan L7 + Pest) + RTL renders correctly + existing auth untouched.

## Sequencing risk register (top 5)

1. **Availability algorithm correctness (Day 6)** — mitigated by a dedicated day + exhaustive boundary tests before any booking writes.
2. **Concurrent double-booking (Day 8)** — transaction + `lockForUpdate` re-check; test the re-check path; design for MySQL semantics.
3. **SQLite (dev/test) vs MySQL (prod) divergence** — enums, JSON, locking. Validate enums in-app; keep queries portable; note MySQL-only behaviors.
4. **Timezone consistency (Days 5–9)** — fix the salon-local convention early; freeze time in tests.
5. **Snapshot integrity for money (Days 8, 11)** — never recompute price from live services; assert snapshots survive later price edits.
