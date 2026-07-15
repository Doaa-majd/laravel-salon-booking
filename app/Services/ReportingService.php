<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Staff;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting aggregates over the appointment history (analysis doc §11).
 *
 * Every money figure is derived from the immutable price snapshots stored on
 * appointment_services / appointments ([ADR-0006]) — never from live service
 * prices — so editing a service never changes historical reports or commission.
 * Revenue, staff performance, commission, and top-service demand all measure
 * *completed* work; no-shows are counted separately. All rows are filtered by a
 * closed [from, to] date range compared against the appointment's stored
 * salon-local wall-clock start date (whereDate, consistent with [ADR-0012]).
 */
class ReportingService
{
    /**
     * Most-requested services within the range: how many times each was part of a
     * completed appointment, and the snapshot revenue it produced. Ordered by
     * booking count (then revenue) descending.
     *
     * @return array<int, array{service_id: int, name: string, bookings: int, revenue: float}>
     */
    public function topServices(string $from, string $to): array
    {
        return $this->completedServiceLines($from, $to)
            ->join('services as s', 's.id', '=', 'aps.service_id')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('bookings')
            ->orderByDesc('revenue')
            ->select(
                's.id as service_id',
                's.name as name',
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(aps.price) as revenue'),
            )
            ->get()
            ->map(fn (object $row): array => [
                'service_id' => (int) $row->service_id,
                'name' => (string) $row->name,
                'bookings' => (int) $row->bookings,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * Per-staff performance for the range: completed appointment count, snapshot
     * revenue, and commission (= commission_percent × completed service value).
     * Every non-deleted staff member appears (zeros included), ordered by revenue
     * then name so top performers surface first.
     *
     * @return array<int, array{staff_id: int, name: string, commission_percent: float, completed_count: int, revenue: float, commission: float}>
     */
    public function staffPerformance(string $from, string $to): array
    {
        $aggregates = $this->completedServiceLines($from, $to)
            ->groupBy('a.staff_id')
            ->select(
                'a.staff_id as staff_id',
                DB::raw('COUNT(DISTINCT a.id) as completed_count'),
                DB::raw('SUM(aps.price) as revenue'),
            )
            ->get()
            ->keyBy('staff_id');

        return Staff::query()
            ->orderBy('name')
            ->get()
            ->map(function (Staff $staff) use ($aggregates): array {
                $row = $aggregates->get($staff->id);
                $revenue = (float) ($row->revenue ?? 0);
                $percent = (float) $staff->commission_percent;

                return [
                    'staff_id' => $staff->id,
                    'name' => $staff->name,
                    'commission_percent' => $percent,
                    'completed_count' => (int) ($row->completed_count ?? 0),
                    'revenue' => $revenue,
                    'commission' => round($percent / 100 * $revenue, 2),
                ];
            })
            ->sortBy([
                ['revenue', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Count of no-show appointments within the range.
     */
    public function noShowCount(string $from, string $to): int
    {
        return Appointment::query()
            ->where('status', AppointmentStatus::NoShow->value)
            ->whereDate('datetime_start', '>=', $from)
            ->whereDate('datetime_start', '<=', $to)
            ->count();
    }

    /**
     * Headline totals for the range (completed appointments, snapshot revenue,
     * total commission across all staff, and no-shows).
     *
     * @return array{completed_count: int, revenue: float, commission: float, no_shows: int}
     */
    public function totals(string $from, string $to): array
    {
        $staff = $this->staffPerformance($from, $to);

        return [
            'completed_count' => array_sum(array_column($staff, 'completed_count')),
            'revenue' => round((float) array_sum(array_column($staff, 'revenue')), 2),
            'commission' => round((float) array_sum(array_column($staff, 'commission')), 2),
            'no_shows' => $this->noShowCount($from, $to),
        ];
    }

    /**
     * Base query over appointment_services joined to their completed, non-deleted
     * appointments within the range — the shared source for the snapshot-based
     * aggregates above.
     */
    protected function completedServiceLines(string $from, string $to): Builder
    {
        return DB::table('appointment_services as aps')
            ->join('appointments as a', 'a.id', '=', 'aps.appointment_id')
            ->where('a.status', AppointmentStatus::Completed->value)
            ->whereNull('a.deleted_at')
            ->whereDate('a.datetime_start', '>=', $from)
            ->whereDate('a.datetime_start', '<=', $to);
    }
}
