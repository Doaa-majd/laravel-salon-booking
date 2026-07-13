<?php

use App\Enums\AppointmentStatus;
use App\Enums\ClientPhotoType;
use App\Livewire\Admin\ClientProfile;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('admin can view a client profile page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create(['name' => 'Profile Client']);

    $this->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee('Profile Client');
});

test('staff cannot view a client profile page', function () {
    $this->actingAs(User::factory()->create());

    $client = Client::factory()->create();

    $this->get(route('admin.clients.show', $client))->assertForbidden();
});

test('admin can upload and classify a before photo', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();

    Livewire::test(ClientProfile::class, ['client' => $client])
        ->set('photo', UploadedFile::fake()->image('before.jpg'))
        ->set('photoType', 'before')
        ->set('photoNote', 'Initial state')
        ->call('addPhoto')
        ->assertHasNoErrors();

    $photo = $client->photos()->firstOrFail();

    expect($photo->type)->toBe(ClientPhotoType::Before);
    expect($photo->note)->toBe('Initial state');
    expect($photo->appointment_id)->toBeNull();
    Storage::disk('public')->assertExists($photo->image_path);
});

test('a photo can be linked to one of the client appointments', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $appointment = Appointment::factory()->create(['client_id' => $client->id]);

    Livewire::test(ClientProfile::class, ['client' => $client])
        ->set('photo', UploadedFile::fake()->image('after.jpg'))
        ->set('photoType', 'after')
        ->set('photoAppointmentId', $appointment->id)
        ->call('addPhoto')
        ->assertHasNoErrors();

    expect($client->photos()->firstOrFail()->appointment_id)->toBe($appointment->id);
});

test('a photo cannot be linked to another client appointment', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $otherAppointment = Appointment::factory()->create();

    Livewire::test(ClientProfile::class, ['client' => $client])
        ->set('photo', UploadedFile::fake()->image('x.jpg'))
        ->set('photoAppointmentId', $otherAppointment->id)
        ->call('addPhoto')
        ->assertHasErrors('photoAppointmentId');
});

test('deleting a linked appointment nulls the photo link', function () {
    $client = Client::factory()->create();
    $appointment = Appointment::factory()->create(['client_id' => $client->id]);
    $photo = ClientPhoto::factory()->create([
        'client_id' => $client->id,
        'appointment_id' => $appointment->id,
    ]);

    $appointment->forceDelete();

    expect($photo->fresh()->appointment_id)->toBeNull();
});

test('admin can remove a photo', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();
    $photo = ClientPhoto::factory()->create(['client_id' => $client->id]);

    Livewire::test(ClientProfile::class, ['client' => $client])
        ->call('removePhoto', $photo->id);

    expect(ClientPhoto::query()->find($photo->id))->toBeNull();
});

test('total spend sums only completed appointment snapshots', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();

    Appointment::factory()->create([
        'client_id' => $client->id,
        'status' => AppointmentStatus::Completed,
        'price_snapshot' => 150.00,
    ]);
    Appointment::factory()->create([
        'client_id' => $client->id,
        'status' => AppointmentStatus::Completed,
        'price_snapshot' => 200.00,
    ]);
    Appointment::factory()->create([
        'client_id' => $client->id,
        'status' => AppointmentStatus::Cancelled,
        'price_snapshot' => 999.00,
    ]);

    Livewire::test(ClientProfile::class, ['client' => $client])
        ->assertViewHas('totalSpend', fn ($total) => (float) $total === 350.00);
});
