<?php

use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Support\Carbon;

const API_TZ = 'Asia/Riyadh';
const API_DATE = '2026-07-14';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', API_TZ));

    Setting::factory()->create([
        'timezone' => API_TZ,
        'working_hours' => collect(range(0, 6))
            ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
            ->all(),
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * An active staff member linked to $service with a schedule for API_DATE.
 */
function apiStaff(Service $service, string $start = '10:00', string $end = '18:00'): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync([$service->id]);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(API_DATE, API_TZ)->dayOfWeek,
        'start_time' => $start,
        'end_time' => $end,
    ]);

    return $staff;
}

test('the endpoint returns available slots as json', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $staff = apiStaff($service);

    $response = $this->getJson(route('api.available-slots', [
        'service_ids' => (string) $service->id,
        'staff_id' => $staff->id,
        'date' => API_DATE,
    ]));

    $response->assertOk()
        ->assertJsonPath('date', API_DATE)
        ->assertJsonPath('slots.0.time', '10:00')
        ->assertJsonPath('slots.0.staff_id', $staff->id);
});

test('the endpoint accepts comma-separated service ids', function () {
    $a = Service::factory()->create(['duration_minutes' => 60]);
    $b = Service::factory()->create(['duration_minutes' => 30]);

    $staff = Staff::factory()->create();
    $staff->services()->sync([$a->id, $b->id]);
    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(API_DATE, API_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
    ]);

    $response = $this->getJson(route('api.available-slots', [
        'service_ids' => "{$a->id},{$b->id}",
        'date' => API_DATE,
    ]));

    // D = 90 → last start is 16:30.
    $response->assertOk();
    $times = array_column($response->json('slots'), 'time');
    expect($times)->toContain('16:30')->not->toContain('16:45');
});

test('the endpoint validates required parameters', function () {
    $this->getJson(route('api.available-slots'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service_ids', 'date']);
});

test('the endpoint rejects a malformed date', function () {
    $service = Service::factory()->create();

    $this->getJson(route('api.available-slots', [
        'service_ids' => (string) $service->id,
        'date' => 'not-a-date',
    ]))->assertJsonValidationErrors(['date']);
});
