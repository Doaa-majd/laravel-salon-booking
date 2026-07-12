<?php

use App\Livewire\Admin\Services;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Livewire\Livewire;

test('admin can view the services page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/services')->assertOk();
});

test('staff cannot view the services page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/services')->assertForbidden();
});

test('guests are redirected from services to login', function () {
    $this->get('/admin/services')->assertRedirect(route('login'));
});

test('admin can create a service', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ServiceCategory::factory()->create();

    Livewire::test(Services::class)
        ->call('create')
        ->set('category_id', (string) $category->id)
        ->set('name', 'Haircut')
        ->set('duration_minutes', 45)
        ->set('price', '120.50')
        ->set('color', '#1E88E5')
        ->set('description', 'A nice haircut')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $service = Service::query()->where('name', 'Haircut')->firstOrFail();

    expect($service->category_id)->toBe($category->id);
    expect($service->duration_minutes)->toBe(45);
    expect((float) $service->price)->toBe(120.50);
    expect($service->color)->toBe('#1E88E5');
    expect($service->is_active)->toBeTrue();
});

test('admin can create an uncategorised service', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Services::class)
        ->call('create')
        ->set('category_id', '')
        ->set('name', 'Loose service')
        ->set('duration_minutes', 30)
        ->set('price', '50')
        ->call('save')
        ->assertHasNoErrors();

    expect(Service::query()->where('name', 'Loose service')->value('category_id'))->toBeNull();
});

test('admin can edit a service', function () {
    $this->actingAs(User::factory()->admin()->create());

    $service = Service::factory()->create(['name' => 'Old', 'price' => '10.00']);

    Livewire::test(Services::class)
        ->call('edit', $service->id)
        ->assertSet('name', 'Old')
        ->set('name', 'Updated')
        ->set('price', '99.99')
        ->call('save')
        ->assertHasNoErrors();

    $service->refresh();

    expect($service->name)->toBe('Updated');
    expect((float) $service->price)->toBe(99.99);
});

test('service name is required', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Services::class)
        ->call('create')
        ->set('name', '')
        ->set('duration_minutes', 30)
        ->set('price', '10')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('service duration must be positive', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Services::class)
        ->call('create')
        ->set('name', 'Bad duration')
        ->set('duration_minutes', 0)
        ->set('price', '10')
        ->call('save')
        ->assertHasErrors(['duration_minutes' => 'min']);
});

test('service price cannot be negative', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Services::class)
        ->call('create')
        ->set('name', 'Bad price')
        ->set('duration_minutes', 30)
        ->set('price', '-5')
        ->call('save')
        ->assertHasErrors(['price' => 'min']);
});

test('service color must be a valid hex value', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Services::class)
        ->call('create')
        ->set('name', 'Bad color')
        ->set('duration_minutes', 30)
        ->set('price', '10')
        ->set('color', 'red')
        ->call('save')
        ->assertHasErrors(['color' => 'regex']);
});

test('deleting a service soft-deletes it', function () {
    $this->actingAs(User::factory()->admin()->create());

    $service = Service::factory()->create();

    Livewire::test(Services::class)
        ->call('delete', $service->id);

    expect(Service::query()->find($service->id))->toBeNull();
    expect(Service::withTrashed()->find($service->id))->not->toBeNull();
});

test('the active scope excludes inactive services', function () {
    Service::factory()->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    expect(Service::query()->active()->count())->toBe(1);
});

test('the inactive filter shows only inactive services', function () {
    $this->actingAs(User::factory()->admin()->create());

    Service::factory()->create(['name' => 'Active one', 'is_active' => true]);
    Service::factory()->create(['name' => 'Inactive one', 'is_active' => false]);

    Livewire::test(Services::class)
        ->set('statusFilter', 'inactive')
        ->assertSee('Inactive one')
        ->assertDontSee('Active one');
});
