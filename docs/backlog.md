# Backlog

> Work items derived from [`11-day-roadmap.md`](11-day-roadmap.md), grouped by module. This is the "what's left to build" list; day-by-day status lives in [`progress.md`](progress.md).

**Item states:** `Todo` · `Doing` · `Blocked` · `Done`
**Priority:** `P0` critical path · `P1` important · `P2` nice-to-have
**ID scheme:** `<MODULE>-<n>` (e.g. `M4-2`). Keep IDs stable once assigned.

---

## Module map

| Module | Name | Depends on | Target day |
|--------|------|-----------|-----------|
| M0 | Foundation (schema/models/seed) | — | 1 |
| M1 | Settings | M0 | 2 |
| M2 | Service Catalog | M0 | 3 |
| M3 | Staff (profiles, services, schedules, time-off) | M0 | 4–5 |
| M4 | Availability Engine | M1, M3 | 6 |
| M5 | Clients + photos | M0 | 7 |
| M6 | Appointments core | M4, M5 | 8 |
| M7 | Public Booking | M6 | 9 |
| M8 | Admin Ops (dashboard/calendar/reminders) | M6 | 10 |
| M9 | Reports | M6 | 11 |
| X | Cross-cutting (roles, RTL, hardening) | M0 | 1, 11 |

---

## M0 — Foundation `P0` — ✅ Done (Day 1, 2026-07-09)
- [x] **M0-1** Migrations for all 12 domain tables (settings, service_categories, services, staff, staff_services, staff_schedules, staff_time_off, clients, client_photos, appointments, appointment_services, reminder_logs)
- [x] **M0-2** Add `role` enum + nullable `staff_id` FK to `users`
- [x] **M0-3** FKs, unique constraints, indexes per spec
- [x] **M0-4** Models + relationships + casts (JSON, enums) + soft-deletes
- [x] **M0-5** Factories for every model
- [x] **M0-6** `DemoSeeder` (salon, categories, services, 2 staff w/ schedules, clients)
- [x] **M0-7** Global RTL shell (`dir="rtl"`, `lang="ar"`)

## M1 — Settings `P0`
- [ ] **M1-1** Admin route group + role gate + redirect-by-role
- [ ] **M1-2** Settings Livewire page (name, logo, color, currency, timezone, week_start, phone, whatsapp, address, template)
- [ ] **M1-3** 7-day working-hours editor → `settings.working_hours` JSON
- [ ] **M1-4** Settings singleton accessor helper

## M2 — Service Catalog `P0`
- [ ] **M2-1** `service_categories` CRUD + drag-to-reorder (`sort_order`)
- [ ] **M2-2** `services` CRUD (duration, price, color, active)
- [ ] **M2-3** Validation classes (duration>0, price≥0, hex color)
- [ ] **M2-4** Active-only query scope

## M3 — Staff `P0`
- [ ] **M3-1** `staff` CRUD (name, phone, photo, bio, commission, active)
- [ ] **M3-2** `staff_services` attach/detach UI
- [ ] **M3-3** Link `users` account to `staff` (staff_id)
- [ ] **M3-4** "Provides ALL selected services" query scope
- [ ] **M3-5** `staff_schedules` weekly editor (unique per weekday)
- [ ] **M3-6** `staff_time_off` management (full/partial day)
- [ ] **M3-7** Extend seeder with schedules + sample time-off

## M4 — Availability Engine `P0`
- [ ] **M4-1** `AvailabilityService` implementing roadmap §4 algorithm
- [ ] **M4-2** Overlap helper (`A.start < B.end AND B.start < A.end`)
- [ ] **M4-3** "Any staff" resolution with suggested staff per slot
- [ ] **M4-4** Read-only `GET /api/available-slots` endpoint
- [ ] **M4-5** Exhaustive unit-test suite (boundaries, closed days, time-off, buffer)

## M5 — Clients `P1`
- [ ] **M5-1** `clients` CRUD keyed on unique phone + search
- [ ] **M5-2** Profile page (cosmetic fields, tags, history, total spend)
- [ ] **M5-3** `client_photos` upload/classify before-after + gallery
- [ ] **M5-4** Shared phone normalization/validation rule (+966/+971)

## M6 — Appointments core `P0`
- [ ] **M6-1** `BookAppointment` action (transaction + lock + re-check + snapshots)
- [ ] **M6-2** Status-transition action + change logging
- [ ] **M6-3** `/admin/appointments` table (filters, row actions, soft-delete)
- [ ] **M6-4** Manual creation flow (source=manual)
- [ ] **M6-5** Appointment policies (staff sees own; admin sees all)

## M7 — Public Booking `P0`
- [ ] **M7-1** `/book` multi-step wizard (services → staff → date/slot → client → confirm)
- [ ] **M7-2** Submit via `BookAppointment` (source=online, pending)
- [ ] **M7-3** `/book/confirmed/{id}` summary + WhatsApp link + `.ics`
- [ ] **M7-4** `/my-booking` phone lookup + policy-bound self-cancel

## M8 — Admin Ops `P1`
- [ ] **M8-1** `/admin` dashboard KPI cards + today's timeline
- [ ] **M8-2** `/admin/calendar` day/week view, colored blocks
- [ ] **M8-3** Drag-drop reschedule with availability re-check
- [ ] **M8-4** Create appointment from empty calendar slot
- [ ] **M8-5** Reminders (`wa.me` link + `reminder_logs`)

## M9 — Reports `P1`
- [ ] **M9-1** Top services (count via appointment_services)
- [ ] **M9-2** Staff performance (completed count/revenue)
- [ ] **M9-3** Commission per staff (from snapshots)
- [ ] **M9-4** No-shows + date-range filter

## X — Cross-cutting `P1`
- [ ] **X-1** Roles & policies matrix across all routes
- [ ] **X-2** License/domain guard (`settings.license_domain`)
- [ ] **X-3** RTL/mobile audit + empty/error states
- [ ] **X-4** Full green `composer test` (Pint + PHPStan L7 + Pest)
- [ ] **X-5** Demo dataset + setup docs for handoff

---

## How to use this file
- Pull items into a working session by flipping `Todo → Doing`; mirror day status in [`progress.md`](progress.md).
- Add new items with the next free ID under the right module — never renumber existing IDs.
- Close items by checking the box; record shipped user-facing changes in [`changelog.md`](changelog.md).
