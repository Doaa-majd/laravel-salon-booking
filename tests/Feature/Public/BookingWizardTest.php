<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Livewire\Public\BookingWizard;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const WIZ_TZ = 'Asia/Riyadh';
const WIZ_DATE = '2026-07-14'; // Future relative to the frozen "now".

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', WIZ_TZ));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Single salon settings row, open 10:00–22:00 every day.
 */
function wizSalon(): Setting
{
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create(['timezone' => WIZ_TZ, 'working_hours' => $hours]);
}

/**
 * Active staff linked to the given services with a 10:00–18:00 schedule on the
 * target date's weekday.
 *
 * @param  array<int, int>  $serviceIds
 */
function wizStaff(array $serviceIds): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync($serviceIds);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(WIZ_DATE, WIZ_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_off' => false,
    ]);

    return $staff;
}

test('guests can open the booking wizard', function () {
    wizSalon();

    $this->get('/book')->assertOk();
});

test('the full wizard happy path books an online appointment', function () {
    wizSalon();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = wizStaff([$service->id]);

    $component = Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$service->id])
        ->call('next')
        ->assertSet('step', 2)
        ->set('staffId', $staff->id)
        ->call('next')
        ->assertSet('step', 3)
        ->set('date', WIZ_DATE)
        ->call('selectSlot', '12:00')
        ->call('next')
        ->assertSet('step', 4)
        ->set('name', 'Sara')
        ->set('phone', '+966 50 111 2233')
        ->call('next')
        ->assertSet('step', 5)
        ->call('submit')
        ->assertHasNoErrors();

    $appointment = Appointment::query()->firstOrFail();
    $component->assertRedirect(route('book.confirmed', $appointment));

    expect($appointment->source)->toBe(AppointmentSource::Online);
    expect($appointment->status)->toBe(AppointmentStatus::Pending);
    expect($appointment->staff_id)->toBe($staff->id);
    expect($appointment->datetime_start->format('Y-m-d H:i'))->toBe(WIZ_DATE.' 12:00');
    expect($appointment->services->pluck('id')->all())->toBe([$service->id]);

    // The guest was found-or-created by normalized phone.
    $client = Client::query()->where('phone', '+966501112233')->firstOrFail();
    expect($appointment->client_id)->toBe($client->id);
    expect($client->name)->toBe('Sara');
});

test('the staff step only lists staff who provide all selected services', function () {
    wizSalon();
    $a = Service::factory()->create();
    $b = Service::factory()->create();

    $qualified = wizStaff([$a->id, $b->id]);
    $partial = wizStaff([$a->id]); // provides only one of the two

    Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$a->id, $b->id])
        ->assertViewHas('staffOptions', fn ($options) => $options->contains($qualified)
            && ! $options->contains($partial));
});

test('choosing any staff resolves a concrete staff member on submit', function () {
    wizSalon();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = wizStaff([$service->id]);

    Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$service->id])
        ->set('staffId', null)
        ->set('date', WIZ_DATE)
        ->call('selectSlot', '12:00')
        ->set('name', 'Nada')
        ->set('phone', '+966501112233')
        ->call('submit')
        ->assertHasNoErrors();

    $appointment = Appointment::query()->firstOrFail();
    expect($appointment->staff_id)->toBe($staff->id);
});

test('changing services drops a now-unqualified selected staff member', function () {
    wizSalon();
    $a = Service::factory()->create();
    $b = Service::factory()->create();
    $staff = wizStaff([$a->id]); // provides only service A

    Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$a->id])
        ->set('staffId', $staff->id)
        ->set('serviceIds', [$a->id, $b->id]) // staff no longer qualifies
        ->assertSet('staffId', null);
});

test('a slot that fills between selection and submit is rejected by the re-check', function () {
    wizSalon();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = wizStaff([$service->id]);

    // The frontend is never trusted: force a time then have it taken before submit.
    Appointment::factory()->create([
        'staff_id' => $staff->id,
        'client_id' => Client::factory(),
        'datetime_start' => Carbon::parse(WIZ_DATE.' 12:00', WIZ_TZ),
        'datetime_end' => Carbon::parse(WIZ_DATE.' 13:00', WIZ_TZ),
        'status' => AppointmentStatus::Confirmed,
    ]);

    Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$service->id])
        ->set('staffId', $staff->id)
        ->set('date', WIZ_DATE)
        ->call('selectSlot', '12:00')
        ->set('name', 'Late')
        ->set('phone', '+966501112233')
        ->call('submit')
        ->assertHasErrors('time')
        ->assertSet('step', 3);

    // Only the pre-existing appointment exists — nothing double-booked.
    expect(Appointment::query()->count())->toBe(1);
});

test('an invalid phone number blocks the details step', function () {
    wizSalon();
    $service = Service::factory()->create();
    wizStaff([$service->id]);

    Livewire::test(BookingWizard::class)
        ->set('serviceIds', [$service->id])
        ->set('date', WIZ_DATE)
        ->call('selectSlot', '12:00')
        ->set('step', 4)
        ->set('name', 'Bad Phone')
        ->set('phone', '12345')
        ->call('next')
        ->assertHasErrors('phone')
        ->assertSet('step', 4);
});
