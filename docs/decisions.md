# Architecture Decision Log

> Lightweight ADRs. One entry per significant technical decision. Newest at the top of the log.
> Keep entries short: **Context → Decision → Consequences**. Never rewrite history — supersede instead.

**Status values:** `Proposed` · `Accepted` · `Superseded by ADR-NNN` · `Deprecated`

---

## Index

| ID | Title | Status | Date |
|----|-------|--------|------|
| ADR-0015 | Public booking flow: capability-by-id pages + lead-time cancellation | Accepted | 2026-07-13 |
| ADR-0014 | Booking safety via staff-row lock + availability re-check | Accepted | 2026-07-13 |
| ADR-0013 | Shared phone normalization + validation rule | Accepted | 2026-07-13 |
| ADR-0012 | Availability engine reasons in salon-local wall clock | Accepted | 2026-07-12 |
| ADR-0011 | Schedules are salon-local wall-clock; time-off null times = full day | Accepted | 2026-07-12 |
| ADR-0010 | "Provides all services" via per-service `whereHas` | Accepted | 2026-07-12 |
| ADR-0009 | Server-driven category reordering (no Alpine) | Accepted | 2026-07-11 |
| ADR-0008 | Role-based admin gating and post-login redirect | Accepted | 2026-07-10 |
| ADR-0007 | App-layer conflict detection with locked transaction | Accepted | 2026-07-09 |
| ADR-0006 | Money stored as immutable price/duration snapshots | Accepted | 2026-07-09 |
| ADR-0005 | Soft deletes for historical entities | Accepted | 2026-07-09 |
| ADR-0004 | Business logic in Actions/Services, not Livewire | Accepted | 2026-07-09 |
| ADR-0003 | Extend Fortify rather than modify its flow | Accepted | 2026-07-09 |
| ADR-0002 | Single-tenant, RTL/Arabic, no billing | Accepted | 2026-07-09 |
| ADR-0001 | MySQL for development, test, and production | Accepted | 2026-07-09 |

---

## ADR-0015 — Public booking flow: capability-by-id pages + lead-time cancellation
**Status:** Accepted · **Date:** 2026-07-13
**Context:** Day 9 exposes the first unauthenticated write path (guests booking online) and two guest-facing read/cancel pages. Guests have no account, so the flow must identify and dedupe them without login, keep multi-step wizard state honest against a live availability engine, and let them cancel without an authorization identity — all while never trusting frontend availability (Booking Rules) and never hard-deleting appointments (ADR-0005).
**Decision:** The public flow reuses existing domain machinery rather than adding parallel logic. The `/book` wizard is UI-only (ADR-0004): it orchestrates the Day 6 `AvailabilityService` for slot display and the Day 8 `BookAppointment` for the write (`source=online`, `status=pending`), so the authoritative locked re-check (ADR-0014) is the single guard against double-booking — a slot that filled between selection and submit throws `SlotUnavailableException` and returns the guest to the time step. Guests are found-or-created by the shared normalized `PhoneNumber` (ADR-0013), so a returning guest links to their existing client record. The confirmation page (`/book/confirmed/{id}`) and the `.ics` download (`/book/{id}/calendar.ics`) are **capability-by-id**: unauthenticated, with the appointment id serving as the (unguessable-enough for this single-tenant, non-sensitive context) capability — matching how a confirmation link is shared. `/my-booking` identifies the caller purely by a looked-up normalized phone, scoping every listed and cancellable appointment to that phone's client; cancellation is allowed only for still-active (pending/confirmed) appointments at least `CANCELLATION_LEAD_MINUTES` (default 120) before start, and runs through `ChangeAppointmentStatus` (status → cancelled, never a delete). The `.ics` emits salon-local wall-clock datetimes (ADR-0012) as absolute UTC instants to stay timezone-correct in any calendar app.
**Consequences:** One definition of availability and one booking writer serve the admin manual flow and the public wizard, so conflict-safety and snapshot discipline hold identically for guests. Capability-by-id pages need no auth but expose an appointment's summary/`.ics` to anyone with the id — acceptable for a booking confirmation and consistent with sharing the link, but not a place for sensitive data. Phone-only self-service means anyone who knows a phone number can view/cancel that number's upcoming bookings; acceptable for this single-tenant salon (ADR-0002), but a future hardening could add an OTP/verification step. The cancellation window is a fixed component constant for now; making it a salon setting is deferred.

