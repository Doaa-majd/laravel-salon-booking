# Known Issues & Risks

> Tracks open bugs, active risks, and their mitigations. Seeded from the roadmap's risk register.
> Bugs found during development go here first, then get scheduled in [`backlog.md`](backlog.md) and closed via [`changelog.md`](changelog.md).

**Type:** `Risk` (potential) · `Bug` (observed) · `Debt` (known shortcut)
**Severity:** `S1` blocker · `S2` major · `S3` minor
**Status:** `Open` · `Mitigating` · `Monitoring` · `Resolved` · `Won't Fix`

_Last updated: 2026-07-10_

---

## Open items

| ID | Type | Sev | Title | Owner | Status |
|----|------|-----|-------|-------|--------|
| KI-1 | Risk | S1 | Availability algorithm correctness (boundaries/overlap) | — | Open |
| KI-2 | Risk | S1 | Concurrent double-booking under load | — | Open |
| KI-3 | Risk | S2 | Timezone consistency across schedules & appointments | — | Open |
| KI-4 | Risk | S2 | Money-snapshot integrity vs live price edits | — | Open |
| KI-5 | Risk | S3 | MySQL config consistency across environments | — | Open |
| KI-6 | Debt | S3 | Roadmap risk-register line still references SQLite (line ~380) | — | Open |
| KI-7 | Debt | S3 | `storage:link` required for uploaded salon logos to be web-visible | — | Open |

---

## Detail

### KI-1 — Availability algorithm correctness `Risk` `S1` `Open`
- **Where:** M4 `AvailabilityService` (Day 6), roadmap §4.
- **Impact:** Off-by-one on interval overlap or inclusive/exclusive end-times causes double-booking or lost slots.
- **Mitigation:** Dedicated build day; exhaustive boundary unit tests (adjacent `end == start` not a conflict, window edges, buffer, closed days, full/partial time-off) written before any booking writes.
- **Trigger to close:** M4-5 test suite green and reviewed.

### KI-2 — Concurrent double-booking `Risk` `S1` `Open`
- **Where:** M6 `BookAppointment` (Day 8).
- **Impact:** Two simultaneous confirmations for the same staff/slot.
- **Mitigation:** Transaction + `lockForUpdate` re-check before insert (InnoDB). Test the re-check path (slot filled between select and submit).
- **Trigger to close:** Re-check test passes; verified on MySQL.

### KI-3 — Timezone consistency `Risk` `S2` `Open`
- **Where:** M3 schedules (Day 5) through M7/M8 (Days 9–10).
- **Impact:** Wall-clock schedules vs datetime appointments can drift; `.ics` and slot generation may be wrong.
- **Mitigation:** Fix salon-local convention (`settings.timezone`) early; freeze time in tests; consistent DB timezone across environments.
- **Trigger to close:** Time-frozen tests pass across booking/`.ics`.

### KI-4 — Money-snapshot integrity `Risk` `S2` `Open`
- **Where:** M6 (Day 8), M9 reports (Day 11). See [ADR-0006](decisions.md).
- **Impact:** Revenue/commission recomputed from live prices would misreport history.
- **Mitigation:** Read snapshots only; assert snapshots unchanged after a later service price edit.
- **Trigger to close:** Snapshot-persistence test green.

### KI-5 — MySQL config consistency `Risk` `S3` `Open`
- **Where:** All environments. See [ADR-0001](decisions.md).
- **Impact:** Divergent engine/charset/timezone between dev, test, prod causes enum/JSON/locking surprises.
- **Mitigation:** Standardize InnoDB + `utf8mb4` + timezone; run tests against MySQL.
- **Trigger to close:** CI/local test DB documented and matching prod settings.

### KI-6 — Roadmap still references SQLite `Debt` `S3` `Open`
- **Where:** [`11-day-roadmap.md`](11-day-roadmap.md) risk register (~line 380).
- **Impact:** Documentation inconsistency after the MySQL switch; no runtime effect.
- **Mitigation:** Update the divergence risk line to the MySQL-consistency wording used elsewhere.
- **Trigger to close:** Line edited to remove the SQLite reference.

### KI-7 — `storage:link` required for uploaded logos `Debt` `S3` `Open`
- **Where:** M1 Settings (Day 2) — `App\Livewire\Admin\Settings` logo upload; stored on the `public` disk under `logos/`.
- **Impact:** Uploaded logos save correctly but are not reachable via a public URL until the storage symlink exists.
- **Mitigation:** Run `php artisan storage:link` once per environment; the same disk convention is reused for client photos on Day 7.
- **Trigger to close:** Symlink documented in setup and present in deploy steps.

---

## How to use this file
1. Log new bugs/risks with the next `KI-n`, fill the detail block, and add a summary row.
2. Move `Open → Mitigating → Monitoring → Resolved` as work progresses; keep resolved items for history (or move to an archive section).
3. Cross-link to the ADR, backlog item, or changelog entry that addresses it.
