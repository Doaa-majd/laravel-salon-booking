<?php

use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;

test('a valid status transition updates the appointment', function () {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

    app(ChangeAppointmentStatus::class)->handle($appointment, AppointmentStatus::Confirmed);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('an illegal status transition throws and leaves the status unchanged', function () {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Completed]);

    expect(fn () => app(ChangeAppointmentStatus::class)->handle($appointment, AppointmentStatus::Pending))
        ->toThrow(InvalidStatusTransitionException::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

test('transitioning to the same status is a no-op', function () {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Pending]);

    app(ChangeAppointmentStatus::class)->handle($appointment, AppointmentStatus::Pending);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Pending);
});

test('terminal statuses allow no further transitions', function (AppointmentStatus $status) {
    expect($status->isTerminal())->toBeTrue();
    expect($status->allowedTransitions())->toBe([]);
})->with([
    'completed' => AppointmentStatus::Completed,
    'cancelled' => AppointmentStatus::Cancelled,
    'no_show' => AppointmentStatus::NoShow,
]);

test('pending may move to confirmed, completed, cancelled, or no-show', function () {
    $pending = AppointmentStatus::Pending;

    expect($pending->canTransitionTo(AppointmentStatus::Confirmed))->toBeTrue();
    expect($pending->canTransitionTo(AppointmentStatus::Completed))->toBeTrue();
    expect($pending->canTransitionTo(AppointmentStatus::Cancelled))->toBeTrue();
    expect($pending->canTransitionTo(AppointmentStatus::NoShow))->toBeTrue();
});

test('confirmed cannot go back to pending', function () {
    expect(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Pending))->toBeFalse();
});
