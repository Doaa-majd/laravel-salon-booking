<?php

use App\Actions\Appointments\BuildAppointmentIcs;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Carbon;

const ICS_TZ = 'Asia/Riyadh';

/**
 * A booked appointment (12:00–13:00 salon-local) with one service snapshot.
 */
function icsAppointment(): Appointment
{
    Setting::factory()->create(['timezone' => ICS_TZ, 'salon_name' => 'Glow Studio', 'address' => 'King Rd, Riyadh']);

    $service = Service::factory()->create(['name' => 'Haircut', 'duration_minutes' => 60, 'price' => 100]);
    $staff = Staff::factory()->create(['name' => 'Mona']);

    $appointment = Appointment::factory()->create([
        'client_id' => Client::factory(),
        'staff_id' => $staff->id,
        'datetime_start' => Carbon::parse('2026-07-14 12:00', ICS_TZ),
        'datetime_end' => Carbon::parse('2026-07-14 13:00', ICS_TZ),
        'status' => AppointmentStatus::Pending,
        'price_snapshot' => 100,
    ]);

    $appointment->appointmentServices()->create([
        'service_id' => $service->id,
        'price' => 100,
        'duration_minutes' => 60,
    ]);

    return $appointment;
}

test('the confirmation page is publicly viewable', function () {
    $appointment = icsAppointment();

    $this->get(route('book.confirmed', $appointment))
        ->assertOk()
        ->assertSee('#'.$appointment->id)
        ->assertSee('Haircut');
});

test('the ics endpoint returns a calendar file with UTC times', function () {
    $appointment = icsAppointment();

    $response = $this->get(route('book.ics', $appointment));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/calendar');

    $body = $response->getContent();

    // 12:00 Asia/Riyadh (UTC+3) → 09:00Z; 13:00 → 10:00Z.
    expect($body)->toContain('DTSTART:20260714T090000Z');
    expect($body)->toContain('DTEND:20260714T100000Z');
    expect($body)->toContain('BEGIN:VEVENT');
    expect($body)->toContain('Haircut');
});

test('the ics builder produces a well-formed event', function () {
    $appointment = icsAppointment();

    $ics = app(BuildAppointmentIcs::class)->handle($appointment);

    expect($ics)->toStartWith('BEGIN:VCALENDAR');
    expect($ics)->toContain('END:VCALENDAR');
    expect($ics)->toContain('UID:appointment-'.$appointment->id.'@');
    expect($ics)->toContain('LOCATION:King Rd\, Riyadh'); // comma escaped per RFC 5545
    expect($ics)->toContain("\r\n"); // CRLF line endings
});
