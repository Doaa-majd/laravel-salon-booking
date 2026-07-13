<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Public\MyBooking;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const MYB_TZ = 'Asia/Riyadh';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', MYB_TZ));
    Setting::factory()->create(['timezone' => MYB_TZ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A booking for the given client at a salon-local datetime.
 */
function mybAppointment(Client $client, string $start, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
{
    return Appointment::factory()->create([
        'client_id' => $client->id,
        'staff_id' => Staff::factory(),
        'datetime_start' => Carbon::parse($start, MYB_TZ),
        'datetime_end' => Carbon::parse($start, MYB_TZ)->addHour(),
        'status' => $status,
    ]);
}

test('guests can open the my-booking page', function () {
    $this->get('/my-booking')->assertOk();
});

test('an invalid phone number is rejected on lookup', function () {
    Livewire::test(MyBooking::class)
        ->set('phone', '12345')
        ->call('lookup')
        ->assertHasErrors('phone')
        ->assertSet('searched', false);
});

test('lookup lists only the caller\'s upcoming active bookings', function () {
    $client = Client::factory()->create(['phone' => '+966501112233']);
    $upcoming = mybAppointment($client, '2026-07-14 12:00');
    $past = mybAppointment($client, '2026-07-10 12:00');
    $cancelled = mybAppointment($client, '2026-07-15 12:00', AppointmentStatus::Cancelled);

    $other = Client::factory()->create(['phone' => '+966509998877']);
    $othersBooking = mybAppointment($other, '2026-07-14 14:00');

    Livewire::test(MyBooking::class)
        ->set('phone', '+966 50 111 2233')
        ->call('lookup')
        ->assertSet('searched', true)
        ->assertViewHas('appointments', fn ($appointments) => $appointments->contains($upcoming)
            && ! $appointments->contains($past)
            && ! $appointments->contains($cancelled)
            && ! $appointments->contains($othersBooking));
});

test('a booking outside the lead window can be cancelled', function () {
    $client = Client::factory()->create(['phone' => '+966501112233']);
    $appointment = mybAppointment($client, '2026-07-14 12:00'); // well beyond the 2h lead

    Livewire::test(MyBooking::class)
        ->set('phone', '+966501112233')
        ->call('lookup')
        ->call('cancel', $appointment->id);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

test('a booking within the lead window cannot be cancelled', function () {
    $client = Client::factory()->create(['phone' => '+966501112233']);
    // Now is 08:00; this starts at 09:00 — only 1h away, inside the 2h lead window.
    $appointment = mybAppointment($client, '2026-07-13 09:00');

    Livewire::test(MyBooking::class)
        ->set('phone', '+966501112233')
        ->call('lookup')
        ->call('cancel', $appointment->id);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('a client cannot cancel another client\'s booking', function () {
    $caller = Client::factory()->create(['phone' => '+966501112233']);
    mybAppointment($caller, '2026-07-14 12:00');

    $other = Client::factory()->create(['phone' => '+966509998877']);
    $othersBooking = mybAppointment($other, '2026-07-14 14:00');

    Livewire::test(MyBooking::class)
        ->set('phone', '+966501112233')
        ->call('lookup')
        ->call('cancel', $othersBooking->id);

    expect($othersBooking->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});
