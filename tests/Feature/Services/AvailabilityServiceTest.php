<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

const SALON_TZ = 'Asia/Riyadh';
const TARGET_DATE = '2026-07-14'; // A future day relative to the frozen "now".

beforeEach(function () {
    // Freeze salon-local "now" so weekday and buffer filtering are deterministic.
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', SALON_TZ));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Create the single salon settings row. Working hours default to 10:00–22:00
 * open every day; pass an override to close/adjust specific weekdays.
 *
 * @param  array<int, array{open: string, close: string, closed: bool}>|null  $workingHours
 */
function salon(?array $workingHours = null): Setting
{
    $hours = $workingHours ?? collect(range(0, 6))
        ->mapWithKeys(fn (int $weekday) => [$weekday => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create([
        'timezone' => SALON_TZ,
        'working_hours' => $hours,
    ]);
}

/**
 * The 0–6 weekday of the target date in the salon timezone.
 */
function targetWeekday(string $date = TARGET_DATE): int
{
    return Carbon::parse($date, SALON_TZ)->dayOfWeek;
}

/**
 * Create an active staff member linked to the given services with a weekly
 * schedule row for the target date's weekday.
 *
 * @param  array<int, int>  $serviceIds
 */
function staffWith(array $serviceIds, string $start = '10:00', string $end = '18:00', bool $off = false, string $date = TARGET_DATE): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync($serviceIds);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => targetWeekday($date),
        'start_time' => $start,
        'end_time' => $end,
        'is_off' => $off,
    ]);

    return $staff;
}

/**
 * Book an appointment for a staff member on the target date (salon-local times).
 */
function appointmentFor(Staff $staff, string $start, string $end, AppointmentStatus $status = AppointmentStatus::Confirmed, string $date = TARGET_DATE): Appointment
{
    return Appointment::factory()->create([
        'staff_id' => $staff->id,
        'client_id' => Client::factory(),
        'datetime_start' => Carbon::parse("$date $start", SALON_TZ),
        'datetime_end' => Carbon::parse("$date $end", SALON_TZ),
        'status' => $status,
    ]);
}

/**
 * The list of "H:i" start times returned for the given inputs.
 *
 * @param  array<int, int>  $serviceIds
 * @return array<int, string>
 */
function slotTimes(array $serviceIds, ?int $staffId = null, string $date = TARGET_DATE, ?AvailabilityService $service = null): array
{
    $service ??= new AvailabilityService;

    return array_column($service->availableSlots($serviceIds, $staffId, $date), 'time');
}

test('the interval overlap helper treats touching boundaries as free', function () {
    $a = Carbon::parse('2026-07-14 12:00', SALON_TZ);
    $b = Carbon::parse('2026-07-14 13:00', SALON_TZ);
    $c = Carbon::parse('2026-07-14 14:00', SALON_TZ);

    // [12,13) and [13,14) touch but do not overlap.
    expect(AvailabilityService::intervalsOverlap($a, $b, $b, $c))->toBeFalse();
    // [12,14) and [13,14) do overlap.
    expect(AvailabilityService::intervalsOverlap($a, $c, $b, $c))->toBeTrue();
});

test('no slots when the salon is closed that weekday', function () {
    $weekday = targetWeekday();
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => $d === $weekday]])
        ->all();
    salon($hours);

    $service = Service::factory()->create(['duration_minutes' => 60]);
    staffWith([$service->id]);

    expect(slotTimes([$service->id]))->toBe([]);
});

test('no slots when the staff member is off that weekday', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id], off: true);

    expect(slotTimes([$service->id], $staff->id))->toBe([]);
});

test('a full-day time off removes the whole day', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]);

    StaffTimeOff::factory()->create([
        'staff_id' => $staff->id,
        'date' => TARGET_DATE,
    ]);

    expect(slotTimes([$service->id], $staff->id))->toBe([]);
});

test('a partial time off removes only the overlapping windows', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]); // 10:00–18:00

    StaffTimeOff::factory()->partial('13:00', '15:00')->create([
        'staff_id' => $staff->id,
        'date' => TARGET_DATE,
    ]);

    $times = slotTimes([$service->id], $staff->id);

    // Ends exactly at the leave start — still free.
    expect($times)->toContain('12:00');
    // Would run into the leave — blocked.
    expect($times)->not->toContain('12:15');
    expect($times)->not->toContain('13:00');
    expect($times)->not->toContain('14:00');
    // Resumes at the leave end.
    expect($times)->toContain('15:00');
});

