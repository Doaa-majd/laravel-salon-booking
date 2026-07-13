<?php

use App\Models\Appointment;
use App\Models\Staff;
use App\Models\User;
use App\Policies\AppointmentPolicy;

test('an admin can view and manage any appointment', function () {
    $admin = User::factory()->admin()->create();
    $appointment = Appointment::factory()->create();
    $policy = new AppointmentPolicy;

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->view($admin, $appointment))->toBeTrue();
    expect($policy->update($admin, $appointment))->toBeTrue();
    expect($policy->delete($admin, $appointment))->toBeTrue();
});

test('a staff user can only see and manage their own appointments', function () {
    $staff = Staff::factory()->create();
    $user = User::factory()->create(['staff_id' => $staff->id]);

    $own = Appointment::factory()->create(['staff_id' => $staff->id]);
    $other = Appointment::factory()->create();
    $policy = new AppointmentPolicy;

    expect($policy->view($user, $own))->toBeTrue();
    expect($policy->update($user, $own))->toBeTrue();
    expect($policy->delete($user, $own))->toBeTrue();

    expect($policy->view($user, $other))->toBeFalse();
    expect($policy->update($user, $other))->toBeFalse();
    expect($policy->delete($user, $other))->toBeFalse();
});

test('a staff user with no linked profile owns no appointments', function () {
    $user = User::factory()->create(['staff_id' => null]);
    $appointment = Appointment::factory()->create();

    expect((new AppointmentPolicy)->view($user, $appointment))->toBeFalse();
});
