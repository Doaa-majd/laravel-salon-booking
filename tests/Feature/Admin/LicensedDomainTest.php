<?php

use App\Models\Setting;
use App\Models\User;

test('an unset license domain leaves the admin area reachable', function () {
    Setting::factory()->create(['license_domain' => null]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.reports.index'))->assertOk();
});

test('a configured license domain rejects requests from another host', function () {
    Setting::factory()->create(['license_domain' => 'salon.example.com']);
    $this->actingAs(User::factory()->admin()->create());

    // The test client's default host is "localhost", which does not match.
    $this->get(route('admin.reports.index'))->assertForbidden();
});

test('a configured license domain allows requests from the licensed host', function () {
    Setting::factory()->create(['license_domain' => 'salon.example.com']);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('http://salon.example.com/admin/reports')->assertOk();
});

test('the license host comparison is case-insensitive', function () {
    Setting::factory()->create(['license_domain' => 'Salon.Example.com']);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('http://salon.example.com/admin/reports')->assertOk();
});

test('the license guard also protects the staff-facing calendar', function () {
    Setting::factory()->create(['license_domain' => 'salon.example.com']);
    $this->actingAs(User::factory()->staff()->create());

    $this->get(route('admin.calendar'))->assertForbidden();
});
