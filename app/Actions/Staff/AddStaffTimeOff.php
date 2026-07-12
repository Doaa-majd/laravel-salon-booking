<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\StaffTimeOff;

class AddStaffTimeOff
{
    /**
     * Record a specific-date time-off entry for a staff member.
     *
     * An empty start/end represents a full-day leave; a filled window
     * represents a partial-day leave used by the availability engine's
     * subtraction step.
     *
     * @param  array{timeOffDate: string, timeOffStart?: string|null, timeOffEnd?: string|null, timeOffReason?: string|null}  $data
     */
    public function handle(Staff $staff, array $data): StaffTimeOff
    {
        return $staff->timeOff()->create([
            'date' => $data['timeOffDate'],
            'start_time' => ($data['timeOffStart'] ?? null) ?: null,
            'end_time' => ($data['timeOffEnd'] ?? null) ?: null,
            'reason' => ($data['timeOffReason'] ?? null) ?: null,
        ]);
    }
}
