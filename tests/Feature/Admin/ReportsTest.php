<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Admin\Reports;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const REPT_TZ = 'Asia/Riyadh';

function reptSalon(): Setting
{
    return Setting::factory()->create(['timezone' => REPT_TZ]);
}

function reptCompleted(Staff $staff, Service $service, string $date): Appointment
{
    $start = Carbon::parse($date.' 12:00', REPT_TZ);

    $appointment = Appointment::factory()->create([
        'client_id' => Client::factory(),
        'staff_id' => $staff->id,
        'datetime_start' => $start,
        'datetime_end' => $start->copy()->addHour(),
        'status' => AppointmentStatus::Completed,
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
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00', REPT_TZ));
    reptSalon();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('admin can view the reports page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.reports.index'))->assertOk();
});

test('staff cannot view the reports page', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(route('admin.reports.index'))->assertForbidden();
});

test('guests are redirected from reports to login', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
});

test('reports default to the current month to date', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Reports::class)
        ->assertSet('dateFrom', '2026-07-01')
        ->assertSet('dateTo', '2026-07-15');
});

test('reports surface aggregates for the selected range', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create(['name' => 'Reem', 'commission_percent' => 10]);
    $service = Service::factory()->create(['name' => 'Facial', 'price' => 100]);

    reptCompleted($staff, $service, '2026-07-10');
    reptCompleted($staff, $service, '2026-07-11');

    Livewire::test(Reports::class)
        ->set('dateFrom', '2026-07-01')
        ->set('dateTo', '2026-07-31')
        ->assertViewHas('totals', fn ($t) => $t['completed_count'] === 2
            && $t['revenue'] === 200.0
            && $t['commission'] === 20.0)
        ->assertViewHas('topServices', fn ($rows) => count($rows) === 1
            && $rows[0]['name'] === 'Facial'
            && $rows[0]['bookings'] === 2)
        ->assertSee('Facial')
        ->assertSee('Reem');
});

test('an inverted date range is normalised rather than returning nothing', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create(['commission_percent' => 0]);
    $service = Service::factory()->create(['price' => 100]);
    reptCompleted($staff, $service, '2026-07-10');

    // from > to: the component swaps them internally.
    Livewire::test(Reports::class)
        ->set('dateFrom', '2026-07-31')
        ->set('dateTo', '2026-07-01')
        ->assertViewHas('totals', fn ($t) => $t['completed_count'] === 1);
});
