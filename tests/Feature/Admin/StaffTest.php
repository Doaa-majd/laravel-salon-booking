<?php

use App\Livewire\Admin\Staff as StaffComponent;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('admin can view the staff page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/staff')->assertOk();
});

test('staff cannot view the staff page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/staff')->assertForbidden();
});

test('guests are redirected from staff to login', function () {
    $this->get('/admin/staff')->assertRedirect(route('login'));
});

test('admin can create a staff member with linked services', function () {
    $this->actingAs(User::factory()->admin()->create());

    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();

    Livewire::test(StaffComponent::class)
        ->call('create')
        ->set('name', 'Sara')
        ->set('phone', '+966500000001')
        ->set('commission_percent', '15.50')
        ->set('bio', 'Senior stylist')
        ->set('service_ids', [$serviceA->id, $serviceB->id])
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $staff = Staff::query()->where('name', 'Sara')->firstOrFail();

    expect($staff->phone)->toBe('+966500000001');
    expect((float) $staff->commission_percent)->toBe(15.50);
    expect($staff->is_active)->toBeTrue();
    expect($staff->services()->pluck('services.id')->all())
        ->toEqualCanonicalizing([$serviceA->id, $serviceB->id]);
});

test('admin can edit a staff member and update service links', function () {
    $this->actingAs(User::factory()->admin()->create());

    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();

    $staff = Staff::factory()->create(['name' => 'Old name']);
    $staff->services()->attach($serviceA);

    Livewire::test(StaffComponent::class)
        ->call('edit', $staff->id)
        ->assertSet('name', 'Old name')
        ->assertSet('service_ids', [$serviceA->id])
        ->set('name', 'New name')
        ->set('service_ids', [$serviceB->id])
        ->call('save')
        ->assertHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe('New name');
    expect($staff->services()->pluck('services.id')->all())->toBe([$serviceB->id]);
});

test('staff name is required', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(StaffComponent::class)
        ->call('create')
        ->set('name', '')
        ->set('commission_percent', '0')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('staff commission cannot be negative', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(StaffComponent::class)
        ->call('create')
        ->set('name', 'Bad commission')
        ->set('commission_percent', '-1')
        ->call('save')
        ->assertHasErrors(['commission_percent' => 'min']);
});

test('staff commission cannot exceed one hundred', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(StaffComponent::class)
        ->call('create')
        ->set('name', 'Too much commission')
        ->set('commission_percent', '150')
        ->call('save')
        ->assertHasErrors(['commission_percent' => 'max']);
});

test('deleting a staff member soft-deletes it', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffComponent::class)
        ->call('delete', $staff->id);

    expect(Staff::query()->find($staff->id))->toBeNull();
    expect(Staff::withTrashed()->find($staff->id))->not->toBeNull();
});

test('the inactive filter shows only inactive staff', function () {
    $this->actingAs(User::factory()->admin()->create());

    Staff::factory()->create(['name' => 'Active member', 'is_active' => true]);
    Staff::factory()->create(['name' => 'Inactive member', 'is_active' => false]);

    Livewire::test(StaffComponent::class)
        ->set('statusFilter', 'inactive')
        ->assertSee('Inactive member')
        ->assertDontSee('Active member');
});

test('providesAllServices scope returns only fully-qualified staff', function () {
    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();

    $qualified = Staff::factory()->create();
    $qualified->services()->attach([$serviceA->id, $serviceB->id]);

    $partial = Staff::factory()->create();
    $partial->services()->attach($serviceA);

    $result = Staff::query()
        ->providesAllServices([$serviceA->id, $serviceB->id])
        ->pluck('id')
        ->all();

    expect($result)->toBe([$qualified->id]);
});

test('providesAllServices excludes staff whose service was soft-deleted', function () {
    $serviceA = Service::factory()->create();
    $serviceB = Service::factory()->create();

    $staff = Staff::factory()->create();
    $staff->services()->attach([$serviceA->id, $serviceB->id]);

    $serviceB->delete();

    $result = Staff::query()
        ->providesAllServices([$serviceA->id, $serviceB->id])
        ->pluck('id')
        ->all();

    expect($result)->toBe([]);
});

test('force-deleting a service cascades from the staff_services pivot', function () {
    $staff = Staff::factory()->create();
    $service = Service::factory()->create();
    $staff->services()->attach($service);

    expect($staff->services()->count())->toBe(1);

    $service->forceDelete();

    expect(
        DB::table('staff_services')
            ->where('service_id', $service->id)
            ->count()
    )->toBe(0);
});

test('a staff member can be linked to a user account', function () {
    $staff = Staff::factory()->create();
    $user = User::factory()->create(['staff_id' => $staff->id]);

    expect($staff->user->is($user))->toBeTrue();
    expect($user->staff->is($staff))->toBeTrue();
});
