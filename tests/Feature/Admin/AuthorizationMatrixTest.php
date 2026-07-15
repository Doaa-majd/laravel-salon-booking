<?php

use App\Models\User;

/**
 * A release-hardening sweep (roadmap Day 11): every admin-only screen must reject
 * guests (→ login) and staff-role users (→ 403), and admit admins. Parameterless
 * admin routes are covered here; parameterised and staff-reachable routes
 * (calendar, client profile, staff schedule) are covered in their own suites.
 *
 * @return list<string>
 */
function adminRouteNames(): array
{
    return [
        'admin.dashboard',
        'admin.settings.edit',
        'admin.service-categories.index',
        'admin.services.index',
        'admin.staff.index',
        'admin.clients.index',
        'admin.appointments.index',
        'admin.reports.index',
    ];
}

test('guests are redirected from every admin screen to login', function (string $name) {
    $this->get(route($name))->assertRedirect(route('login'));
})->with(adminRouteNames());

test('staff-role users are forbidden from every admin screen', function (string $name) {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(route($name))->assertForbidden();
})->with(adminRouteNames());

test('admins can reach every admin screen', function (string $name) {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route($name))->assertOk();
})->with(adminRouteNames());
