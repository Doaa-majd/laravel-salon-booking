<?php

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use Illuminate\Database\Eloquent\Model;

dataset('models', [
    'Setting' => [Setting::class],
    'ServiceCategory' => [ServiceCategory::class],
    'Service' => [Service::class],
    'Staff' => [Staff::class],
    'StaffSchedule' => [StaffSchedule::class],
    'StaffTimeOff' => [StaffTimeOff::class],
    'Client' => [Client::class],
    'ClientPhoto' => [ClientPhoto::class],
    'Appointment' => [Appointment::class],
    'AppointmentService' => [AppointmentService::class],
    'ReminderLog' => [ReminderLog::class],
]);

test('each factory creates a persisted row', function (string $model) {
    /** @var Model $instance */
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue();
    expect($model::query()->whereKey($instance->getKey())->exists())->toBeTrue();
})->with('models');
