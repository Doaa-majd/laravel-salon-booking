<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\BuildAppointmentIcs;
use App\Models\Appointment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams the .ics calendar file for a booked appointment (linked from the
 * public confirmation page). Read-only and unauthenticated — the appointment
 * id is the capability, matching the public confirmation page.
 */
class BookingIcsController extends Controller
{
    public function __invoke(Appointment $appointment, BuildAppointmentIcs $action): Response
    {
        $body = $action->handle($appointment);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="appointment-'.$appointment->id.'.ics"',
        ]);
    }
}
