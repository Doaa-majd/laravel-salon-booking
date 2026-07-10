<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;

test('staff and services are linked many-to-many', function () {
    $staff = Staff::factory()->create();
    $services = Service::factory()->count(3)->create();

    $staff->services()->sync($services->pluck('id'));

    expect($staff->services)->toHaveCount(3);
    expect($services->first()->staff)->toHaveCount(1);
});

test('an appointment contains services with price and duration snapshots', function () {
    $appointment = Appointment::factory()->create();
    $service = Service::factory()->create();

    $appointment->services()->attach($service->id, [
        'price' => 150.00,
        'duration_minutes' => 60,
    ]);

    $attached = $appointment->services()->first();

    expect($appointment->services)->toHaveCount(1);
    expect($attached->pivot->price)->toEqual('150.00');
    expect($attached->pivot->duration_minutes)->toBe(60);
});

test('a client has appointments and photos', function () {
    $client = Client::factory()->create();
    Appointment::factory()->for($client)->create();
    ClientPhoto::factory()->for($client)->create();

    expect($client->appointments)->toHaveCount(1);
    expect($client->photos)->toHaveCount(1);
    expect($client->photos->first())->toBeInstanceOf(ClientPhoto::class);
});

test('a user can be linked to a staff profile', function () {
    $user = User::factory()->staff()->create();

    expect($user->staff)->toBeInstanceOf(Staff::class);
    expect($user->isStaff())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
});

test('an admin user is identified by role', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
    expect($user->isStaff())->toBeFalse();
});

test('domain enum and json casts resolve correctly', function () {
    $appointment = Appointment::factory()->create();
    expect($appointment->status)->toBeInstanceOf(AppointmentStatus::class);

    $setting = Setting::factory()->create();
    expect($setting->working_hours)->toBeArray();
    expect($setting->working_hours[0])->toHaveKeys(['open', 'close', 'closed']);
});