## ADR-0014 — Booking safety via staff-row lock + availability re-check
**Status:** Accepted · **Date:** 2026-07-13
**Context:** ADR-0007 established that appointment conflicts are detected in application code (durations are variable, so no DB constraint can enforce non-overlap) and confirmed inside a locked transaction. Day 8 implements the actual writer, `BookAppointment`, which must be immune to concurrent double-booking of the same staff/slot while reusing the Day 6 `AvailabilityService` (whose reads are non-locking) as the source of truth for what "available" means.
**Decision:** `BookAppointment` runs inside a `DB::transaction`. Its **first** database read is `Staff::whereKey($id)->lockForUpdate()` — an exclusive row lock that serializes all concurrent bookings for that staff member. Because that locking read precedes any consistent (non-locking) read, the InnoDB read view for the transaction is established *after* the lock is acquired, so the subsequent `AvailabilityService` reads see the latest committed appointments (including one a competing transaction just committed before releasing the lock). The action then re-runs `availableSlots()` for the specific staff and rejects the write with `SlotUnavailableException` unless the requested `H:i` start is still offered. Snapshots (price/duration) and `datetime_end` are computed from the live services *at write time* and then frozen (ADR-0006); datetimes are written as salon-local wall clock (ADR-0012). A null `staff_id` ("any") is resolved to the earliest-id free staff *inside* the transaction before locking that staff. Status changes go through `ChangeAppointmentStatus`, gated by the transition rules on the `AppointmentStatus` enum; cancellation is a status change, never a delete.
**Consequences:** Two concurrent bookings for the same staff serialize on the staff row; the loser re-checks against the winner's committed appointment and is rejected — no double-booking, proven by the taken-slot/filled-slot tests via the re-check path (true multi-threaded races aren't simulated in Pest, but the mechanism they rely on is). The lock is per-staff, so bookings for different staff never contend. The availability engine's lead-time buffer applies uniformly, so booking a manual walk-in *inside* the buffer window is currently rejected — an accepted limitation for now (a future "admin override" could bypass the buffer for manual sources). Reusing `availableSlots()` for the re-check keeps one definition of availability across the read endpoint, the wizard, and the writer.

## ADR-0013 — Shared phone normalization + validation rule
**Status:** Accepted · **Date:** 2026-07-13
**Context:** Clients are keyed on a unique `phone` (the primary identifier). Day 7's admin client form and Day 9's public booking wizard both create/link clients by phone. The roadmap flags that diverging phone validation between the two flows would produce mismatched or duplicate client records, and that spacing/format variants of the same number must not defeat the unique constraint.
**Decision:** Extract a single `App\Rules\PhoneNumber` (`ValidationRule`) enforcing the canonical international form `+(966|971)\d{8,9}`, with a static `PhoneNumber::normalize()` that strips spaces/dashes/parentheses and converts a leading `00` to `+`. Callers **normalize before validating and before the unique check** (the client component sets `$this->phone = PhoneNumber::normalize(...)` prior to `validate()`), so the value that hits `Rule::unique('clients','phone')` is already canonical. The unique rule intentionally counts soft-deleted rows, keeping the reuse-on-restore policy (ADR-0005). Day 9 must reuse this exact rule + normalization for guest dedupe.
**Consequences:** One source of truth for phone shape across admin and public flows; spacing variants collapse to the same stored value, so the unique constraint holds. Only Saudi/Emirati numbers are accepted, matching the single-tenant KSA/UAE scope (ADR-0002); supporting other countries later means widening the one rule. Normalization is deliberately minimal (no libphonenumber dependency) — it canonicalizes separators and the `00`/`+` prefix but does not reformat national-format input, which is surfaced to the user as a validation error instead.

## ADR-0012 — Availability engine reasons in salon-local wall clock
**Status:** Accepted · **Date:** 2026-07-12
**Context:** `AvailabilityService` (Day 6) intersects three inputs with different storage shapes: `settings.working_hours` and `staff_schedules` are naive `H:i` wall-clock (ADR-0011), while `appointments.{datetime_start,datetime_end}` are `datetime` columns. `config('app.timezone')` is `UTC`, but the salon runs in `settings.timezone` (e.g. `Asia/Riyadh`). Laravel's datetime casts **do not convert timezones on write** — a `Carbon` is formatted using its own timezone and stored verbatim — so an appointment booked for a salon-local time is persisted as those same wall-clock digits and reads back labelled `UTC`. Naively `setTimezone(salon)` on read shifted every appointment by the salon offset, corrupting overlap detection.
**Decision:** The engine performs **all** interval math in the salon timezone. Salon hours and schedules are composed with the target date via `settings.timezone`. Appointments are matched with `whereDate('datetime_start', <salon date>)` (comparing the stored wall-clock date, not a UTC range) and each busy interval is rebuilt by re-parsing the appointment's raw `Y-m-d H:i:s` **as** salon-local — i.e. the stored digits are treated as the salon wall clock, never offset-shifted. "Now + buffer" uses `Carbon::now(settings.timezone)`. The half-open overlap rule `A.start < B.end && B.start < A.end` treats touching boundaries (`end == start`) as free. Day 8's `BookAppointment` must write appointment datetimes as salon-local wall clock to stay consistent.
**Consequences:** Availability is correct regardless of the divergence between `app.timezone` (UTC) and the salon timezone, and is proven by boundary tests (adjacency, partial time-off edges, step/window edges, buffer). The convention couples the engine to "appointments stored as salon-local wall clock"; a future move to true UTC instants (or per-branch timezones, ADR-0002) would require revisiting both this service and the booking writer together. `whereDate` assumes appointments never cross midnight, which holds given salon working hours.

