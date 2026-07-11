# Architecture Decision Log

> Lightweight ADRs. One entry per significant technical decision. Newest at the top of the log.
> Keep entries short: **Context → Decision → Consequences**. Never rewrite history — supersede instead.

**Status values:** `Proposed` · `Accepted` · `Superseded by ADR-NNN` · `Deprecated`

---

## Index

| ID | Title | Status | Date |
|----|-------|--------|------|
| ADR-0008 | Role-based admin gating and post-login redirect | Accepted | 2026-07-10 |
| ADR-0007 | App-layer conflict detection with locked transaction | Accepted | 2026-07-09 |
| ADR-0006 | Money stored as immutable price/duration snapshots | Accepted | 2026-07-09 |
| ADR-0005 | Soft deletes for historical entities | Accepted | 2026-07-09 |
| ADR-0004 | Business logic in Actions/Services, not Livewire | Accepted | 2026-07-09 |
| ADR-0003 | Extend Fortify rather than modify its flow | Accepted | 2026-07-09 |
| ADR-0002 | Single-tenant, RTL/Arabic, no billing | Accepted | 2026-07-09 |
| ADR-0001 | MySQL for development, test, and production | Accepted | 2026-07-09 |

---

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