test('an existing appointment blocks overlapping candidates but not adjacent ones', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]);

    appointmentFor($staff, '12:00', '13:00');

    $times = slotTimes([$service->id], $staff->id);

    expect($times)->toContain('11:00'); // ends 12:00 — touches, allowed
    expect($times)->not->toContain('11:15'); // ends 12:15 — overlaps
    expect($times)->not->toContain('12:00'); // overlaps
    expect($times)->toContain('13:00'); // starts at appointment end — allowed
});

test('a cancelled appointment does not block slots', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]);

    appointmentFor($staff, '12:00', '13:00', AppointmentStatus::Cancelled);

    expect(slotTimes([$service->id], $staff->id))->toContain('12:00');
});

test('multiple services sum their durations', function () {
    salon();
    $a = Service::factory()->create(['duration_minutes' => 60]);
    $b = Service::factory()->create(['duration_minutes' => 30]);
    $staff = staffWith([$a->id, $b->id]); // 10:00–18:00

    $times = slotTimes([$a->id, $b->id], $staff->id);

    // D = 90; last start that fits before 18:00 is 16:30.
    expect($times)->toContain('16:30');
    expect($times)->not->toContain('16:45');
});

test('no slots when the total duration exceeds the working window', function () {
    salon();
    $a = Service::factory()->create(['duration_minutes' => 60]);
    $b = Service::factory()->create(['duration_minutes' => 30]);
    // Only a one-hour window, but D = 90.
    $staff = staffWith([$a->id, $b->id], start: '10:00', end: '11:00');

    expect(slotTimes([$a->id, $b->id], $staff->id))->toBe([]);
});

test('any-staff suggests the earliest-id free staff for each time', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $first = staffWith([$service->id]);
    $second = staffWith([$service->id]);

    $slots = (new AvailabilityService)->availableSlots([$service->id], null, TARGET_DATE);
    $ten = collect($slots)->firstWhere('time', '10:00');

    expect($ten)->not->toBeNull();
    expect($ten['staff_id'])->toBe($first->id);
    // Each time appears exactly once even though two staff are free.
    expect(collect($slots)->where('time', '10:00'))->toHaveCount(1);
});

test('any-staff falls back to another free staff when the first is busy', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $first = staffWith([$service->id]);
    $second = staffWith([$service->id]);

    // First is booked 10:00–11:00, so the 10:00 slot must resolve to the second.
    appointmentFor($first, '10:00', '11:00');

    $slots = (new AvailabilityService)->availableSlots([$service->id], null, TARGET_DATE);
    $ten = collect($slots)->firstWhere('time', '10:00');

    expect($ten['staff_id'])->toBe($second->id);
});

test('the buffer filters out slots too close to now', function () {
    // Re-freeze "now" to a same-day time so the buffer bites.
    Carbon::setTestNow(Carbon::parse('2026-07-13 11:30', SALON_TZ));

    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id], date: '2026-07-13'); // schedule for today

    // Buffer 60 min → earliest bookable start is 12:30.
    $times = slotTimes([$service->id], $staff->id, '2026-07-13');

    expect($times)->not->toContain('12:00');
    expect($times)->not->toContain('12:15');
    expect($times[0])->toBe('12:30');
});

test('candidate generation respects the window edges and step', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]); // 10:00–18:00, step 15, D 60

    $times = slotTimes([$service->id], $staff->id);

    expect($times[0])->toBe('10:00');
    // Last start whose end (18:00) fits the window.
    expect(end($times))->toBe('17:00');
    // 10:00 → 17:00 in 15-min steps = 29 candidates.
    expect($times)->toHaveCount(29);
});

test('a specific staff who does not provide the service yields no slots', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $other = Service::factory()->create(['duration_minutes' => 60]);
    // Staff only provides the OTHER service.
    $staff = staffWith([$other->id]);

    expect(slotTimes([$service->id], $staff->id))->toBe([]);
});

test('no slots when the service list is empty', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    staffWith([$service->id]);

    expect((new AvailabilityService)->availableSlots([], null, TARGET_DATE))->toBe([]);
});

test('an inactive staff member is never a candidate', function () {
    salon();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = staffWith([$service->id]);
    $staff->update(['is_active' => false]);

    expect(slotTimes([$service->id], $staff->id))->toBe([]);
    expect(slotTimes([$service->id]))->toBe([]);
});
