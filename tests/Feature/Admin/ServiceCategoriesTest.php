<?php

use App\Livewire\Admin\ServiceCategories;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Livewire\Livewire;

test('admin can view the service categories page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/service-categories')->assertOk();
});

test('staff cannot view the service categories page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/service-categories')->assertForbidden();
});

test('guests are redirected from service categories to login', function () {
    $this->get('/admin/service-categories')->assertRedirect(route('login'));
});

test('admin can create a category appended to the end of the order', function () {
    $this->actingAs(User::factory()->admin()->create());

    ServiceCategory::factory()->create(['sort_order' => 0]);

    Livewire::test(ServiceCategories::class)
        ->call('create')
        ->set('name', 'Hair')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $category = ServiceCategory::query()->where('name', 'Hair')->firstOrFail();

    expect($category->sort_order)->toBe(1);
    expect($category->is_active)->toBeTrue();
});

test('admin can edit a category', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ServiceCategory::factory()->create(['name' => 'Old', 'is_active' => true]);

    Livewire::test(ServiceCategories::class)
        ->call('edit', $category->id)
        ->assertSet('name', 'Old')
        ->set('name', 'New')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $category->refresh();

    expect($category->name)->toBe('New');
    expect($category->is_active)->toBeFalse();
});

test('category name is required', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ServiceCategories::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('deleting a category leaves its services intact with a null category', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['category_id' => $category->id]);

    Livewire::test(ServiceCategories::class)
        ->call('delete', $category->id);

    expect(ServiceCategory::query()->find($category->id))->toBeNull();

    $service->refresh();
    expect($service->exists)->toBeTrue();
    expect($service->category_id)->toBeNull();
});

test('moving a category down persists the new sort order', function () {
    $this->actingAs(User::factory()->admin()->create());

    $first = ServiceCategory::factory()->create(['name' => 'First', 'sort_order' => 0]);
    $second = ServiceCategory::factory()->create(['name' => 'Second', 'sort_order' => 1]);

    Livewire::test(ServiceCategories::class)
        ->call('moveDown', $first->id);

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('moving a category up persists the new sort order', function () {
    $this->actingAs(User::factory()->admin()->create());

    $first = ServiceCategory::factory()->create(['name' => 'First', 'sort_order' => 0]);
    $second = ServiceCategory::factory()->create(['name' => 'Second', 'sort_order' => 1]);

    Livewire::test(ServiceCategories::class)
        ->call('moveUp', $second->id);

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('moving the first category up is a no-op', function () {
    $this->actingAs(User::factory()->admin()->create());

    $first = ServiceCategory::factory()->create(['sort_order' => 0]);
    $second = ServiceCategory::factory()->create(['sort_order' => 1]);

    Livewire::test(ServiceCategories::class)
        ->call('moveUp', $first->id);

    expect($first->fresh()->sort_order)->toBe(0);
    expect($second->fresh()->sort_order)->toBe(1);
});

test('the active scope excludes inactive categories', function () {
    ServiceCategory::factory()->create(['is_active' => true]);
    ServiceCategory::factory()->create(['is_active' => false]);

    expect(ServiceCategory::query()->active()->count())->toBe(1);
});
