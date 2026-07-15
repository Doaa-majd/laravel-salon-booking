<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const DASH_TZ = 'Asia/Riyadh';

function dashSalon(): Setting
{
    return Setting::factory()->create(['timezone' => DASH_TZ]);
}

/**
 * An appointment on $date at $time with the given status and price.
 */
function dashAppointment(string $date, string $time, AppointmentStatus $status, float $price): Appointment
{
    $start = Carbon::parse($date.' '.$time, DASH_TZ);

    return Appointment::factory()->create([
        'datetime_start' => $start,
        'datetime_end' => $start->copy()->addHour(),
        'status' => $status,
        'price_snapshot' => $price,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00', DASH_TZ));
    dashSalon();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('admin can view the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin')->assertOk();
});

test('staff cannot view the admin dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
});

test('guests are redirected from the dashboard to login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('dashboard aggregates today and excludes cancelled from count and revenue', function () {
    $this->actingAs(User::factory()->admin()->create());

    dashAppointment('2026-07-15', '11:00', AppointmentStatus::Confirmed, 100);
    dashAppointment('2026-07-15', '14:00', AppointmentStatus::Pending, 200);
    dashAppointment('2026-07-15', '16:00', AppointmentStatus::NoShow, 300);
    dashAppointment('2026-07-15', '12:00', AppointmentStatus::Cancelled, 999); // excluded
    dashAppointment('2026-07-16', '11:00', AppointmentStatus::Confirmed, 500);  // tomorrow

    Livewire::test(Dashboard::class)
        ->assertViewHas('todayCount', 3)
        ->assertViewHas('noShows', 1)
        ->assertViewHas('revenue', fn ($revenue) => (float) $revenue === 600.0)
        ->assertViewHas('nextUp', fn ($nextUp) => $nextUp !== null
            && $nextUp->datetime_start->format('H:i') === '11:00');
});

test('dashboard next-up is empty when nothing remains today', function () {
    $this->actingAs(User::factory()->admin()->create());

    // Only a past (already started) appointment remains today.
    dashAppointment('2026-07-15', '08:00', AppointmentStatus::Completed, 100);

    Livewire::test(Dashboard::class)
        ->assertViewHas('nextUp', fn ($nextUp) => $nextUp === null);
});
