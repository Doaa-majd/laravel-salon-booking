<?php

use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Support\Carbon;

const RS_TZ = 'Asia/Riyadh';
const RS_DATE = '2026-07-15';

/**
 * Salon open every day 10:00–22:00.
 */
function rsSalon(): Setting
{
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create(['timezone' => RS_TZ, 'working_hours' => $hours]);
}

/**
 * Active staff linked to $serviceIds with a 10:00–18:00 schedule on RS_DATE.
 *
 * @param  array<int, int>  $serviceIds
 */
function rsStaff(array $serviceIds): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync($serviceIds);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(RS_DATE, RS_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_off' => false,
    ]);

    return $staff;
}

/**
 * Create an appointment at $time with a snapshotted service.
 */
function rsAppointment(Staff $staff, Service $service, string $time): Appointment
{
    $start = Carbon::parse(RS_DATE.' '.$time, RS_TZ);

    $appointment = Appointment::factory()->create([
        'client_id' => Client::factory(),
        'staff_id' => $staff->id,
        'datetime_start' => $start,
        'datetime_end' => $start->copy()->addMinutes($service->duration_minutes),
        'status' => AppointmentStatus::Confirmed,
        'price_snapshot' => $service->price,
    ]);

    $appointment->appointmentServices()->create([
        'service_id' => $service->id,
        'price' => $service->price,
        'duration_minutes' => $service->duration_minutes,
    ]);

    return $appointment;
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse(RS_DATE.' 08:00', RS_TZ));
    rsSalon();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('reschedules an appointment to a free slot', function () {
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = rsStaff([$service->id]);
    $appointment = rsAppointment($staff, $service, '12:00');

    app(RescheduleAppointment::class)->handle($appointment, RS_DATE.' 14:00');

    $appointment->refresh();
    expect($appointment->datetime_start->format('H:i'))->toBe('14:00');
    expect($appointment->datetime_end->format('H:i'))->toBe('15:00');
    expect($appointment->staff_id)->toBe($staff->id);
});

test('rescheduling into another appointment is rejected and leaves the original unchanged', function () {
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = rsStaff([$service->id]);

    $appointment = rsAppointment($staff, $service, '12:00');
    rsAppointment($staff, $service, '14:00'); // Blocks 14:00–15:00.

    expect(fn () => app(RescheduleAppointment::class)->handle($appointment, RS_DATE.' 14:00'))
        ->toThrow(SlotUnavailableException::class);

    expect($appointment->fresh()->datetime_start->format('H:i'))->toBe('12:00');
});

test('an appointment can be nudged within its own current interval', function () {
    // Its own booking must not block the new overlapping slot (exclude-self).
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = rsStaff([$service->id]);
    $appointment = rsAppointment($staff, $service, '12:00');

    app(RescheduleAppointment::class)->handle($appointment, RS_DATE.' 12:15');

    expect($appointment->fresh()->datetime_start->format('H:i'))->toBe('12:15');
});

test('rescheduling can move the appointment to another staff member', function () {
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staffA = rsStaff([$service->id]);
    $staffB = rsStaff([$service->id]);
    $appointment = rsAppointment($staffA, $service, '12:00');

    app(RescheduleAppointment::class)->handle($appointment, RS_DATE.' 14:00', $staffB->id);

    $appointment->refresh();
    expect($appointment->staff_id)->toBe($staffB->id);
    expect($appointment->datetime_start->format('H:i'))->toBe('14:00');
});

test('rescheduling preserves the original duration snapshot after a later service edit', function () {
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = rsStaff([$service->id]);
    $appointment = rsAppointment($staff, $service, '12:00');

    // The live service duration changes, but the snapshot (60) governs the end.
    $service->update(['duration_minutes' => 30]);

    app(RescheduleAppointment::class)->handle($appointment, RS_DATE.' 14:00');

    $appointment->refresh();
    expect($appointment->datetime_end->format('H:i'))->toBe('15:00');
});
