<?php

namespace App\Actions\Appointments;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Move an existing appointment to a new start time (and optionally a new staff
 * member), re-checking availability under the same locked-transaction guard as
 * BookAppointment (ADR-0014) — the client's proposed slot is never trusted.
 *
 * The appointment keeps its immutable price/duration snapshots (ADR-0006): only
 * its datetimes (and possibly staff) change, and the new end is derived from the
 * snapshotted service durations, never recomputed from live services. The
 * appointment being moved is excluded from the busy intervals so it never blocks
 * its own new slot. Datetimes stay salon-local wall clock (ADR-0012).
 */
class RescheduleAppointment
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Reschedule the appointment to $start, keeping its current staff unless a
     * new $staffId is given.
     *
     * @throws SlotUnavailableException
     */
    public function handle(Appointment $appointment, string|Carbon $start, ?int $staffId = null): Appointment
    {
        $timezone = Setting::current()->timezone;
        $start = $start instanceof Carbon
            ? $start->copy()->setTimezone($timezone)
            : Carbon::parse($start, $timezone);

        $date = $start->toDateString();
        $time = $start->format('H:i');

        // Services and their durations are fixed by the original booking.
        $serviceIds = $appointment->services->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $duration = (int) $appointment->appointmentServices->sum('duration_minutes');

        $targetStaffId = $staffId ?? $appointment->staff_id;

        return DB::transaction(function () use ($appointment, $serviceIds, $duration, $date, $time, $start, $targetStaffId): Appointment {
            // Serialize concurrent writes for the target staff (ADR-0014).
            /** @var Staff|null $staff */
            $staff = Staff::query()->whereKey($targetStaffId)->lockForUpdate()->first();

            if ($staff === null) {
                throw new SlotUnavailableException('The selected staff member is unavailable.');
            }

            // Authoritative re-check, ignoring this appointment's own interval.
            if (! $this->slotIsFree($serviceIds, $staff->id, $date, $time, $appointment->id)) {
                throw new SlotUnavailableException('The selected time is no longer available.');
            }

            $from = $appointment->datetime_start->format('Y-m-d H:i');

            $appointment->update([
                'staff_id' => $staff->id,
                'datetime_start' => $start,
                'datetime_end' => $start->copy()->addMinutes($duration),
            ]);

            Log::info('appointment.rescheduled', [
                'appointment_id' => $appointment->id,
                'staff_id' => $staff->id,
                'from' => $from,
                'to' => $start->toDateTimeString(),
            ]);

            return $appointment;
        });
    }

    /**
     * Whether the requested "H:i" time is offered for this staff, ignoring the
     * appointment being moved.
     *
     * @param  array<int, int>  $serviceIds
     */
    private function slotIsFree(array $serviceIds, int $staffId, string $date, string $time, int $excludeAppointmentId): bool
    {
        foreach ($this->availability->availableSlots($serviceIds, $staffId, $date, $excludeAppointmentId) as $slot) {
            if ($slot['time'] === $time) {
                return true;
            }
        }

        return false;
    }
}
