<?php

use App\Livewire\Admin\Clients as ClientsComponent;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;

test('admin can view the clients page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/clients')->assertOk();
});

test('staff cannot view the clients page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/clients')->assertForbidden();
});

test('guests are redirected from clients to login', function () {
    $this->get('/admin/clients')->assertRedirect(route('login'));
});

test('admin can create a client', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ClientsComponent::class)
        ->call('create')
        ->set('name', 'نورة')
        ->set('phone', '+966500000001')
        ->set('email', 'noura@example.com')
        ->set('skin_type', 'dry')
        ->set('hair_type', 'curly')
        ->set('tagsInput', 'VIP, حساسية, VIP')
        ->set('notes', 'Prefers morning appointments')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::query()->where('phone', '+966500000001')->firstOrFail();

    expect($client->name)->toBe('نورة');
    expect($client->email)->toBe('noura@example.com');
    expect($client->tags)->toEqualCanonicalizing(['VIP', 'حساسية']);
});

test('client phone must be unique', function () {
    $this->actingAs(User::factory()->admin()->create());

    Client::factory()->create(['phone' => '+966500000002']);

    Livewire::test(ClientsComponent::class)
        ->call('create')
        ->set('name', 'Duplicate')
        ->set('phone', '+966500000002')
        ->call('save')
        ->assertHasErrors(['phone' => 'unique']);
});

test('client phone must match the +966/+971 format', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ClientsComponent::class)
        ->call('create')
        ->set('name', 'Bad phone')
        ->set('phone', '0500000001')
        ->call('save')
        ->assertHasErrors('phone');
});

test('client phone is normalized before saving', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ClientsComponent::class)
        ->call('create')
        ->set('name', 'Spaced')
        ->set('phone', '+966 50-000 0003')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::query()->where('phone', '+966500000003')->exists())->toBeTrue();
});

test('admin can edit a client', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create(['name' => 'Old name', 'phone' => '+966500000004']);

    Livewire::test(ClientsComponent::class)
        ->call('edit', $client->id)
        ->assertSet('name', 'Old name')
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($client->fresh()->name)->toBe('New name');
});

test('editing a client keeps its own phone valid against the unique rule', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create(['phone' => '+966500000005']);

    Livewire::test(ClientsComponent::class)
        ->call('edit', $client->id)
        ->set('notes', 'Updated notes')
        ->call('save')
        ->assertHasNoErrors();
});

test('deleting a client soft-deletes it', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create();

    Livewire::test(ClientsComponent::class)
        ->call('delete', $client->id);

    expect(Client::query()->find($client->id))->toBeNull();
    expect(Client::withTrashed()->find($client->id))->not->toBeNull();
});

test('the client list can be searched by name and phone', function () {
    $this->actingAs(User::factory()->admin()->create());

    Client::factory()->create(['name' => 'Layla Ahmad', 'phone' => '+966511111111']);
    Client::factory()->create(['name' => 'Mona Saleh', 'phone' => '+966522222222']);

    Livewire::test(ClientsComponent::class)
        ->set('search', 'Layla')
        ->assertSee('Layla Ahmad')
        ->assertDontSee('Mona Saleh')
        ->set('search', '522222')
        ->assertSee('Mona Saleh')
        ->assertDontSee('Layla Ahmad');
});

test('the client list shows visit count and last visit', function () {
    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create(['name' => 'Visitor']);
    Appointment::factory()->count(2)->create(['client_id' => $client->id]);

    Livewire::test(ClientsComponent::class)
        ->assertViewHas('clients', function ($clients) {
            return $clients->firstWhere('name', 'Visitor')->appointments_count === 2;
        });
});
