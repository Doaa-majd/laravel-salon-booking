<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class SaveStaffSchedule
{
    /**
     * Upsert a staff member's full weekly schedule (one row per weekday).
     *
     * Each entry is keyed by weekday (0 = Sunday .. 6 = Saturday) and
     * persisted via the unique (staff_id, weekday) constraint, so saving is
     * idempotent and cannot create duplicate rows for a weekday.
     *
     * @param  array<int, array{start_time: string, end_time: string, is_off?: bool}>  $schedule
     */
    public function handle(Staff $staff, array $schedule): void
    {
        DB::transaction(function () use ($staff, $schedule): void {
            foreach ($schedule as $weekday => $row) {
                $staff->schedules()->updateOrCreate(
                    ['weekday' => (int) $weekday],
                    [
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'is_off' => (bool) ($row['is_off'] ?? false),
                    ],
                );
            }
        });
    }
}