## ADR-0011 — Schedules are salon-local wall-clock; time-off null times = full day
**Status:** Accepted · **Date:** 2026-07-12
**Context:** Day 5 introduces `staff_schedules` (weekly hours) and `staff_time_off` (specific-date leaves) — the last inputs to the availability engine (Day 6). The roadmap flags timezone correctness as a top risk: schedules are wall-clock times while appointments are datetimes, and the partial-vs-full-day time-off representation must be unambiguous for the engine's subtraction step.
**Decision:** Store `staff_schedules.{start_time,end_time}` and `staff_time_off.{start_time,end_time}` as **naive salon-local wall-clock `TIME` values**, interpreted against `settings.timezone`. No timezone math happens at the schedule layer; Day 6 composes these wall-clock windows with the target date in the salon timezone. For `staff_time_off`, **both times null = a full-day leave**; **both set = a partial-day window** to subtract. The form enforces this: either both bounds are provided (end after start) or neither. The weekly editor is a fixed 7-row grid (one row per weekday) upserted on the unique `(staff_id, weekday)` key, so overlapping/duplicate weekday rows are structurally impossible.
**Consequences:** The engine can treat schedule/time-off windows as plain `H:i` ranges without per-row tz conversion, applying `settings.timezone` once when generating candidate datetimes. Half-open partial windows (both bounds required) keep the subtraction step unambiguous. DST edge cases are deferred to Day 6 and confined to candidate generation. A future multi-branch/multi-tz model would need per-schedule tz, which this single-tenant design intentionally omits (ADR-0002).

## ADR-0010 — "Provides all services" via per-service `whereHas`
**Status:** Accepted · **Date:** 2026-07-12
**Context:** The availability engine (Day 6) must narrow candidate staff to those who provide *every* service in a multi-service booking. The roadmap flags this query as subtle; a naive `whereHas('services', in [ids])` matches staff providing *any* one of them, not all.
**Decision:** Implement `Staff::scopeProvidesAllServices(array $serviceIds)` as one `whereHas('services')` constraint per distinct service id (each requiring that specific service on the pivot). All constraints must hold, so only fully-qualified staff match. Because the constraint runs through the `services()` relation, the `Service` soft-delete scope applies automatically — a staff member whose required service was soft-deleted no longer qualifies. Callers chain `->active()` to also require the staff be active.
**Consequences:** Correctness is readable and covered by tests (fully-qualified vs partial, and soft-delete exclusion). It issues one correlated subquery per service; acceptable for the small service-per-booking counts expected here. An equivalent `GROUP BY … HAVING COUNT(DISTINCT service_id) = N` form was rejected as harder to compose with soft-delete scoping and other query constraints.

## ADR-0009 — Server-driven category reordering (no Alpine)
**Status:** Accepted · **Date:** 2026-07-11
**Context:** The roadmap (Day 3) calls for reordering service categories by persisting `sort_order`, ideally via drag-and-drop. Project rules forbid adding jQuery/Alpine unless required and prefer server-driven Livewire UX.
**Decision:** Implement reordering with explicit move up/down controls handled entirely on the server. The Livewire component computes the new ordering and delegates persistence to a `ReorderServiceCategories` action that assigns contiguous `sort_order` values (0, 1, 2, …) inside a transaction. No client-side drag library is introduced.
**Consequences:** Reordering is fully testable without JS and cannot desync with the backend. The action is reusable, so a drag-and-drop layer can be added later (emitting an ordered ID list) without changing the persistence logic. UX is click-based rather than drag-based for now.

