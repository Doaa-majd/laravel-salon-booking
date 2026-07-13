<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

/**
 * Move an appointment through its allowed status lifecycle (see the transition
 * rules on AppointmentStatus). Every change is logged, per the Database Rules.
 * Cancellation is a status change, never a delete — the record is retained.
 */
class ChangeAppointmentStatus
{
    /**
     * @throws InvalidStatusTransitionException
     */
    public function handle(Appointment $appointment, AppointmentStatus $to): Appointment
    {
        $from = $appointment->status;

        if ($from === $to) {
            return $appointment;
        }

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStatusTransitionException($from, $to);
        }

        $appointment->update(['status' => $to]);

        Log::info('appointment.status_changed', [
            'appointment_id' => $appointment->id,
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $appointment;
    }
}
