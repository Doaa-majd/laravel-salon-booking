<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Admin\Calendar;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const CAL_TZ = 'Asia/Riyadh';
const CAL_DATE = '2026-07-15';

function calSalon(): Setting
{
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create(['timezone' => CAL_TZ, 'working_hours' => $hours]);
}

/**
 * @param  array<int, int>  $serviceIds
 */
function calStaff(array $serviceIds = []): Staff
{
    $staff = Staff::factory()->create();

    if ($serviceIds !== []) {
        $staff->services()->sync($serviceIds);
    }

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(CAL_DATE, CAL_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_off' => false,
    ]);

    return $staff;
}

function calAppointment(Staff $staff, Service $service, string $time, ?int $clientId = null): Appointment
{
    $start = Carbon::parse(CAL_DATE.' '.$time, CAL_TZ);

    $appointment = Appointment::factory()->create([
        'client_id' => $clientId ?? Client::factory(),
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
    Carbon::setTestNow(Carbon::parse(CAL_DATE.' 08:00', CAL_TZ));
    calSalon();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('admin can view the calendar', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/calendar')->assertOk();
});

test('a staff user can view the calendar', function () {
    $staff = calStaff();
    $this->actingAs(User::factory()->staff()->create(['staff_id' => $staff->id]));

    $this->get('/admin/calendar')->assertOk();
});

test('guests are redirected from the calendar to login', function () {
    $this->get('/admin/calendar')->assertRedirect(route('login'));
});

test('a staff user only sees their own appointments on the calendar', function () {
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $mine = calStaff([$service->id]);
    $other = calStaff([$service->id]);

    $myAppointment = calAppointment($mine, $service, '12:00');
    $otherAppointment = calAppointment($other, $service, '13:00');

    $this->actingAs(User::factory()->staff()->create(['staff_id' => $mine->id]));

    Livewire::test(Calendar::class)
        ->set('viewMode', 'day')
        ->set('date', CAL_DATE)
        ->assertViewHas('appointments', fn ($appointments) => $appointments->contains($myAppointment)
            && ! $appointments->contains($otherAppointment));
});

test('admin can reschedule an appointment to a free slot from the calendar', function () {
    $this->actingAs(User::factory()->admin()->create());

    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = calStaff([$service->id]);
    $appointment = calAppointment($staff, $service, '12:00');

    Livewire::test(Calendar::class)
        ->set('date', CAL_DATE)
        ->call('openReschedule', $appointment->id)
        ->set('reschedule_date', CAL_DATE)
        ->set('reschedule_time', '15:00')
        ->call('reschedule')
        ->assertHasNoErrors();

    expect($appointment->fresh()->datetime_start->format('H:i'))->toBe('15:00');
});

test('rescheduling to a conflicting slot shows an error and leaves the appointment unchanged', function () {
    $this->actingAs(User::factory()->admin()->create());

    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = calStaff([$service->id]);

    $appointment = calAppointment($staff, $service, '12:00');
    calAppointment($staff, $service, '15:00'); // blocks 15:00–16:00

    Livewire::test(Calendar::class)
        ->set('date', CAL_DATE)
        ->call('openReschedule', $appointment->id)
        ->set('reschedule_date', CAL_DATE)
        ->set('reschedule_time', '15:00')
        ->call('reschedule')
        ->assertHasErrors('reschedule_time');

    expect($appointment->fresh()->datetime_start->format('H:i'))->toBe('12:00');
});

test('admin can create an appointment from the calendar', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = calStaff([$service->id]);

    Livewire::test(Calendar::class)
        ->set('date', CAL_DATE)
        ->call('createAt', $staff->id, CAL_DATE)
        ->set('form_client_id', $client->id)
        ->set('form_service_ids', [$service->id])
        ->set('form_staff_id', $staff->id)
        ->set('form_time', '11:00')
        ->set('form_status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::query()->where('staff_id', $staff->id)->count())->toBe(1);
});
