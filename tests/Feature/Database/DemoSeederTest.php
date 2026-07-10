<?php

use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('demo seeder produces a working salon dataset', function () {
    $this->seed(DemoSeeder::class);

    expect(Setting::query()->count())->toBe(1);
    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue();
    expect(Service::query()->count())->toBeGreaterThanOrEqual(6);
    expect(Staff::query()->count())->toBe(2);

    $staff = Staff::query()->with('schedules')->first();
    expect($staff->schedules)->toHaveCount(7);
    expect($staff->services()->count())->toBeGreaterThan(0);
});

test('demo seeder is idempotent for the settings singleton', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Setting::query()->count())->toBe(1);
    expect(User::query()->where('email', 'admin@example.com')->count())->toBe(1);
});
