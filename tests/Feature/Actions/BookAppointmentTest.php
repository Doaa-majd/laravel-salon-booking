<?php

use App\Actions\Appointments\BookAppointment;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Support\Carbon;

const BOOK_TZ = 'Asia/Riyadh';
const BOOK_DATE = '2026-07-14'; // Future relative to the frozen "now".

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', BOOK_TZ));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Create the single salon settings row open 10:00–22:00 every day.
 */
function bookSalon(): Setting
{
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create(['timezone' => BOOK_TZ, 'working_hours' => $hours]);
}

/**
 * Active staff linked to the given services with a 10:00–18:00 schedule on the
 * target date's weekday.
 *
 * @param  array<int, int>  $serviceIds
 */
function bookStaff(array $serviceIds): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync($serviceIds);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(BOOK_DATE, BOOK_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_off' => false,
    ]);

    return $staff;
}

/**
 * Pre-occupy a staff member's time on the target date (salon-local).
 */
function existingAppointment(Staff $staff, string $start, string $end): Appointment
{
    return Appointment::factory()->create([
        'staff_id' => $staff->id,
        'client_id' => Client::factory(),
        'datetime_start' => Carbon::parse(BOOK_DATE.' '.$start, BOOK_TZ),
        'datetime_end' => Carbon::parse(BOOK_DATE.' '.$end, BOOK_TZ),
        'status' => AppointmentStatus::Confirmed,
    ]);
}

test('booking creates an appointment with service snapshots and a computed end', function () {
    bookSalon();
    $client = Client::factory()->create();
    $a = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $b = Service::factory()->create(['duration_minutes' => 30, 'price' => 50]);
    $staff = bookStaff([$a->id, $b->id]);

    $appointment = app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$a->id, $b->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 12:00',
        'status' => AppointmentStatus::Confirmed,
        'source' => AppointmentSource::Manual,
    ]);

    expect($appointment->datetime_start->format('Y-m-d H:i'))->toBe(BOOK_DATE.' 12:00');
    // D = 90 minutes.
    expect($appointment->datetime_end->format('H:i'))->toBe('13:30');
    expect((float) $appointment->price_snapshot)->toBe(150.0);
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointment->source)->toBe(AppointmentSource::Manual);
    expect($appointment->appointmentServices)->toHaveCount(2);

    $snapshot = $appointment->appointmentServices->firstWhere('service_id', $a->id);
    expect((float) $snapshot->price)->toBe(100.0);
    expect($snapshot->duration_minutes)->toBe(60);
});

test('booking a slot already taken for the staff is rejected', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = bookStaff([$service->id]);

    $action = app(BookAppointment::class);

    $action->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 12:00',
    ]);

    expect(fn () => $action->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 12:00',
    ]))->toThrow(SlotUnavailableException::class);

    expect(Appointment::query()->count())->toBe(1);
});

test('booking rejects a time that filled between selection and submit', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = bookStaff([$service->id]);

    // Someone else grabbed 14:00–15:00 already.
    existingAppointment($staff, '14:00', '15:00');

    expect(fn () => app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 14:00',
    ]))->toThrow(SlotUnavailableException::class);
});

test('an adjacent (non-overlapping) time is still bookable', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = bookStaff([$service->id]);

    existingAppointment($staff, '12:00', '13:00');

    $appointment = app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 13:00', // starts exactly when the other ends
    ]);

    expect($appointment->datetime_start->format('H:i'))->toBe('13:00');
});

test('booking a time outside the working window is rejected', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = bookStaff([$service->id]); // 10:00–18:00

    // 17:30 + 60 = 18:30, past the window end.
    expect(fn () => app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 17:30',
    ]))->toThrow(SlotUnavailableException::class);
});

test('booking with any staff resolves the earliest free staff', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $first = bookStaff([$service->id]);
    $second = bookStaff([$service->id]);

    // The earliest-id staff is busy at 10:00, so "any" must resolve to the second.
    existingAppointment($first, '10:00', '11:00');

    $appointment = app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => null,
        'start' => BOOK_DATE.' 10:00',
    ]);

    expect($appointment->staff_id)->toBe($second->id);
});

test('booking with inline client details finds or creates the client by phone', function () {
    bookSalon();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = bookStaff([$service->id]);

    $appointment = app(BookAppointment::class)->handle([
        'client' => ['name' => 'Walk-in', 'phone' => '+966 50 111 2233'],
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 12:00',
    ]);

    $client = Client::query()->where('phone', '+966501112233')->firstOrFail();

    expect($appointment->client_id)->toBe($client->id);
    expect($client->name)->toBe('Walk-in');
});

test('price and duration snapshots stay fixed after the service later changes', function () {
    bookSalon();
    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = bookStaff([$service->id]);

    $appointment = app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'start' => BOOK_DATE.' 12:00',
    ]);

    $service->update(['price' => 999, 'duration_minutes' => 15]);

    $appointment->refresh();
    $snapshot = $appointment->appointmentServices->first();

    expect((float) $appointment->price_snapshot)->toBe(100.0);
    expect((float) $snapshot->price)->toBe(100.0);
    expect($snapshot->duration_minutes)->toBe(60);
    // The stored end reflects the original 60-minute duration, not the new 15.
    expect($appointment->datetime_end->format('H:i'))->toBe('13:00');
});

test('booking with no services is rejected', function () {
    bookSalon();
    $client = Client::factory()->create();

    expect(fn () => app(BookAppointment::class)->handle([
        'client_id' => $client->id,
        'service_ids' => [],
        'staff_id' => null,
        'start' => BOOK_DATE.' 12:00',
    ]))->toThrow(SlotUnavailableException::class);
});
