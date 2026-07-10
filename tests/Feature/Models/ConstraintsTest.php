<?php

use App\Models\Client;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Database\QueryException;

test('client phone must be unique', function () {
    Client::factory()->create(['phone' => '+966500000000']);

    expect(fn () => Client::factory()->create(['phone' => '+966500000000']))
        ->toThrow(QueryException::class);
});

test('a staff member cannot have two schedules for the same weekday', function () {
    $staff = Staff::factory()->create();
    StaffSchedule::factory()->create(['staff_id' => $staff->id, 'weekday' => 1]);

    expect(fn () => StaffSchedule::factory()->create(['staff_id' => $staff->id, 'weekday' => 1]))
        ->toThrow(QueryException::class);
});
