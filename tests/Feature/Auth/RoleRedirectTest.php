<?php

use App\Models\User;

test('admins are redirected to the admin area after login', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticated();
});

test('staff are redirected to the dashboard after login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});
