<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Admin\Appointments as AppointmentsComponent;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

const APPT_TZ = 'Asia/Riyadh';
const APPT_DATE = '2026-07-14';

/**
 * Create the single salon settings row open every day 10:00–22:00.
 */
function apptSalon(): Setting
{
    $hours = collect(range(0, 6))
        ->mapWithKeys(fn (int $d) => [$d => ['open' => '10:00', 'close' => '22:00', 'closed' => false]])
        ->all();

    return Setting::factory()->create(['timezone' => APPT_TZ, 'working_hours' => $hours]);
}

/**
 * Active staff linked to the given services with a 10:00–18:00 schedule.
 *
 * @param  array<int, int>  $serviceIds
 */
function apptStaff(array $serviceIds): Staff
{
    $staff = Staff::factory()->create();
    $staff->services()->sync($serviceIds);

    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => Carbon::parse(APPT_DATE, APPT_TZ)->dayOfWeek,
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_off' => false,
    ]);

    return $staff;
}

test('admin can view the appointments page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/appointments')->assertOk();
});

test('staff cannot view the appointments page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/appointments')->assertForbidden();
});

test('guests are redirected from appointments to login', function () {
    $this->get('/admin/appointments')->assertRedirect(route('login'));
});

test('admin can book a manual appointment', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', APPT_TZ));
    apptSalon();
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = apptStaff([$service->id]);

    Livewire::test(AppointmentsComponent::class)
        ->call('create')
        ->set('form_client_id', $client->id)
        ->set('form_service_ids', [$service->id])
        ->set('form_staff_id', $staff->id)
        ->set('form_date', APPT_DATE)
        ->set('form_time', '12:00')
        ->set('form_status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->staff_id)->toBe($staff->id);
    expect($appointment->client_id)->toBe($client->id);
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointment->datetime_start->format('H:i'))->toBe('12:00');

    Carbon::setTestNow();
});

test('booking a taken slot surfaces a friendly error', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 08:00', APPT_TZ));
    apptSalon();
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 100]);
    $staff = apptStaff([$service->id]);

    Appointment::factory()->create([
        'staff_id' => $staff->id,
        'client_id' => Client::factory(),
        'datetime_start' => Carbon::parse(APPT_DATE.' 12:00', APPT_TZ),
        'datetime_end' => Carbon::parse(APPT_DATE.' 13:00', APPT_TZ),
        'status' => AppointmentStatus::Confirmed,
    ]);

    Livewire::test(AppointmentsComponent::class)
        ->call('create')
        ->set('form_client_id', $client->id)
        ->set('form_service_ids', [$service->id])
        ->set('form_staff_id', $staff->id)
        ->set('form_date', APPT_DATE)
        ->set('form_time', '12:00')
        ->call('save')
        ->assertHasErrors('form_time');

    // Only the pre-existing appointment exists — nothing double-booked.
    expect(Appointment::query()->count())->toBe(1);

    Carbon::setTestNow();
});

test('admin can change an appointment status', function () {
    $this->actingAs(User::factory()->admin()->create());

    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

    Livewire::test(AppointmentsComponent::class)
        ->call('changeStatus', $appointment->id, 'confirmed');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('an illegal status change is blocked in the component', function () {
    $this->actingAs(User::factory()->admin()->create());

    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Completed]);

    Livewire::test(AppointmentsComponent::class)
        ->call('changeStatus', $appointment->id, 'pending');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

test('admin can edit appointment notes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $appointment = Appointment::factory()->create(['notes' => 'old note']);

    Livewire::test(AppointmentsComponent::class)
        ->call('edit', $appointment->id)
        ->assertSet('edit_notes', 'old note')
        ->set('edit_notes', 'new note')
        ->call('updateNotes');

    expect($appointment->fresh()->notes)->toBe('new note');
});

test('admin can soft-delete an appointment', function () {
    $this->actingAs(User::factory()->admin()->create());

    $appointment = Appointment::factory()->create();

    Livewire::test(AppointmentsComponent::class)
        ->call('delete', $appointment->id);

    expect(Appointment::query()->find($appointment->id))->toBeNull();
    expect(Appointment::withTrashed()->find($appointment->id))->not->toBeNull();
});

test('admin can send a whatsapp reminder for an appointment', function () {
    Setting::factory()->create([
        'salon_name' => 'Glow',
        'reminder_template' => 'Hi {client}, reminder for {salon} on {date} at {time}.',
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $client = Client::factory()->create(['name' => 'Noura', 'phone' => '+966512345678']);
    $appointment = Appointment::factory()->create(['client_id' => $client->id]);

    Livewire::test(AppointmentsComponent::class)
        ->call('sendReminder', $appointment->id)
        ->assertSet('showReminder', true)
        ->assertSet('reminderUrl', fn ($url) => str_starts_with((string) $url, 'https://wa.me/966512345678?text='));

    expect(ReminderLog::query()->where('appointment_id', $appointment->id)->count())->toBe(1);
    expect(ReminderLog::query()->firstOrFail()->sent_by)->toBe($user->id);
});

test('appointments can be filtered by status', function () {
    $this->actingAs(User::factory()->admin()->create());

    $pending = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);
    $confirmed = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

    Livewire::test(AppointmentsComponent::class)
        ->set('statusFilter', 'confirmed')
        ->assertViewHas('appointments', fn ($appointments) => $appointments->contains($confirmed)
            && ! $appointments->contains($pending));
});

test('appointments can be filtered by client phone', function () {
    $this->actingAs(User::factory()->admin()->create());

    $target = Client::factory()->create(['phone' => '+966511111111']);
    $targetAppointment = Appointment::factory()->create(['client_id' => $target->id]);

    $otherAppointment = Appointment::factory()->create([
        'client_id' => Client::factory()->create(['phone' => '+966522222222'])->id,
    ]);

    Livewire::test(AppointmentsComponent::class)
        ->set('phoneSearch', '511111')
        ->assertViewHas('appointments', fn ($appointments) => $appointments->contains($targetAppointment)
            && ! $appointments->contains($otherAppointment));
});