## ADR-0008 — Role-based admin gating and post-login redirect
**Status:** Accepted · **Date:** 2026-07-10
**Context:** The admin area (`/admin/*`) must be reachable only by admins, and users should land on a role-appropriate page after login — all without modifying Fortify's core auth flow (ADR-0003).
**Decision:** Guard `/admin/*` with an `admin` route middleware (`EnsureUserIsAdmin`, aborts 403 for non-admins) on top of `auth`. Route by role after login by binding a custom `Laravel\Fortify\Contracts\LoginResponse` that redirects admins to `admin.dashboard` and everyone else to `config('fortify.home')`. Modules read salon config through a `Setting::current()` singleton accessor (returns the first row, creating one with defaults if none exists — guards the zero/multiple-row cases).
**Consequences:** Role routing is additive; the existing auth suite remains the regression guard and is unchanged. `/admin` currently redirects to `/admin/settings` until the Day 10 dashboard lands. The `admin` group intentionally omits `verified` so the seeded (unverified) admin demo account can reach settings.

## ADR-0007 — App-layer conflict detection with locked transaction
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Appointment durations are variable, so overlap cannot be enforced by a DB constraint. Concurrent requests could double-book the same staff/slot.
**Decision:** Detect conflicts in application code via `AvailabilityService`, and confirm bookings inside a DB transaction that re-checks the slot with `lockForUpdate` before writing (roadmap §4 / Day 8).
**Consequences:** Correctness depends on test coverage of the overlap rule (`A.start < B.end AND B.start < A.end`) and the locked re-check path. Requires InnoDB tables. Never trust frontend availability.

## ADR-0006 — Money stored as immutable price/duration snapshots
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Service prices/durations change over time; historical appointments and reports must reflect the values at booking time.
**Decision:** `appointments.price_snapshot` and `appointment_services.{price,duration_minutes}` are captured at booking and never recomputed from live services. Reports/commission read snapshots only.
**Consequences:** Editing a service does not alter past appointments or revenue/commission figures. Tests must assert snapshots survive later price edits.

## ADR-0005 — Soft deletes for historical entities
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Appointments, clients, and catalog data carry historical/reporting value; hard deletes would corrupt history.
**Decision:** Use `SoftDeletes` on `appointments`, `clients`, `services`, `staff`. Appointments are never hard-deleted. Cancellations use `status`, not deletion.
**Consequences:** Queries must respect soft-delete scopes. Unique `clients.phone` interacts with soft-deletes — policy: treat as reuse-on-restore.

## ADR-0004 — Business logic in Actions/Services, not Livewire
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Project rules require thin UI components and testable domain logic.
**Decision:** Livewire orchestrates UI only. Single operations live in `app/Actions`; complex domain logic (e.g. `AvailabilityService`) in `app/Services`. Validation via Form Requests / validation classes; authorization via Policies.
**Consequences:** More classes, but logic is unit-testable without Livewire and reusable across public/admin flows.

## ADR-0003 — Extend Fortify rather than modify its flow
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Auth (password, 2FA, email verification, passkeys) is complete and must not regress.
**Decision:** Add role-based redirects and admin gating by extending via redirect responses/events/actions — no edits to Fortify's core auth flow.
**Consequences:** Role routing is additive and low-risk; existing auth test suite remains the regression guard.

## ADR-0002 — Single-tenant, RTL/Arabic, no billing
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Product scope per the technical build analysis.
**Decision:** Build a single-tenant salon system, Arabic-first RTL UI, mobile-first for the public booking flow, with no billing/invoicing module.
**Consequences:** Layouts default to `dir="rtl"`/`lang="ar"`; no payment integration in scope; one `settings` row governs the salon.

## ADR-0001 — MySQL for development, test, and production
**Status:** Accepted · **Date:** 2026-07-09
**Context:** Earlier scaffold defaulted to SQLite for dev/tests, which diverges from a MySQL production target (enums, JSON, row locking).
**Decision:** Use MySQL across development, testing, and production. Tests run against a dedicated MySQL test database. Keep engine (InnoDB), charset (`utf8mb4`), and timezone consistent across environments.
**Consequences:** No dev/prod DB divergence; row locking (`lockForUpdate`) behaves as in production. Requires a local MySQL instance and a configured test database for CI/local runs.

---

## How to add a decision
1. Copy an entry block, assign the next `ADR-NNNN`, set **Status** and **Date**.
2. Fill **Context → Decision → Consequences** in a few lines each.
3. Add a row to the **Index** table (newest at top).
4. To reverse a decision, add a new ADR and mark the old one `Superseded by ADR-NNNN` — do not delete it.
